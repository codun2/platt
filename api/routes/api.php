<?php

use App\Http\Controllers\Api\App;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\Super;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:20,1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/register', [AuthController::class, 'register']);
});
Route::get('public/sectors', [PublicController::class, 'sectors']);
Route::get('public/plans', [PublicController::class, 'plans']);

/*
|--------------------------------------------------------------------------
| Any signed-in user
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant'])->prefix('auth')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::patch('profile', [AuthController::class, 'updateProfile']);
});

/*
|--------------------------------------------------------------------------
| Tenant app (scoped by tenant_id)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant-user', 'tenant'])->prefix('app')->group(function () {
    Route::get('dashboard', App\DashboardController::class);

    Route::get('company', [App\CompanyController::class, 'show'])->middleware('perm:settings.manage');
    Route::patch('company', [App\CompanyController::class, 'update'])->middleware('perm:settings.manage');

    Route::middleware('perm:users.manage')->group(function () {
        Route::get('users', [App\UserController::class, 'index']);
        Route::post('users', [App\UserController::class, 'store']);
        Route::match(['put', 'patch'], 'users/{id}', [App\UserController::class, 'update'])->whereNumber('id');
        Route::delete('users/{id}', [App\UserController::class, 'destroy'])->whereNumber('id');
    });

    Route::middleware('perm:roles.manage')->group(function () {
        Route::get('roles', [App\RoleController::class, 'index']);
        Route::get('permissions', [App\RoleController::class, 'permissions']);
        Route::post('roles', [App\RoleController::class, 'store']);
        Route::match(['put', 'patch'], 'roles/{id}', [App\RoleController::class, 'update'])->whereNumber('id');
        Route::delete('roles/{id}', [App\RoleController::class, 'destroy'])->whereNumber('id');
    });
});

/*
|--------------------------------------------------------------------------
| Platform super-admin
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'super'])->prefix('super')->group(function () {
    Route::get('stats', Super\StatsController::class);

    Route::get('tenants', [Super\TenantController::class, 'index']);
    Route::post('tenants', [Super\TenantController::class, 'store']);
    Route::get('tenants/{id}', [Super\TenantController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], 'tenants/{id}', [Super\TenantController::class, 'update'])->whereNumber('id');
    Route::post('tenants/{id}/modules', [Super\TenantController::class, 'toggleModule'])->whereNumber('id');
    Route::post('tenants/{id}/apply-preset', [Super\TenantController::class, 'applyPreset'])->whereNumber('id');
    Route::post('tenants/{id}/renew', [Super\TenantController::class, 'renew'])->whereNumber('id');
    Route::post('tenants/{id}/reset-owner-password', [Super\TenantController::class, 'resetOwnerPassword'])->whereNumber('id');

    Route::get('modules', [Super\ModuleController::class, 'index']);
    Route::post('modules', [Super\ModuleController::class, 'store']);
    Route::match(['put', 'patch'], 'modules/{id}', [Super\ModuleController::class, 'update'])->whereNumber('id');
    Route::delete('modules/{id}', [Super\ModuleController::class, 'destroy'])->whereNumber('id');

    Route::get('presets', [Super\PresetController::class, 'index']);
    Route::post('presets', [Super\PresetController::class, 'store']);
    Route::match(['put', 'patch'], 'presets/{id}', [Super\PresetController::class, 'update'])->whereNumber('id');
    Route::delete('presets/{id}', [Super\PresetController::class, 'destroy'])->whereNumber('id');

    Route::get('plans', [Super\PlanController::class, 'index']);
    Route::post('plans', [Super\PlanController::class, 'store']);
    Route::match(['put', 'patch'], 'plans/{id}', [Super\PlanController::class, 'update'])->whereNumber('id');
    Route::delete('plans/{id}', [Super\PlanController::class, 'destroy'])->whereNumber('id');

    Route::get('subscriptions', [Super\SubscriptionController::class, 'index']);
    Route::get('subscriptions/history', [Super\SubscriptionController::class, 'history']);
    Route::post('subscriptions/expire-overdue', [Super\SubscriptionController::class, 'expireOverdue']);
});
