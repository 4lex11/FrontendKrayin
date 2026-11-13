<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Webkul\Churn\Contracts\PanelRepository;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->filters = ['q' => '', 'risk' => '', 'status' => '', 'sort' => 'risk_desc', 'as_of' => '2018-05-31'];
    $this->customers = DB::table('churn_dataset_customers')->orderBy('id')->limit(3)->get();
    foreach ([0.9, 0.1] as $i => $probability) {
        DB::table('churn_predictions')->insert([
            'dataset_customer_id' => $this->customers[$i]->id, 'as_of' => '2018-05-31', 'model_version' => config('churn.api.model_version'),
            'schema_version' => 'churn180-v1', 'horizon_days' => 180, 'probability' => $probability, 'risk' => $i === 0 ? 'Alto' : 'Bajo',
            'limited_history' => 0, 'automatic_action_allowed' => 0, 'model_status' => 'experimental', 'input_sha256' => str_repeat('a', 64),
            'predicted_at' => '2026-10-28 00:00:00', 'response' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    foreach (['succeeded', 'failed'] as $state) {
        DB::table('churn_prediction_runs')->insert(['dataset_customer_id' => $this->customers[0]->id, 'as_of' => '2018-05-31', 'input_sha256' => str_repeat('a', 64), 'status' => $state, 'started_at' => now(), 'finished_at' => now()]);
    }
    $this->repository = app(PanelRepository::class);
});

it('counts the same filtered population and selects one row per customer', function () {
    $result = $this->repository->listing(null, $this->filters);
    expect($result['summary']['total'])->toBe(793)->and($result['summary']['saved'])->toBe(2)
        ->and($result['summary']['missing_count'])->toBe(791)->and($result['rows']->total())->toBe(793)->and($result['rows']->count())->toBe(20);
    expect($result['summary']['high_count'] + $result['summary']['medium_count'] + $result['summary']['low_count'] + $result['summary']['missing_count'])->toBe($result['summary']['total']);
});

it('sorts numeric probabilities and keeps absent predictions last', function () {
    $desc = $this->repository->listing(null, $this->filters)['rows'];
    $asc = $this->repository->listing(null, array_replace($this->filters, ['sort' => 'risk_asc']))['rows'];
    expect((float) $desc[0]->probability)->toBe(0.9)->and((float) $asc[0]->probability)->toBe(0.1)->and($asc[2]->probability)->toBeNull();
});

it('applies risk and status to both cards and rows', function ($filter, $value, $expected) {
    $result = $this->repository->listing(null, array_replace($this->filters, [$filter => $value]));
    expect($result['summary']['total'])->toBe($expected)->and($result['rows']->total())->toBe($expected);
})->with([['risk', 'Alto', 1], ['risk', 'Medio', 0], ['status', 'saved', 2], ['status', 'missing', 791], ['status', 'failed', 1], ['status', 'running', 0]]);

it('preserves the stored result when the latest run failed', function () {
    $row = $this->repository->listing(null, array_replace($this->filters, ['status' => 'failed']))['rows'][0];
    expect($row->run_status)->toBe('failed')->and((float) $row->probability)->toBe(0.9);
});

it('searches external IDs and treats wildcard characters literally', function () {
    $result = $this->repository->listing(null, array_replace($this->filters, ['q' => $this->customers[0]->external_customer_id]));
    expect($result['rows']->total())->toBe(1);
    $none = $this->repository->listing(null, array_replace($this->filters, ['q' => '%_']));
    expect($none['rows']->total())->toBe(0);
});

it('does not leak counts or matches outside the permitted owners', function () {
    foreach ([[], [-1]] as $owners) {
        $result = $this->repository->listing($owners, $this->filters);
        expect($result['summary']['total'])->toBe(0)->and($result['rows']->total())->toBe(0);
    }
});

it('selects the exact date and model version without repeating customers', function () {
    DB::table('churn_predictions')->where('as_of', '2018-05-31')->update(['model_version' => 'different-version']);
    $result = $this->repository->listing(null, $this->filters);
    expect($result['summary']['saved'])->toBe(0)->and($result['rows']->total())->toBe(793);
});

it('renders the listing and paginates without overlapping customers', function () {
    $this->actingAs(User::findOrFail(1), 'user');
    $url = route('admin.churn.index', ['as_of' => '2018-05-31', 'status' => 'missing'], false);
    $first = $this->get($url)->assertOk()->assertViewIs('churn::index');
    $second = $this->get($url.'&page=2')->assertOk();
    $firstIds = $first->viewData('rows')->pluck('external_customer_id')->all();
    $secondIds = $second->viewData('rows')->pluck('external_customer_id')->all();
    expect(array_intersect($firstIds, $secondIds))->toBeEmpty()
        ->and($second->viewData('rows')->currentPage())->toBe(2)
        ->and($second->viewData('rows')->previousPageUrl())->toContain('status=missing');
    $first->assertSee('—')->assertDontSee('0.00 %');
});

it('rejects invalid filters before querying', function ($query) {
    $this->actingAs(User::findOrFail(1), 'user')->getJson(route('admin.churn.index', $query, false))->assertUnprocessable();
})->with([[['sort' => 'DROP TABLE']], [['risk' => 'Unknown']], [['page' => -1]], [['as_of' => '2026-10-28']], [['q' => ['nested']]]]);
