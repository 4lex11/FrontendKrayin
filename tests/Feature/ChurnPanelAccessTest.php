<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;
use Webkul\Churn\Contracts\PanelRepository;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ChurnPanelAccessTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private string $url;

    public static function permissionCases(): array
    {
        return [[['dashboard']], [['churn.update']]];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => 'CRM041 test', 'permission_type' => 'custom', 'permissions' => ['churn']]);
        $this->user = User::create(['name' => 'CRM041 test', 'email' => 'crm041-test@example.invalid', 'password' => bcrypt('test-only'), 'status' => 1, 'view_permission' => 'individual', 'role_id' => $role->id]);
        $this->url = route('admin.churn.index', [], false);
    }

    // requires login even for direct URLs
    public function test_case1(): void
    {
        $this->get($this->url)->assertRedirect(route('admin.session.create'));
    }

    // denies access and hides the menu without the read permission
    #[DataProvider('permissionCases')]
    public function test_case2($permissions): void
    {
        $this->user->role->update(['permissions' => $permissions]);
        $this->actingAs($this->user, 'user')->get($this->url)->assertStatus(401);
        expect(menu()->getItems('admin')->contains(fn ($item) => $item->getKey() === 'churn'))->toBeFalse();
    }

    // renders the native layout and menu for a reader without update access
    public function test_case3(): void
    {
        $response = $this->actingAs($this->user, 'user')->get($this->url);
        $response->assertOk()->assertViewIs('churn::index')->assertViewHas('visiblePredictions', 0);
        $response->assertSee('data-testid="summary-total"', false)->assertSee(__('churn::app.title'));
        expect(menu()->getItems('admin')->contains(fn ($item) => $item->getKey() === 'churn'))->toBeTrue()
            ->and(bouncer()->hasPermission('churn.update'))->toBeFalse();
    }

    // does not allow an inactive user to enter
    public function test_case4(): void
    {
        $this->user->update(['status' => 0]);
        $this->actingAs($this->user, 'user')->get($this->url)->assertRedirect(route('admin.session.create'));
    }

    // filters individual visibility by the current contact owner
    public function test_case5(): void
    {
        $person = DB::table('churn_predictions as p')->join('churn_dataset_customers as c', 'c.id', '=', 'p.dataset_customer_id')->value('c.person_id');
        DB::table('persons')->where('id', $person)->update(['user_id' => $this->user->id]);
        $expected = app(PanelRepository::class)->visiblePredictionCount([$this->user->id]);
        expect($expected)->toBeGreaterThan(0);
        $this->actingAs($this->user, 'user')->get($this->url)->assertOk()->assertViewHas('visiblePredictions', $expected);
    }

    // does not treat an empty group as global access
    public function test_case6(): void
    {
        $this->user->update(['view_permission' => 'group']);
        $this->actingAs($this->user, 'user')->get($this->url)->assertOk()->assertViewHas('visiblePredictions', 0);
        expect(app(PanelRepository::class)->visiblePredictionCount(null))->toBeGreaterThan(0);
    }

    // allows only owners in the same group
    public function test_case7(): void
    {
        $this->user->update(['view_permission' => 'group']);
        $group = DB::table('groups')->insertGetId(['name' => 'CRM041 test group', 'created_at' => now(), 'updated_at' => now()]);
        $owner = DB::table('persons')->whereNotNull('user_id')->value('user_id');
        DB::table('user_groups')->insert([['user_id' => $this->user->id, 'group_id' => $group], ['user_id' => $owner, 'group_id' => $group]]);
        $expected = app(PanelRepository::class)->visiblePredictionCount([$owner, $this->user->id]);
        $this->actingAs($this->user, 'user')->get($this->url)->assertOk()->assertViewHas('visiblePredictions', $expected);
    }

    // allows global scope and all-permission roles
    public function test_case8(): void
    {
        $this->user->role->update(['permission_type' => 'all']);
        $this->user->update(['view_permission' => 'global']);
        $expected = app(PanelRepository::class)->visiblePredictionCount(null);
        $this->actingAs($this->user, 'user')->get($this->url)->assertOk()->assertViewHas('visiblePredictions', $expected);
    }

    // registers separate ACL capabilities and protects the update endpoint
    public function test_case9(): void
    {
        expect(acl()->getRoles()['admin.churn.index'])->toBe('churn')
            ->and(collect(config('acl'))->pluck('key')->contains('churn.update'))->toBeTrue()
            ->and(Route::has('admin.churn.update'))->toBeTrue();
        $this->actingAs($this->user, 'user')->post(route('admin.churn.update', [], false), ['scope' => 'visible', 'as_of' => '2018-06-30'])->assertStatus(401);
        $this->actingAs($this->user, 'user')->post($this->url)->assertStatus(405);
    }

    // uses the configured admin prefix
    public function test_case10(): void
    {
        config(['app.admin_path' => 'backoffice']);
        require base_path('packages/Webkul/Churn/src/Routes/admin-routes.php');
        $this->actingAs($this->user, 'user')->get('/backoffice/churn')->assertOk()->assertViewIs('churn::index');
    }
}
