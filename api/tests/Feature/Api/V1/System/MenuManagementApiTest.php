<?php

namespace Tests\Feature\Api\V1\System;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MenuManagementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_menu_management_returns_tree_and_prevents_cycles(): void
    {
        $actor = $this->actingUserWithPermissions([
            'system.menu.view',
            'system.menu.create',
            'system.menu.update',
            'system.menu.delete',
        ]);
        Permission::query()->create(['name' => 'system.user.view', 'guard_name' => 'web']);

        Sanctum::actingAs($actor);

        $parentId = $this->postJson('/api/v1/menus', [
            'title' => '系统管理',
            'name' => 'system',
            'path' => '/system',
            'component' => 'RouteView',
            'sort' => 10,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'system')
            ->json('data.id');

        $childId = $this->postJson('/api/v1/menus', [
            'parent_id' => $parentId,
            'title' => '用户管理',
            'name' => 'system.users',
            'path' => '/system/users',
            'component' => 'SystemUserList',
            'permission_name' => 'system.user.view',
            'sort' => 20,
        ])
            ->assertCreated()
            ->assertJsonPath('data.parent_id', $parentId)
            ->json('data.id');

        $this->getJson('/api/v1/menus?tree=1')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'system')
            ->assertJsonPath('data.0.children.0.name', 'system.users');

        $this->patchJson("/api/v1/menus/{$parentId}", ['parent_id' => $childId])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['parent_id']]);

        $this->deleteJson("/api/v1/menus/{$childId}")
            ->assertOk();
    }

    public function test_menu_component_identifier_is_validated(): void
    {
        $actor = $this->actingUserWithPermissions(['system.menu.create']);
        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/menus', [
            'title' => '危险组件',
            'component' => '../../Danger',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['component']]);
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
