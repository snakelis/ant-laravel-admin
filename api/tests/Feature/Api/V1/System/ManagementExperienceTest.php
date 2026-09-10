<?php

namespace Tests\Feature\Api\V1\System;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ManagementExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function actor(array $names): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = User::factory()->create();
        foreach ($names as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_options_require_authentication_and_matching_management_permission(): void
    {
        foreach (['user-roles', 'role-permissions', 'menu-permissions'] as $kind) {
            $this->getJson('/api/v1/management-options/'.$kind)->assertUnauthorized();
        }
        $this->actor(['system.user.view']);
        foreach (['user-roles', 'role-permissions', 'menu-permissions'] as $kind) {
            $this->getJson('/api/v1/management-options/'.$kind)->assertForbidden();
        }
    }

    public function test_role_options_are_complete_and_respect_grant_boundaries_without_role_list_access(): void
    {
        $this->actor(['system.user.update']);
        for ($i = 0; $i < 105; $i++) {
            Role::create(['name' => 'ordinary-'.$i, 'guard_name' => 'web']);
        }
        Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
        $privileged = Role::create(['name' => 'privileged', 'guard_name' => 'web']);
        $privileged->givePermissionTo(Permission::findOrCreate('extension.report.view', 'web'));
        $this->getJson('/api/v1/management-options/user-roles')->assertOk()->assertJsonCount(105, 'data');
        $this->getJson('/api/v1/roles')->assertForbidden();
    }

    public function test_permission_options_do_not_require_permission_management_access_and_exceed_old_limit(): void
    {
        $this->actor(['system.role.update', 'system.menu.create']);
        for ($i = 0; $i < 205; $i++) {
            Permission::create(['name' => 'extension.resource'.$i.'.view', 'guard_name' => 'web', 'action_code' => 'view']);
        }
        Permission::create(['name' => 'extension.report.delete', 'guard_name' => 'web', 'action_code' => 'delete']);
        Permission::create(['name' => 'extension.disabled.view', 'guard_name' => 'web', 'action_code' => 'view', 'is_enabled' => false]);
        $this->getJson('/api/v1/management-options/menu-permissions')->assertOk()->assertJsonCount(205, 'data');
        $this->getJson('/api/v1/management-options/role-permissions')->assertOk()->assertJsonStructure(['data' => [['key', 'children']]]);
        $this->getJson('/api/v1/permissions')->assertForbidden();
    }

    public function test_capabilities_explain_protection_and_permission_filters_work(): void
    {
        $actor = $this->actor(['system.user.view', 'system.user.update', 'system.user.delete', 'system.role.view', 'system.role.update', 'system.role.delete', 'system.permission.view', 'system.permission.update', 'system.permission.delete']);
        $role = Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
        $this->getJson('/api/v1/roles/'.$role->id)->assertOk()->assertJsonPath('data.capabilities.update', false)->assertJsonPath('data.capabilities.delete', false);
        $this->getJson('/api/v1/users/'.$actor->id)->assertOk()->assertJsonPath('data.capabilities.delete', false);
        $permission = Permission::create(['name' => 'extension.report.view', 'guard_name' => 'web', 'module_code' => 'extension', 'resource_code' => 'report', 'is_enabled' => false]);
        $role->givePermissionTo($permission);
        $this->getJson('/api/v1/permissions?module_code=extension&resource_code=report&is_enabled=0&is_system=0')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $permission->id)
            ->assertJsonPath('data.0.capabilities.rename', false)->assertJsonPath('data.0.capabilities.delete', false);
        $this->getJson('/api/v1/permissions?is_enabled=unexpected')->assertUnprocessable();
        $this->getJson('/api/v1/permissions?module_code[]=invalid')->assertUnprocessable();
    }
}
