<?php

namespace Tests\Feature\Console;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_preserves_existing_configuration_and_business_grants(): void
    {
        $this->seed(DatabaseSeeder::class);
        $menu = Menu::where('name', 'system.users')->firstOrFail();
        $menu->update(['title' => '账号中心', 'sort' => 91, 'is_visible' => false, 'is_enabled' => false]);
        $permission = Permission::where('name', 'system.user.view')->firstOrFail();
        $permission->update(['title' => '自定义标题', 'sort' => 92]);
        $businessPermission = Permission::create(['name' => 'business.sample.view', 'guard_name' => 'web']);
        $role = Role::findByName('super-admin', 'web');
        $role->givePermissionTo($businessPermission);
        $ordinary = Role::create(['name' => 'business-editor', 'guard_name' => 'web']);
        $ordinary->givePermissionTo($businessPermission);
        $user = User::factory()->create();
        $user->assignRole($ordinary);
        $businessMenu = Menu::create(['name' => 'business.sample', 'title' => '业务入口', 'component' => 'RouteView']);
        // 模拟较旧版本没有的新默认数据：升级应补齐，重复执行不能产生重复记录。
        Menu::where('name', 'system.roles')->delete();
        $this->artisan('foundation:sync')->assertSuccessful();
        $this->artisan('foundation:sync')->assertSuccessful();
        $this->assertSame('账号中心', $menu->refresh()->title);
        $this->assertSame(91, $menu->sort);
        $this->assertFalse($menu->is_visible);
        $this->assertFalse($menu->is_enabled);
        $this->assertSame('自定义标题', $permission->refresh()->title);
        $this->assertSame(92, $permission->sort);
        $this->assertTrue($role->fresh()->permissions->contains('id', $businessPermission->id));
        $this->assertSame([$businessPermission->id], $ordinary->fresh()->permissions->pluck('id')->all());
        $this->assertTrue($user->fresh()->hasRole($ordinary));
        $this->assertDatabaseHas('menus', ['id' => $businessMenu->id, 'title' => '业务入口']);
        $this->assertSame(1, Menu::where('name', 'system.roles')->count());
        $this->assertDatabaseCount('users', 1);
    }
}
