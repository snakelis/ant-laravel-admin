<?php

namespace Tests\Feature\Api\V1\System;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RbacManagementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_role_management_assigns_permissions(): void
    {
        $actor = $this->actingUserWithPermissions([
            'system.role.view',
            'system.role.create',
            'system.role.update',
            'system.role.delete',
            'system.user.view',
        ]);
        $permission = Permission::query()->updateOrCreate(
            ['name' => 'system.user.view', 'guard_name' => 'web'],
            ['title' => '查看用户']
        );

        Sanctum::actingAs($actor);

        $roleId = $this->postJson('/api/v1/roles', [
            'name' => 'manager',
            'permission_ids' => [$permission->id],
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'manager')
            ->assertJsonPath('data.permissions.0.name', 'system.user.view')
            ->assertJsonPath('data.permissions.0.title', '查看用户')
            ->json('data.id');

        $this->patchJson("/api/v1/roles/{$roleId}", [
            'name' => 'manager-updated',
            'permission_ids' => [],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'manager-updated')
            ->assertJsonCount(0, 'data.permissions');

        $this->deleteJson("/api/v1/roles/{$roleId}")
            ->assertOk();
    }

    public function test_role_name_only_update_rejects_unmanageable_target_and_keeps_database_unchanged(): void
    {
        $actor = $this->actingUserWithPermissions(['system.role.update']);
        $unmanageable = Permission::query()->firstOrCreate(['name' => 'system.user.delete', 'guard_name' => 'web']);
        $role = Role::query()->create(['name' => 'privileged-manager', 'guard_name' => 'web']);
        $role->syncPermissions([$unmanageable]);

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/roles/{$role->id}", ['name' => 'tampered-manager'])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $role->refresh();
        $this->assertSame('privileged-manager', $role->name);
        $this->assertSame(
            [$unmanageable->id],
            $role->permissions()->pluck('permissions.id')->all()
        );
    }

    public function test_role_delete_rejects_unmanageable_target_and_preserves_assignments(): void
    {
        $actor = $this->actingUserWithPermissions(['system.role.delete']);
        $unmanageable = Permission::query()->firstOrCreate(['name' => 'system.user.delete', 'guard_name' => 'web']);
        $role = Role::query()->create(['name' => 'privileged-manager', 'guard_name' => 'web']);
        $role->syncPermissions([$unmanageable]);
        $assignedUser = User::factory()->create();
        $assignedUser->assignRole($role);

        Sanctum::actingAs($actor);

        $this->deleteJson("/api/v1/roles/{$role->id}")
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'privileged-manager']);
        $this->assertDatabaseHas('role_has_permissions', [
            'role_id' => $role->id,
            'permission_id' => $unmanageable->id,
        ]);
        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $role->id,
            'model_type' => $assignedUser->getMorphClass(),
            'model_id' => $assignedUser->id,
        ]);
    }

    public function test_grant_all_operator_can_update_and_delete_unmanageable_role(): void
    {
        $actor = $this->actingUserWithPermissions([
            'system.role.update',
            'system.role.delete',
            'system.permission.grant-all',
        ]);
        $currentPermission = Permission::query()->firstOrCreate(['name' => 'system.user.delete', 'guard_name' => 'web']);
        $replacementPermission = Permission::query()->firstOrCreate(['name' => 'system.menu.delete', 'guard_name' => 'web']);
        $role = Role::query()->create(['name' => 'privileged-manager', 'guard_name' => 'web']);
        $role->syncPermissions([$currentPermission]);
        $assignedUser = User::factory()->create();
        $assignedUser->assignRole($role);

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/roles/{$role->id}", ['name' => 'privileged-manager-renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'privileged-manager-renamed')
            ->assertJsonPath('data.permissions.0.name', 'system.user.delete');

        $this->patchJson("/api/v1/roles/{$role->id}", ['permission_ids' => [$replacementPermission->id]])
            ->assertOk()
            ->assertJsonPath('data.permissions.0.name', 'system.menu.delete');

        $this->deleteJson("/api/v1/roles/{$role->id}")
            ->assertOk();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseMissing('role_has_permissions', ['role_id' => $role->id]);
        $this->assertDatabaseMissing('model_has_roles', ['role_id' => $role->id]);
    }

    public function test_regular_operator_can_update_and_delete_manageable_role(): void
    {
        $actor = $this->actingUserWithPermissions([
            'system.role.update',
            'system.role.delete',
            'system.user.view',
        ]);
        $manageable = Permission::query()->firstOrCreate(['name' => 'system.user.view', 'guard_name' => 'web']);
        $role = Role::query()->create(['name' => 'manageable-role', 'guard_name' => 'web']);
        $role->syncPermissions([$manageable]);
        $assignedUser = User::factory()->create();
        $assignedUser->assignRole($role);

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/roles/{$role->id}", ['name' => 'manageable-role-renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'manageable-role-renamed')
            ->assertJsonPath('data.permissions.0.name', 'system.user.view');

        $this->deleteJson("/api/v1/roles/{$role->id}")
            ->assertOk();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseMissing('role_has_permissions', ['role_id' => $role->id]);
        $this->assertDatabaseMissing('model_has_roles', ['role_id' => $role->id]);
    }

    public function test_role_update_rejects_removing_unmanageable_current_permission_and_rolls_back_name_change(): void
    {
        $actor = $this->actingUserWithPermissions(['system.role.update', 'system.user.view']);
        $manageable = Permission::query()->firstOrCreate(['name' => 'system.user.view', 'guard_name' => 'web']);
        $unmanageable = Permission::query()->firstOrCreate(['name' => 'system.user.delete', 'guard_name' => 'web']);
        $role = Role::query()->create(['name' => 'mixed-manager', 'guard_name' => 'web']);
        $role->syncPermissions([$manageable, $unmanageable]);

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/roles/{$role->id}", [
            'name' => 'tampered-manager',
            'permission_ids' => [],
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $role->refresh();
        $this->assertSame('mixed-manager', $role->name);
        $this->assertEqualsCanonicalizing(
            [$manageable->id, $unmanageable->id],
            $role->permissions()->pluck('permissions.id')->all()
        );
    }

    public function test_grant_all_operator_can_replace_unmanageable_current_permissions(): void
    {
        $actor = $this->actingUserWithPermissions(['system.role.update', 'system.permission.grant-all']);
        $permission = Permission::query()->firstOrCreate(['name' => 'system.user.delete', 'guard_name' => 'web']);
        $role = Role::query()->create(['name' => 'privileged-manager', 'guard_name' => 'web']);
        $role->syncPermissions([$permission]);

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/roles/{$role->id}", ['permission_ids' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data.permissions');

        $this->assertCount(0, $role->refresh()->permissions);
    }

    public function test_policy_protects_seeded_role_from_deletion(): void
    {
        $actor = $this->actingUserWithPermissions(['system.role.delete']);
        $role = Role::query()->create(['name' => 'super-admin', 'guard_name' => 'web']);

        Sanctum::actingAs($actor);

        $this->deleteJson("/api/v1/roles/{$role->id}")
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_permission_management_protects_seeded_system_permissions(): void
    {
        $actor = $this->actingUserWithPermissions([
            'system.permission.view',
            'system.permission.create',
            'system.permission.update',
            'system.permission.delete',
        ]);
        $systemPermission = Permission::query()->create(['name' => 'system.user.view', 'guard_name' => 'web']);

        Sanctum::actingAs($actor);

        $customId = $this->postJson('/api/v1/permissions', ['name' => 'custom.report.view'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'custom.report.view')
            ->assertJsonPath('data.module_code', 'custom')
            ->assertJsonPath('data.resource_code', 'report')
            ->assertJsonPath('data.action_code', 'view')
            ->json('data.id');

        $this->patchJson("/api/v1/permissions/{$customId}", [
            'name' => 'custom.report.update',
            'title' => '更新报表',
            'is_enabled' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'custom.report.update')
            ->assertJsonPath('data.title', '更新报表')
            ->assertJsonPath('data.is_enabled', false);

        $this->deleteJson("/api/v1/permissions/{$systemPermission->id}")
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_permission_api_rejects_reserved_system_namespace_for_create_and_custom_rename(): void
    {
        $actor = $this->actingUserWithPermissions([
            'system.permission.create',
            'system.permission.update',
        ]);
        $custom = Permission::query()->create(['name' => 'custom.report.view', 'guard_name' => 'web']);

        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/permissions', ['name' => 'system.fake.view'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonValidationErrors(['name']);

        $this->patchJson("/api/v1/permissions/{$custom->id}", ['name' => 'system.fake.update'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseHas('permissions', ['id' => $custom->id, 'name' => 'custom.report.view']);
        $this->assertDatabaseMissing('permissions', ['name' => 'system.fake.view']);
        $this->assertDatabaseMissing('permissions', ['name' => 'system.fake.update']);
    }

    public function test_system_permission_update_requires_grant_all_and_cannot_change_identity_or_disable(): void
    {
        $systemPermission = Permission::query()->create([
            'name' => 'system.user.view',
            'guard_name' => 'web',
            'title' => '查看用户',
            'module_code' => 'system',
            'module_title' => '系统管理',
            'resource_code' => 'user',
            'resource_title' => '用户管理',
            'action_code' => 'view',
            'action_title' => '查看用户',
            'is_system' => true,
            'is_enabled' => true,
        ]);
        $ordinaryActor = $this->actingUserWithPermissions(['system.permission.update']);

        Sanctum::actingAs($ordinaryActor);

        $this->patchJson("/api/v1/permissions/{$systemPermission->id}", ['title' => '普通修改'])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $grantAllActor = $this->actingUserWithPermissions([
            'system.permission.update',
            'system.permission.grant-all',
            'system.user.view',
        ]);

        Sanctum::actingAs($grantAllActor);

        $this->patchJson("/api/v1/permissions/{$systemPermission->id}", [
            'title' => '用户查看权限',
            'module_code' => 'custom',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', '用户查看权限')
            ->assertJsonPath('data.module_code', 'system');

        $this->patchJson("/api/v1/permissions/{$systemPermission->id}", ['name' => 'custom.user.view'])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        foreach ([false, 0, '0'] as $disabledValue) {
            $this->patchJson("/api/v1/permissions/{$systemPermission->id}", ['is_enabled' => $disabledValue])
                ->assertForbidden()
                ->assertJsonPath('code', 'forbidden');

            $this->assertTrue($systemPermission->refresh()->is_enabled);
        }

        $systemPermission->refresh();
        $this->assertSame('system.user.view', $systemPermission->name);
        $this->assertSame('system', $systemPermission->module_code);
        $this->assertTrue($systemPermission->is_enabled);
        $this->assertTrue($grantAllActor->fresh()->can('system.permission.update'));
        $this->assertTrue($grantAllActor->can('system.permission.grant-all'));
        $this->assertTrue($grantAllActor->can('system.user.view'));
    }

    public function test_permission_list_returns_metadata_and_tree(): void
    {
        $actor = $this->actingUserWithPermissions(['system.permission.view']);
        Permission::query()->updateOrCreate(
            ['name' => 'system.menu.view', 'guard_name' => 'web'],
            [
                'title' => '查看菜单',
                'module_code' => 'system',
                'module_title' => '系统管理',
                'resource_code' => 'menu',
                'resource_title' => '菜单管理',
                'action_code' => 'view',
                'action_title' => '查看菜单',
                'description' => '查看菜单列表和树。',
                'sort' => 40,
                'is_system' => true,
                'is_enabled' => true,
            ]
        );
        Permission::query()->updateOrCreate(
            ['name' => 'system.menu.create', 'guard_name' => 'web'],
            [
                'title' => '创建菜单',
                'module_code' => 'system',
                'module_title' => '系统管理',
                'resource_code' => 'menu',
                'resource_title' => '菜单管理',
                'action_code' => 'create',
                'action_title' => '创建菜单',
                'sort' => 41,
                'is_system' => true,
                'is_enabled' => true,
            ]
        );

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/permissions?search=menu&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'system.menu.view')
            ->assertJsonPath('data.0.title', '查看菜单')
            ->assertJsonPath('data.0.module_code', 'system')
            ->assertJsonPath('data.0.module_title', '系统管理')
            ->assertJsonPath('data.0.resource_code', 'menu')
            ->assertJsonPath('data.0.resource_title', '菜单管理')
            ->assertJsonPath('data.0.action_code', 'view')
            ->assertJsonPath('data.0.action_title', '查看菜单')
            ->assertJsonPath('data.0.is_system', true)
            ->assertJsonPath('data.0.is_enabled', true);

        $this->getJson('/api/v1/permissions?tree=1&search=menu')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'system')
            ->assertJsonPath('data.0.type', 'module')
            ->assertJsonPath('data.0.title', '系统管理')
            ->assertJsonPath('data.0.children.0.key', 'system.menu')
            ->assertJsonPath('data.0.children.0.type', 'resource')
            ->assertJsonPath('data.0.children.0.title', '菜单管理')
            ->assertJsonPath('data.0.children.0.children.0.key', 'system.menu.view')
            ->assertJsonPath('data.0.children.0.children.0.name', 'system.menu.view')
            ->assertJsonPath('data.0.children.0.children.0.title', '查看菜单')
            ->assertJsonPath('data.0.children.0.children.0.action_code', 'view');
    }

    public function test_role_permission_sync_rejects_over_grant_and_keeps_database_unchanged(): void
    {
        $actor = $this->actingUserWithPermissions([
            'system.role.create',
            'system.role.update',
            'system.user.view',
        ]);
        $allowed = Permission::query()->firstOrCreate(['name' => 'system.user.view', 'guard_name' => 'web']);
        $forbidden = Permission::query()->firstOrCreate(['name' => 'system.user.delete', 'guard_name' => 'web']);
        $role = Role::query()->create(['name' => 'limited', 'guard_name' => 'web']);
        $role->syncPermissions([$allowed]);

        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/roles', [
            'name' => 'bad-manager',
            'permission_ids' => [$allowed->id, $forbidden->id],
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->assertDatabaseMissing('roles', ['name' => 'bad-manager']);

        $this->patchJson("/api/v1/roles/{$role->id}", [
            'permission_ids' => [$allowed->id, $forbidden->id],
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $role->refresh();
        $this->assertSame(['system.user.view'], $role->permissions()->pluck('name')->sort()->values()->all());
    }

    public function test_grant_all_permission_can_assign_permissions_actor_does_not_have(): void
    {
        $actor = $this->actingUserWithPermissions([
            'system.role.create',
            'system.permission.grant-all',
        ]);
        $permission = Permission::query()->firstOrCreate(['name' => 'system.user.delete', 'guard_name' => 'web']);

        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/roles', [
            'name' => 'grant-all-manager',
            'permission_ids' => [$permission->id],
        ])
            ->assertCreated()
            ->assertJsonPath('data.permissions.0.name', 'system.user.delete');
    }

    public function test_referenced_and_assigned_custom_permissions_cannot_be_renamed_or_deleted(): void
    {
        $actor = $this->actingUserWithPermissions([
            'system.permission.update',
            'system.permission.delete',
            'custom.report.view',
            'custom.audit.view',
        ]);
        $menuPermission = Permission::query()->firstOrCreate(['name' => 'custom.report.view', 'guard_name' => 'web']);
        $assignedPermission = Permission::query()->firstOrCreate(['name' => 'custom.audit.view', 'guard_name' => 'web']);
        DB::table('menus')->insert([
            'title' => '报表',
            'permission_name' => 'custom.report.view',
            'sort' => 1,
            'is_visible' => true,
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $role = Role::query()->create(['name' => 'auditor', 'guard_name' => 'web']);
        $role->syncPermissions([$assignedPermission]);

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/permissions/{$menuPermission->id}", ['name' => 'custom.report.updated'])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->deleteJson("/api/v1/permissions/{$menuPermission->id}")
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->patchJson("/api/v1/permissions/{$assignedPermission->id}", ['name' => 'custom.audit.updated'])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->deleteJson("/api/v1/permissions/{$assignedPermission->id}")
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->assertDatabaseHas('menus', ['permission_name' => 'custom.report.view']);
        $this->assertDatabaseHas('permissions', ['name' => 'custom.audit.view']);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function actingUserWithPermissions(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}
