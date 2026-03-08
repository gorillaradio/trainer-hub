<?php

use App\Http\Controllers\Tenant\DashboardController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

Route::middleware(['web', 'auth', InitializeTenancyByPath::class, 'tenant.access'])
    ->prefix('app/{tenant}')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('tenant.dashboard');
    });
