<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Webkul\Churn\Contracts\PanelRepository;
use Webkul\Churn\Services\HistoricalFeatures;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

it('shows the saved prediction with matching input and historical variables', function () {
    $id = DB::table('churn_dataset_customers')->where('external_customer_id', 'AA-10315')->value('id');
    $r = app(PanelRepository::class)->detail($id, null, '2018-06-30', 'churn180-v1');
    expect($r['inputMatches'])->toBeTrue()->and($r['statistics']['features']['Frequency'])->toBe(1)
        ->and($r['statistics']['features']['Recency'])->toBe(1)
        ->and(abs($r['statistics']['features']['Monetary_Std'] - 248.4773229089528))->toBeLessThan(1e-8);
    $this->actingAs(User::findOrFail(1), 'user')->get(route('admin.churn.show', ['customer' => $id], false))->assertOk()->assertSee('34.01 %')->assertSee('Monetary_Mean')->assertSee('180');
});
it('does not disclose a customer outside the permitted scope', function ($owners) {
    $id = DB::table('churn_dataset_customers')->value('id');
    try {
        app(PanelRepository::class)->detail($id, $owners, '2018-06-30', 'churn180-v1');
        $this->fail('Expected 404');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(404);
    }
})->with([[[]], [[-1]]]);
it('does not substitute a different cut or model and detects changed inputs', function () {
    $id = DB::table('churn_dataset_customers')->where('external_customer_id', 'AA-10315')->value('id');
    $repo = app(PanelRepository::class);
    expect($repo->detail($id, null, '2018-06-30', 'other')['prediction'])->toBeNull()
        ->and($repo->detail($id, null, '2018-05-31', 'churn180-v1')['prediction'])->toBeNull();
    DB::table('churn_predictions')->where('dataset_customer_id', $id)->update(['input_sha256' => str_repeat('0', 64)]);
    expect($repo->detail($id, null, '2018-06-30', 'churn180-v1')['inputMatches'])->toBeFalse();
});
it('excludes future and boundary sales and uses unique orders and sample deviation', function () {
    $row = fn ($date, $sales) => ['order_date' => $date, 'sales' => $sales, 'order_id' => 'same', 'category' => 'C', 'subcategory' => 'S'];
    $stats = (new HistoricalFeatures)->summarize([$row('2017-06-30', 999), $row('2018-07-01', 999), $row('2018-06-30', 10), $row('2018-06-30', 30)], '2018-06-30');
    expect($stats['line_count'])->toBe(2)->and($stats['features']['Frequency'])->toBe(1)->and($stats['features']['Monetary_Sum'])->toBe(40.0)
        ->and(abs($stats['features']['Monetary_Std'] - sqrt(200)))->toBeLessThan(1e-9);
});
it('handles empty histories and eligibility boundary', function () {
    $service = new HistoricalFeatures;
    expect($service->summarize([], '2018-06-30')['features'])->toBeNull();
    foreach ([180 => true, 181 => false] as $days => $eligible) {
        $row = ['order_date' => (new DateTimeImmutable('2018-06-30'))->modify("-$days days")->format('Y-m-d'), 'sales' => 1, 'order_id' => 'A', 'category' => 'C', 'subcategory' => 'S'];
        $stats = $service->summarize([$row], '2018-06-30');
        expect($stats['eligible'])->toBe($eligible)->and($stats['features']['Monetary_Std'])->toBe(0.0);
    }
});
it('validates detail query parameters', function ($query) {
    $id = DB::table('churn_dataset_customers')->value('id');
    $this->actingAs(User::findOrFail(1), 'user')->getJson(route('admin.churn.show', ['customer' => $id] + $query, false))->assertUnprocessable();
})->with([[['version' => ['bad']]], [['as_of' => '2026-10-29']], [['history_page' => 0]]]);

it('paginates historical versions with links to the exact selection', function () {
    $saved = (array) DB::table('churn_predictions')->first();
    unset($saved['id']);
    for ($i = 0; $i < 12; $i++) {
        $saved['model_version'] = 'history-'.$i;
        DB::table('churn_predictions')->insert($saved);
    }
    $this->actingAs(User::findOrFail(1), 'user');
    $url = route('admin.churn.show', ['customer' => $saved['dataset_customer_id'], 'as_of' => '2018-06-30', 'version' => 'history-1'], false);
    $first = $this->get($url)->assertOk()->assertDontSee('data-testid="queue-form"', false);
    $second = $this->get($url.'&history_page=2')->assertOk();
    expect($first->viewData('history')->total())->toBe(13)->and($second->viewData('history')->count())->toBe(3)
        ->and($first->viewData('prediction')->model_version)->toBe('history-1');
    expect(array_intersect($first->viewData('history')->pluck('id')->all(), $second->viewData('history')->pluck('id')->all()))->toBeEmpty();
});
it('protects detail URLs for individual users and guests', function () {
    $id = DB::table('churn_dataset_customers')->value('id');
    $url = route('admin.churn.show', ['customer' => $id], false);
    $this->get($url)->assertRedirect(route('admin.session.create'));
    $role = Role::create(['name' => 'CRM043 test', 'permission_type' => 'custom', 'permissions' => ['churn']]);
    $user = User::create(['name' => 'CRM043 test', 'email' => 'crm043@example.invalid', 'password' => bcrypt('test'), 'status' => 1, 'view_permission' => 'individual', 'role_id' => $role->id]);
    $this->actingAs($user, 'user')->get($url)->assertNotFound();
});
