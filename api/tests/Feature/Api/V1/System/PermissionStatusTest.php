<?php

namespace Tests\Feature\Api\V1\System;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Route::get('/api/v1/status-probe', fn () => ApiResponse::success())
            ->middleware(['api', 'auth:sanctum', 'can:extension.report.view']);
    }

    public function test_disabling_and_reenabling_permissions_updates_authorization_and_profile_without_removing_assignments(): void
    {
        $permission = Permission::query()->create(['name' => 'extension.report.view', 'guard_name' => 'web']);
        $adminPermission = Permission::query()->create(['name' => 'system.permission.update', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->givePermissionTo($adminPermission);
        $role = Role::query()->create(['name' => 'report-reader', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $direct = User::factory()->create();
        $direct->givePermissionTo($permission);
        $viaRole = User::factory()->create();
        $viaRole->assignRole($role);
        Menu::factory()->create(['permission_name' => $permission->name, 'is_enabled' => true, 'is_visible' => true]);

        $this->getJson('/api/v1/status-probe')->assertUnauthorized();
        foreach ([$direct, $viaRole] as $user) {
            $this->assertTrue($user->can($permission->name));
        }

        foreach ([false, true] as $enabled) {
            Sanctum::actingAs($admin);
            $this->patchJson("/api/v1/permissions/{$permission->id}", ['is_enabled' => $enabled])->assertOk();
            foreach ([$direct, $viaRole] as $user) {
                // 同一实例已预热过权限关系，仍应读取最新停用状态。
                $this->assertSame($enabled, $user->hasPermissionTo($permission));
                Sanctum::actingAs($user);
                $this->getJson('/api/v1/status-probe')->assertStatus($enabled ? 200 : 403);
                $this->getJson('/api/v1/me')->assertOk()
                    ->assertJsonCount($enabled ? 1 : 0, 'data.permissions')
                    ->assertJsonCount($enabled ? 1 : 0, 'data.menus');
            }
        }

        $this->assertDatabaseHas('role_has_permissions', ['role_id' => $role->id, 'permission_id' => $permission->id]);
        $this->assertDatabaseHas('model_has_permissions', ['model_id' => $direct->id, 'permission_id' => $permission->id]);
    }

    public function test_disabled_permissions_cannot_be_newly_granted_even_with_grant_all_but_existing_assignments_can_be_preserved_or_removed(): void
    {
        $actor = User::factory()->create();
        foreach (['system.role.create', 'system.role.update', 'system.permission.grant-all'] as $name) {
            $actor->givePermissionTo(Permission::query()->create(['name' => $name, 'guard_name' => 'web']));
        }
        $permission = Permission::query()->create(['name' => 'extension.report.view', 'guard_name' => 'web', 'is_enabled' => false]);
        $role = Role::query()->create(['name' => 'existing-role', 'guard_name' => 'web']);
        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/roles', ['name' => 'blocked-role', 'permission_ids' => [$permission->id]])->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name' => 'blocked-role']);
        $this->patchJson("/api/v1/roles/{$role->id}", ['name' => 'blocked-rename', 'permission_ids' => [$permission->id]])->assertForbidden();
        $this->assertSame('existing-role', $role->refresh()->name);

        $role->givePermissionTo($permission);
        $this->patchJson("/api/v1/roles/{$role->id}", ['permission_ids' => [$permission->id]])->assertOk();
        $this->patchJson("/api/v1/roles/{$role->id}", ['permission_ids' => []])->assertOk();
        $this->assertDatabaseMissing('role_has_permissions', ['role_id' => $role->id, 'permission_id' => $permission->id]);
    }
}
