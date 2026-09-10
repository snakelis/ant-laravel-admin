<?php

use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\System\ManagementOptionsController;
use App\Http\Controllers\Api\V1\System\MenuController;
use App\Http\Controllers\Api\V1\System\PermissionController;
use App\Http\Controllers\Api\V1\System\RoleController;
use App\Http\Controllers\Api\V1\System\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('health', HealthController::class)->name('health');
        Route::post('auth/login', [AuthenticatedSessionController::class, 'store'])
            ->middleware('throttle:login')->name('auth.login');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::prefix('business')->name('business.')->group(base_path('routes/business.php'));

            Route::get('me', CurrentUserController::class)->name('me');
            Route::post('auth/logout', [AuthenticatedSessionController::class, 'destroy'])->name('auth.logout');

            Route::get('management-options/{kind}', ManagementOptionsController::class)
                ->whereIn('kind', ['user-roles', 'role-permissions', 'menu-permissions', 'user-password-policy'])->name('management-options');

            Route::apiResource('users', UserController::class)
                ->middlewareFor('index', 'can:system.user.view')
                ->middlewareFor('store', 'can:system.user.create')
                ->middlewareFor('show', ['can:system.user.view', 'can:view,user'])
                ->middlewareFor('update', ['can:system.user.update', 'can:update,user'])
                ->middlewareFor('destroy', ['can:system.user.delete', 'can:delete,user']);

            Route::apiResource('roles', RoleController::class)
                ->middlewareFor('index', 'can:system.role.view')
                ->middlewareFor('store', 'can:system.role.create')
                ->middlewareFor('show', ['can:system.role.view', 'can:view,role'])
                ->middlewareFor('update', ['can:system.role.update', 'can:update,role'])
                ->middlewareFor('destroy', ['can:system.role.delete', 'can:delete,role']);

            Route::apiResource('permissions', PermissionController::class)
                ->middlewareFor('index', 'can:system.permission.view')
                ->middlewareFor('store', 'can:system.permission.create')
                ->middlewareFor('show', ['can:system.permission.view', 'can:view,permission'])
                ->middlewareFor('update', ['can:system.permission.update', 'can:update,permission'])
                ->middlewareFor('destroy', ['can:system.permission.delete', 'can:delete,permission']);

            Route::apiResource('menus', MenuController::class)
                ->middlewareFor('index', 'can:system.menu.view')
                ->middlewareFor('store', 'can:system.menu.create')
                ->middlewareFor('show', ['can:system.menu.view', 'can:view,menu'])
                ->middlewareFor('update', ['can:system.menu.update', 'can:update,menu'])
                ->middlewareFor('destroy', ['can:system.menu.delete', 'can:delete,menu']);
        });
    });
