<?php

namespace Tests\Feature\Api\V1;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FoundationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_health_endpoint_uses_api_envelope(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'ok')
            ->assertJsonPath('data.service', 'ant-laravel-admin-api')
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_current_user_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_current_user_returns_permissions_and_authorized_menus(): void
    {
        $permission = Permission::query()->create(['name' => 'system.user.view', 'guard_name' => 'web']);
        $hiddenPermission = Permission::query()->create(['name' => 'system.role.view', 'guard_name' => 'web']);
        $parent = Menu::factory()->create(['name' => 'system', 'permission_name' => null]);
        Menu::factory()->create(['parent_id' => $parent->id, 'name' => 'system.users', 'permission_name' => $permission->name]);
        Menu::factory()->create(['parent_id' => $parent->id, 'name' => 'system.roles', 'permission_name' => $hiddenPermission->name]);

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.permissions.0', 'system.user.view')
            ->assertJsonPath('data.menus.0.name', 'system')
            ->assertJsonPath('data.menus.0.children.0.name', 'system.users')
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token')
            ->assertJsonMissing(['name' => 'system.roles']);
    }

    public function test_validation_errors_use_api_error_envelope(): void
    {
        Route::post('/api/v1/_test/validation', function (Request $request) {
            $request->validate([
                'name' => ['required', 'string'],
            ]);

            return ApiResponse::success();
        })->middleware('api');

        $this->postJson('/api/v1/_test/validation', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'errors' => ['name'],
            ]);
    }

    public function test_forbidden_errors_use_api_error_envelope(): void
    {
        Route::get('/api/v1/_test/authorization', fn () => ApiResponse::success())
            ->middleware(['api', 'auth:sanctum', 'can:system.foundation.view']);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/_test/authorization')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'forbidden');
    }
}
