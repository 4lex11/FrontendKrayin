<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Webkul\Churn\Contracts\ReviewRepository;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);
beforeEach(function () {
    DB::table('churn_reviews')->delete();
    $this->customer = DB::table('churn_dataset_customers')->where('external_customer_id', 'AA-10315')->first();
    $this->data = ['as_of' => '2018-06-30', 'version' => 'churn180-v1', 'status' => 'pending', 'responsible_id' => 1, 'note' => 'Revisión de demostración', 'expected_id' => 0];
    $this->repo = app(ReviewRepository::class);
    $this->url = route('admin.churn.review', ['customer' => $this->customer->id], false);
});
it('records append-only state changes with server authorship and immutable previous notes', function () {
    $before = DB::table('churn_predictions')->get()->toJson();
    foreach (['pending', 'reviewed', 'discarded'] as $state) {
        $this->data['status'] = $state;
        $this->data['note'] = 'Note '.$state;
        $this->repo->record($this->customer->id, null, $this->data, 1);
        $this->data['expected_id'] = DB::table('churn_reviews')->max('id');
    }
    $view = $this->repo->overview($this->customer->id, null, '2018-06-30', 'churn180-v1');
    expect($view['latest']->status)->toBe('discarded')->and($view['history']->total())->toBe(3)
        ->and(DB::table('churn_reviews')->orderBy('id')->value('note'))->toBe('Note pending')
        ->and((int) $view['latest']->author_id)->toBe(1)->and($view['latest']->created_at)->not->toBeNull()
        ->and(DB::table('churn_predictions')->get()->toJson())->toBe($before);
});
it('rejects stale or duplicated submissions without losing history', function () {
    $this->repo->record($this->customer->id, null, $this->data, 1);
    expect(fn () => $this->repo->record($this->customer->id, null, $this->data, 1))->toThrow(ValidationException::class);
    expect(DB::table('churn_reviews')->count())->toBe(1);
});
it('separates customer date and version and allows a missing prediction', function () {
    $this->repo->record($this->customer->id, null, $this->data, 1);
    foreach ([['2018-05-31', 'churn180-v1'], ['2018-06-30', 'other-version']] as [$date,$version]) {
        expect($this->repo->overview($this->customer->id, null, $date, $version)['latest'])->toBeNull();
        $data = array_replace($this->data, ['as_of' => $date, 'version' => $version]);
        $this->repo->record($this->customer->id, null, $data, 1);
    }
    expect(DB::table('churn_reviews')->count())->toBe(3);
});
it('rejects out of scope read and write', function ($owners) {
    expect(fn () => $this->repo->overview($this->customer->id, $owners, '2018-06-30', 'churn180-v1'))->toThrow(HttpException::class);
    expect(fn () => $this->repo->record($this->customer->id, $owners, $this->data, 1))->toThrow(HttpException::class);
    expect(DB::table('churn_reviews')->count())->toBe(0);
})->with([[[]], [[-1]]]);
it('rejects inactive or unknown responsible users', function ($id) {
    if ($id === 1) {
        DB::table('users')->where('id', 1)->update(['status' => 0]);
    }
    $this->data['responsible_id'] = $id;
    expect(fn () => $this->repo->record($this->customer->id, null, $this->data, 1))->toThrow(ValidationException::class);
})->with([1, 999999]);
it('rejects a responsible user outside individual scope', function () {
    $role = Role::create(['name' => 'review-test', 'permission_type' => 'custom', 'permissions' => ['churn', 'churn.review']]);
    $user = User::create(['name' => 'review-test', 'email' => 'review-test@example.invalid', 'password' => bcrypt('test'), 'status' => 1, 'role_id' => $role->id, 'view_permission' => 'individual']);
    DB::table('persons')->where('id', $this->customer->person_id)->update(['user_id' => $user->id]);
    expect($this->repo->overview($this->customer->id, [$user->id], '2018-06-30', 'churn180-v1')['responsibles']->pluck('id')->all())->toBe([$user->id]);
    expect(fn () => $this->repo->record($this->customer->id, [$user->id], $this->data, $user->id))->toThrow(ValidationException::class);
});
it('enforces separate read and review permissions', function ($permissions, $expected) {
    $role = Role::create(['name' => 'review-test', 'permission_type' => 'custom', 'permissions' => $permissions]);
    $user = User::create(['name' => 'review-test', 'email' => 'review-test@example.invalid', 'password' => bcrypt('test'), 'status' => 1, 'role_id' => $role->id, 'view_permission' => 'global']);
    $this->actingAs($user, 'user')->postJson($this->url, $this->data)->assertStatus($expected);
    expect(DB::table('churn_reviews')->count())->toBe($expected === 302 ? 1 : 0);
})->with([[['churn'], 401], [['churn', 'churn.update'], 401], [['churn.review'], 403], [['churn', 'churn.review'], 302]]);
it('validates notes statuses dates and context versions', function ($change) {
    $this->actingAs(User::findOrFail(1), 'user')->postJson($this->url, array_replace($this->data, $change))->assertUnprocessable();
    expect(DB::table('churn_reviews')->count())->toBe(0);
})->with([[['note' => '   ']], [['note' => str_repeat('a', 2001)]], [['status' => 'contacted']], [['as_of' => '2026-10-29']], [['version' => ['invalid']]], [['expected_id' => -1]]]);
it('escapes notes and ignores spoofed authors and timestamps', function () {
    $this->data['note'] = '<script>alert(1)</script>';
    $this->actingAs(User::findOrFail(1), 'user')->post($this->url, $this->data + ['author_id' => 999, 'created_at' => '2000-01-01'])->assertRedirect();
    $row = DB::table('churn_reviews')->first();
    expect((int) $row->author_id)->toBe(1)->and($row->created_at)->not->toContain('2000-01-01');
    $this->get(route('admin.churn.show', ['customer' => $this->customer->id], false))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
});
it('paginates review history independently from predictions', function () {
    for ($i = 0; $i < 12; $i++) {
        $this->repo->record($this->customer->id, null, $this->data, 1);
        $this->data['expected_id'] = DB::table('churn_reviews')->max('id');
    }
    $this->actingAs(User::findOrFail(1), 'user');
    $url = route('admin.churn.show', ['customer' => $this->customer->id], false);
    $a = $this->get($url)->assertOk()->viewData('reviews');
    $b = $this->get($url.'?review_page=2')->assertOk()->viewData('reviews');
    expect($a['history']->count())->toBe(10)->and($b['history']->count())->toBe(2)->and($a['latest']->id)->toBe($b['latest']->id)
        ->and(array_intersect($a['history']->pluck('id')->all(), $b['history']->pluck('id')->all()))->toBeEmpty();
});
it('requires a session for review submissions', function () {
    $this->post($this->url, $this->data)->assertRedirect(route('admin.session.create'));
    expect(DB::table('churn_reviews')->count())->toBe(0);
});
