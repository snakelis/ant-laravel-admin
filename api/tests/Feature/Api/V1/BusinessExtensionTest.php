<?php

namespace Tests\Feature\Api\V1;

use App\Models\Permission;
use App\Models\User;
use App\Providers\BusinessServiceProvider;
use App\Support\Api\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessExtensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_provider_and_route_boundary_support_normal_laravel_extensions(): void
    {
        $this->assertNotNull($this->app->getProvider(BusinessServiceProvider::class));
        // 使用相同挂载上下文注册测试业务路由，不向框架加入演示业务。
        Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1/business')->name('api.v1.business.')
            ->group(function (): void {
                Route::get('extension-probe', fn () => ApiResponse::success(['available' => true]))
                    ->middleware('can:business.probe.view')->name('probe');
            });
        $this->getJson('/api/v1/business/extension-probe')->assertUnauthorized();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/business/extension-probe')->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('business.probe.view', 'web'));
        $this->getJson('/api/v1/business/extension-probe')->assertOk()->assertJsonPath('data.available', true);
    }
}
