<?php

namespace Tests\Feature\Api\V1\System;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserManagementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_user_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/users')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_user_index_requires_permission(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/users')
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_user_crud_uses_permissions_pagination_and_validation(): void
    {
        $actor = $this->actingUserWithPermissions([
            'system.user.view',
            'system.user.create',
            'system.user.update',
            'system.user.delete',
        ]);
        $role = Role::query()->create(['name' => 'operator', 'guard_name' => 'web']);
        $role->syncPermissions(Permission::query()->whereIn('name', [
            'system.user.view',
            'system.user.create',
            'system.user.update',
            'system.user.delete',
        ])->get());

        Sanctum::actingAs($actor);

        $createResponse = $this->postJson('/api/v1/users', [
            'name' => 'Operator',
            'email' => 'operator@example.com',
            'password' => 'Password123!',
            'role_ids' => [$role->id],
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'operator@example.com')
            ->assertJsonPath('data.roles.0.name', 'operator');

        $userId = $createResponse->json('data.id');

        $this->getJson('/api/v1/users?per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.per_page', 10)
            ->assertJsonPath('success', true);

        $this->patchJson("/api/v1/users/{$userId}", [
            'name' => 'Updated Operator',
            'role_ids' => [],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Operator')
            ->assertJsonCount(0, 'data.roles');

        $this->postJson('/api/v1/users', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['name', 'email', 'password']]);

        $this->deleteJson("/api/v1/users/{$userId}")
            ->assertOk()
            ->assertJsonPath('message', 'Deleted.');
    }

    public function test_policy_prevents_user_from_deleting_self(): void
    {
        $actor = $this->actingUserWithPermissions(['system.user.delete']);
        Sanctum::actingAs($actor);

        $this->deleteJson("/api/v1/users/{$actor->id}")
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_user_role_sync_rejects_unmanageable_role_and_keeps_database_unchanged(): void
    {
        $actor = $this->actingUserWithPermissions(['system.user.update', 'system.user.view']);
        $forbiddenPermission = Permission::query()->firstOrCreate(['name' => 'system.user.delete', 'guard_name' => 'web']);
        $role = Role::query()->create(['name' => 'dangerous', 'guard_name' => 'web']);
        $role->syncPermissions([$forbiddenPermission]);
        $target = User::factory()->create();

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/users/{$target->id}", ['role_ids' => [$role->id]])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->assertFalse($target->refresh()->hasRole($role));
    }

    public function test_user_role_sync_rejects_removing_unmanageable_current_role_and_rolls_back_profile_change(): void
    {
        $actor = $this->actingUserWithPermissions(['system.user.update', 'system.user.view']);
        $manageablePermission = Permission::query()->firstOrCreate(['name' => 'system.user.view', 'guard_name' => 'web']);
        $unmanageablePermission = Permission::query()->firstOrCreate(['name' => 'system.user.delete', 'guard_name' => 'web']);
        $manageableRole = Role::query()->create(['name' => 'viewer', 'guard_name' => 'web']);
        $manageableRole->syncPermissions([$manageablePermission]);
        $unmanageableRole = Role::query()->create(['name' => 'dangerous', 'guard_name' => 'web']);
        $unmanageableRole->syncPermissions([$unmanageablePermission]);
        $target = User::factory()->create(['name' => 'Original User']);
        $target->syncRoles([$manageableRole, $unmanageableRole]);

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/users/{$target->id}", [
            'name' => 'Tampered User',
            'role_ids' => [],
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $target->refresh();
        $this->assertSame('Original User', $target->name);
        $this->assertEqualsCanonicalizing(
            [$manageableRole->id, $unmanageableRole->id],
            $target->roles()->pluck('roles.id')->all()
        );
    }

    public function test_user_update_rejects_profile_and_password_changes_for_unmanageable_target_without_role_sync(): void
    {
        $actor = $this->actingUserWithPermissions(['system.user.update']);
        $unmanageablePermission = Permission::query()->firstOrCreate(['name' => 'system.user.delete', 'guard_name' => 'web']);
        $role = Role::query()->create(['name' => 'dangerous-profile-target', 'guard_name' => 'web']);
        $role->syncPermissions([$unmanageablePermission]);
        $target = User::factory()->create([
            'name' => 'Protected User',
            'email' => 'protected@example.com',
            'password' => 'original-password',
        ]);
        $target->assignRole($role);
        $originalPassword = $target->password;

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/users/{$target->id}", [
            'name' => 'Compromised User',
            'email' => 'compromised@example.com',
            'password' => 'Compromised-password1!',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $target->refresh();
        $this->assertSame('Protected User', $target->name);
        $this->assertSame('protected@example.com', $target->email);
        $this->assertSame($originalPassword, $target->password);
        $this->assertFalse(Hash::check('Compromised-password1!', $target->password));
        $this->assertTrue($target->hasRole($role));
    }

    public function test_user_update_rejects_super_admin_target_and_keeps_all_fields_and_roles_unchanged(): void
    {
        $actor = $this->actingUserWithPermissions(['system.user.update']);
        $superAdminRole = Role::query()->create(['name' => 'super-admin', 'guard_name' => 'web']);
        $target = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'super-admin@example.com',
            'password' => 'original-password',
        ]);
        $target->assignRole($superAdminRole);
        $originalPassword = $target->password;

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/users/{$target->id}", [
            'name' => 'Compromised Admin',
            'email' => 'compromised-admin@example.com',
            'password' => 'Compromised-password1!',
            'role_ids' => [],
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $target->refresh();
        $this->assertSame('Super Admin', $target->name);
        $this->assertSame('super-admin@example.com', $target->email);
        $this->assertSame($originalPassword, $target->password);
        $this->assertTrue($target->hasRole($superAdminRole));
    }

    public function test_user_delete_rejects_super_admin_and_unmanageable_targets_and_keeps_relations(): void
    {
        $actor = $this->actingUserWithPermissions(['system.user.delete']);
        $superAdminRole = Role::query()->create(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole($superAdminRole);

        $unmanageablePermission = Permission::query()->firstOrCreate(['name' => 'system.role.update', 'guard_name' => 'web']);
        $unmanageableRole = Role::query()->create(['name' => 'dangerous-delete-target', 'guard_name' => 'web']);
        $unmanageableRole->syncPermissions([$unmanageablePermission]);
        $unmanageableTarget = User::factory()->create();
        $unmanageableTarget->assignRole($unmanageableRole);
        $directPermissionTarget = User::factory()->create();
        $directPermissionTarget->givePermissionTo($unmanageablePermission);

        Sanctum::actingAs($actor);

        foreach ([$superAdmin, $unmanageableTarget, $directPermissionTarget] as $target) {
            $this->deleteJson("/api/v1/users/{$target->id}")
                ->assertForbidden()
                ->assertJsonPath('code', 'forbidden');

            $this->assertDatabaseHas('users', ['id' => $target->id]);
        }

        $this->assertTrue($superAdmin->refresh()->hasRole($superAdminRole));
        $this->assertTrue($unmanageableTarget->refresh()->hasRole($unmanageableRole));
        $this->assertTrue($directPermissionTarget->refresh()->hasDirectPermission($unmanageablePermission));
    }

    public function test_grant_all_operator_can_update_and_delete_protected_target(): void
    {
        $actor = $this->actingUserWithPermissions([
            'system.user.update',
            'system.user.delete',
            'system.permission.grant-all',
        ]);
        $superAdminRole = Role::query()->create(['name' => 'super-admin', 'guard_name' => 'web']);
        $target = User::factory()->create([
            'name' => 'Original Admin',
            'email' => 'original-admin@example.com',
        ]);
        $target->assignRole($superAdminRole);

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/users/{$target->id}", [
            'name' => 'Updated Admin',
            'email' => 'updated-admin@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Admin')
            ->assertJsonPath('data.email', 'updated-admin@example.com')
            ->assertJsonPath('data.roles.0.name', 'super-admin');

        $this->deleteJson("/api/v1/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Deleted.');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseMissing(config('permission.table_names.model_has_roles'), [
            config('permission.column_names.model_morph_key') => $target->id,
            'model_type' => $target->getMorphClass(),
        ]);
    }

    public function test_grant_all_operator_can_remove_unmanageable_current_role(): void
    {
        $actor = $this->actingUserWithPermissions(['system.user.update', 'system.permission.grant-all']);
        $permission = Permission::query()->firstOrCreate(['name' => 'system.user.delete', 'guard_name' => 'web']);
        $role = Role::query()->create(['name' => 'dangerous', 'guard_name' => 'web']);
        $role->syncPermissions([$permission]);
        $target = User::factory()->create();
        $target->assignRole($role);

        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/users/{$target->id}", ['role_ids' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data.roles');

        $this->assertCount(0, $target->refresh()->roles);
    }

    public function test_user_role_sync_rejects_protected_super_admin_role_without_grant_all(): void
    {
        $actor = $this->actingUserWithPermissions(['system.user.create']);
        $role = Role::query()->create(['name' => 'super-admin', 'guard_name' => 'web']);

        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/users', [
            'name' => 'Unsafe Admin',
            'email' => 'unsafe-admin@example.com',
            'password' => 'Password123!',
            'role_ids' => [$role->id],
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->assertDatabaseMissing('users', ['email' => 'unsafe-admin@example.com']);
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
