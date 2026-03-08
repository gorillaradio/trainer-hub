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
            ->names([
                'index'   => 'tenant.students.index',
                'create'  => 'tenant.students.create',
                'store'   => 'tenant.students.store',
                'show'    => 'tenant.students.show',
                'edit'    => 'tenant.students.edit',
                'update'  => 'tenant.students.update',
                'destroy' => 'tenant.students.destroy',
            ]);
    });
