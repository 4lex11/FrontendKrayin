<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Webkul\Churn\Contracts\PanelRepository;
use Webkul\Churn\Services\RetentionAdvice;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);
it('prioritizes history quality over category and never enables actions', function ($changes, $stats, $match, $expected) {
    $prediction = (object) array_replace(['risk' => 'Alto', 'limited_history' => false, 'automatic_action_allowed' => false, 'model_status' => 'experimental'], $changes);
    expect((new RetentionAdvice)->recommend($prediction, $stats, $match))->toBe($expected);
})->with([
    [[], ['features' => null, 'eligible' => false], true, 'no_history'],
    [[], ['features' => ['Recency' => 181], 'eligible' => false], true, 'ineligible'],
    [['automatic_action_allowed' => true], ['features' => [], 'eligible' => true], true, 'invalid'],
    [['model_status' => 'production'], ['features' => [], 'eligible' => true], true, 'invalid'],
    [['risk' => 'Other'], ['features' => [], 'eligible' => true], true, 'invalid'],
    [['limited_history' => true], ['features' => [], 'eligible' => true], false, 'unverified'],
    [['limited_history' => true], ['features' => [], 'eligible' => true], true, 'limited'],
    [[], ['features' => [], 'eligible' => true], null, 'unverified'],
]);
it('uses the stored API category consistently in listing and detail', function ($risk, $expected) {
    $customer = DB::table('churn_dataset_customers')->where('external_customer_id', 'AA-10315')->first();
    DB::table('churn_predictions')->where('dataset_customer_id', $customer->id)->update(['risk' => $risk, 'limited_history' => false]);
    $repo = app(PanelRepository::class);
    $filters = ['q' => 'AA-10315', 'risk' => '', 'status' => '', 'sort' => 'risk_desc', 'as_of' => '2018-06-30'];
    $listing = $repo->listing(null, $filters);
    $detail = $repo->detail($customer->id, null, '2018-06-30', 'churn180-v1');
    expect($listing['rows'][0]->advice)->toBe($expected)->and($detail['advice'])->toBe($expected);
    $this->actingAs(User::findOrFail(1), 'user');
    $this->get(route('admin.churn.index', $filters, false))->assertOk()->assertSee('data-advice="'.$expected.'"', false);
    $this->get(route('admin.churn.show', ['customer' => $customer->id], false))->assertOk()->assertSee('data-advice="'.$expected.'"', false)->assertSee(__('churn::app.advice_note'));
})->with([['Alto', 'high'], ['Medio', 'medium'], ['Bajo', 'low']]);
it('shows limited coverage before risk and changes to unverified if inputs change', function () {
    $customer = DB::table('churn_dataset_customers')->where('external_customer_id', 'AA-10315')->first();
    $repo = app(PanelRepository::class);
    $filters = ['q' => 'AA-10315', 'risk' => '', 'status' => '', 'sort' => 'risk_desc', 'as_of' => '2018-06-30'];
    expect($repo->listing(null, $filters)['rows'][0]->advice)->toBe('limited');
    DB::table('churn_sales_lines')->where('dataset_customer_id', $customer->id)->where('order_date', '2018-06-29')->increment('sales', 1);
    expect($repo->listing(null, $filters)['rows'][0]->advice)->toBe('unverified')
        ->and($repo->detail($customer->id, null, '2018-06-30', 'churn180-v1')['advice'])->toBe('unverified');
});
it('does not use a prediction from another version and protects scope', function () {
    $id = DB::table('churn_dataset_customers')->where('external_customer_id', 'AA-10315')->value('id');
    expect(app(PanelRepository::class)->detail($id, null, '2018-06-30', 'missing-version')['advice'])->toBe('missing');
    $filters = ['q' => '', 'risk' => '', 'status' => '', 'sort' => 'risk_desc', 'as_of' => '2018-06-30'];
    expect(app(PanelRepository::class)->listing([], $filters)['rows']->count())->toBe(0);
});
it('keeps no-history distinct from a missing prediction', function () {
    $service = new RetentionAdvice;
    expect($service->recommend(null, ['features' => null, 'eligible' => false], null))->toBe('no_history')
        ->and($service->recommend(null, ['features' => [], 'eligible' => false], null))->toBe('ineligible')
        ->and($service->recommend(null, ['features' => [], 'eligible' => true], null))->toBe('missing');
});
it('provides translations for every rule in both supported languages', function () {
    foreach (['es', 'en'] as $locale) {
        foreach (['no_history', 'ineligible', 'missing', 'invalid', 'unverified', 'limited', 'high', 'medium', 'low'] as $rule) {
            expect(Lang::has('churn::app.advice.'.$rule, $locale))->toBeTrue()->and(Lang::has('churn::app.advice_reasons.'.$rule, $locale))->toBeTrue();
        }
    }
});
