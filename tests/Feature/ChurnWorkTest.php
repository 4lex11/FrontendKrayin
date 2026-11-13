<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Webkul\Churn\Contracts\WorkRepository;
use Webkul\Churn\Exceptions\ChurnApiException;
use Webkul\Churn\Services\ChurnApiClient;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);
beforeEach(function () {
    DB::table('churn_work_items')->delete();
    $this->customer = DB::table('churn_dataset_customers')->where('external_customer_id', 'AA-10315')->first();
});
it('queues once and scopes progress without leaking other owners', function () {
    $repo = app(WorkRepository::class);
    expect($repo->enqueue(null, '2018-06-30', $this->customer->id, 1))->toBe(['added' => 1, 'existing' => 0]);
    expect($repo->enqueue(null, '2018-06-30', $this->customer->id, 1))->toBe(['added' => 0, 'existing' => 1]);
    expect($repo->progress(null, '2018-06-30')['counts']['pending'])->toBe(1)
        ->and($repo->progress([], '2018-06-30')['counts']['pending'])->toBe(0)
        ->and($repo->progress([-1], '2018-06-30')['items'])->toHaveCount(0);
});
it('cannot queue a customer outside its scope or an invalid date', function () {
    $repo = app(WorkRepository::class);
    expect(fn () => $repo->enqueue([], '2018-06-30', $this->customer->id, 1))->toThrow(HttpException::class);
    expect(fn () => $repo->enqueue(null, '2026-10-29', null, 1))->toThrow(ChurnApiException::class);
    expect(DB::table('churn_work_items')->count())->toBe(0);
});
it('queues only owners in the requested batch', function () {
    $repo = app(WorkRepository::class);
    expect($repo->enqueue([], '2018-06-30', null, 1)['added'])->toBe(0);
    $owner = DB::table('persons')->where('id', $this->customer->person_id)->value('user_id');
    $expected = DB::table('churn_dataset_customers as c')->join('persons', 'persons.id', '=', 'c.person_id')->where('persons.user_id', $owner)->count();
    expect($repo->enqueue([$owner], '2018-06-30', null, 1)['added'])->toBe($expected);
});
it('processes a real stored response and reuses it without changing the timestamp', function () {
    $saved = DB::table('churn_predictions')->where('dataset_customer_id', $this->customer->id)->where('as_of', '2018-06-30')->first();
    $mock = Mockery::mock(ChurnApiClient::class);
    $mock->shouldReceive('predictTransactions')->once()->andReturn(json_decode($saved->response, true));
    app()->instance(ChurnApiClient::class, $mock);
    $repo = app(WorkRepository::class);
    $repo->enqueue(null, '2018-06-30', $this->customer->id, 1);
    expect($repo->process(1))->toBe(['completed' => 1, 'error' => 0, 'skipped' => 0]);
    $item = DB::table('churn_work_items')->first();
    expect($item->outcome)->toBe('reused')->and((int) $item->prediction_id)->toBe((int) $saved->id)
        ->and(DB::table('churn_predictions')->where('id', $saved->id)->value('predicted_at'))->toBe($saved->predicted_at)
        ->and(DB::table('churn_prediction_runs')->where('work_item_id', $item->id)->value('status'))->toBe('reused');
});
it('preserves saved predictions on failure and permits a new explicit request', function () {
    $before = DB::table('churn_predictions')->where('dataset_customer_id', $this->customer->id)->get()->toJson();
    $mock = Mockery::mock(ChurnApiClient::class);
    $mock->shouldReceive('predictTransactions')->once()->andThrow(new ChurnApiException('http_error', 503, 3));
    app()->instance(ChurnApiClient::class, $mock);
    $repo = app(WorkRepository::class);
    $repo->enqueue(null, '2018-06-30', $this->customer->id, 1);
    expect($repo->process(1)['error'])->toBe(1)->and(DB::table('churn_work_items')->value('status'))->toBe('error')
        ->and(DB::table('churn_predictions')->where('dataset_customer_id', $this->customer->id)->get()->toJson())->toBe($before);
    expect($repo->enqueue(null, '2018-06-30', $this->customer->id, 1)['added'])->toBe(1);
});
it('skips ineligible customers without calling the API', function () {
    $mock = Mockery::mock(ChurnApiClient::class);
    $mock->shouldNotReceive('predictTransactions');
    app()->instance(ChurnApiClient::class, $mock);
    $repo = app(WorkRepository::class);
    $repo->enqueue(null, '2015-01-03', $this->customer->id, 1);
    expect($repo->process(1)['skipped'])->toBe(1)->and(DB::table('churn_work_items')->value('outcome'))->toBe('not_eligible');
});
it('closes interrupted queue items without automatically retrying them', function () {
    $repo = app(WorkRepository::class);
    $repo->enqueue(null, '2018-06-30', $this->customer->id, 1);
    DB::table('churn_work_items')->update(['status' => 'running']);
    $id = DB::table('churn_work_items')->value('id');
    DB::table('churn_prediction_runs')->insert(['dataset_customer_id' => $this->customer->id, 'as_of' => '2018-06-30', 'input_sha256' => str_repeat('a', 64), 'status' => 'running', 'work_item_id' => $id, 'started_at' => now()]);
    expect($repo->process(1)['error'])->toBe(1)->and(DB::table('churn_work_items')->value('outcome'))->toBe('worker_interrupted')
        ->and(DB::table('churn_prediction_runs')->where('work_item_id', $id)->value('status'))->toBe('failed');
});
it('rejects a changed model configuration instead of writing an unexpected version', function () {
    $repo = app(WorkRepository::class);
    $repo->enqueue(null, '2018-06-30', $this->customer->id, 1);
    config(['churn.api.model_version' => 'changed']);
    expect($repo->process(1)['error'])->toBe(1)->and(DB::table('churn_work_items')->value('outcome'))->toBe('model_configuration_changed');
});
it('enforces read and update permissions on POST', function ($permissions, $expected) {
    $role = Role::create(['name' => 'CRM044 test', 'permission_type' => 'custom', 'permissions' => $permissions]);
    $user = User::create(['name' => 'CRM044 test', 'email' => 'crm044-test@example.invalid', 'password' => bcrypt('test'), 'status' => 1, 'view_permission' => 'global', 'role_id' => $role->id]);
    $this->actingAs($user, 'user')->post(route('admin.churn.update', [], false), ['scope' => 'customer', 'customer' => $this->customer->id, 'as_of' => '2018-06-30'])->assertStatus($expected);
    expect(DB::table('churn_work_items')->count())->toBe($expected === 302 ? 1 : 0);
})->with([[['churn'], 401], [['churn.update'], 403], [['churn', 'churn.update'], 302]]);
it('requires explicit target and valid parameters', function ($data) {
    $this->actingAs(User::findOrFail(1), 'user')->postJson(route('admin.churn.update', [], false), $data)->assertUnprocessable();
    expect(DB::table('churn_work_items')->count())->toBe(0);
})->with([[['scope' => 'customer', 'as_of' => '2018-06-30']], [['scope' => 'visible', 'as_of' => '2026-10-29']], [['scope' => 'visible', 'customer' => 1, 'as_of' => '2018-06-30']]]);
it('validates CLI input without doing work', function () {
    $this->artisan('churn:queue')->assertExitCode(1);
    $this->artisan('churn:queue', ['--all' => true, '--customer' => 'AA-10315'])->assertExitCode(1);
    $this->artisan('churn:work', ['--limit' => 0])->assertExitCode(1);
    $this->artisan('churn:work', ['--limit' => 1001])->assertExitCode(1);
});

it('refuses a concurrent worker while another connection holds its lock', function () {
    config(['database.connections.churn_lock_test' => config('database.connections.mysql')]);
    $other = DB::connection('churn_lock_test');
    $lock = 'churn-worker-'.substr(hash('sha256', DB::connection()->getDatabaseName()), 0, 32);
    try {
        expect((int) $other->selectOne('SELECT GET_LOCK(?,0) AS acquired', [$lock])->acquired)->toBe(1);
        expect(fn () => app(WorkRepository::class)->process(1))->toThrow(ChurnApiException::class, 'worker_busy');
    } finally {
        $other->selectOne('SELECT RELEASE_LOCK(?)', [$lock]);
        DB::purge('churn_lock_test');
    }
});
it('honors processing limits and leaves remaining requests pending', function () {
    $repo = app(WorkRepository::class);
    foreach (DB::table('churn_dataset_customers')->orderBy('id')->limit(2)->pluck('id') as $id) {
        $repo->enqueue(null, '2015-01-03', $id, 1);
    }
    expect($repo->process(1)['skipped'])->toBe(1)->and(DB::table('churn_work_items')->where('status', 'pending')->count())->toBe(1);
});
it('rejects a conflicting result without overwriting the original prediction', function () {
    $saved = DB::table('churn_predictions')->where('dataset_customer_id', $this->customer->id)->first();
    $response = json_decode($saved->response, true);
    $response['probabilidad_churn'] = 0.99;
    $mock = Mockery::mock(ChurnApiClient::class);
    $mock->shouldReceive('predictTransactions')->once()->andReturn($response);
    app()->instance(ChurnApiClient::class, $mock);
    $repo = app(WorkRepository::class);
    $repo->enqueue(null, '2018-06-30', $this->customer->id, 1);
    expect($repo->process(1)['error'])->toBe(1)->and(DB::table('churn_work_items')->value('outcome'))->toBe('prediction_conflict')
        ->and(DB::table('churn_predictions')->where('id', $saved->id)->value('response'))->toBe($saved->response);
});
