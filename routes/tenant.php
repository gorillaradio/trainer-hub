<?php

use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\StudentController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

Route::middleware(['web', 'auth', InitializeTenancyByPath::class, 'tenant.access'])
    ->prefix('app/{tenant}')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('tenant.dashboard');

        Route::resource('students', StudentController::class)
            ->names('tenant.students');

        Route::put('students/{student}/suspend', [StudentController::class, 'suspend'])
            ->name('tenant.students.suspend');
        Route::put('students/{student}/archive', [StudentController::class, 'archive'])
            ->name('tenant.students.archive');
    });
