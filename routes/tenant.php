<?php

use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\GroupController;
use App\Http\Controllers\Tenant\StudentController;
use App\Http\Controllers\Tenant\StudentGroupController;
use App\Http\Controllers\Tenant\StudentPaymentController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

Route::middleware(['web', 'auth', InitializeTenancyByPath::class, 'tenant.access'])
    ->prefix('app/{tenant}')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('tenant.dashboard');

        Route::get('students/search', [StudentController::class, 'search'])
            ->name('tenant.students.search');

        Route::resource('students', StudentController::class)
            ->names('tenant.students');

        Route::put('students/{student}/suspend', [StudentController::class, 'suspend'])
            ->name('tenant.students.suspend');
        Route::put('students/{student}/archive', [StudentController::class, 'archive'])
            ->name('tenant.students.archive');
        Route::put('students/{student}/reactivate', [StudentController::class, 'reactivate'])
            ->name('tenant.students.reactivate');

        Route::resource('groups', GroupController::class)
            ->names('tenant.groups');

        Route::post('students/{student}/groups', [StudentGroupController::class, 'attach'])
            ->name('tenant.students.groups.attach');
        Route::delete('students/{student}/groups/{group}', [StudentGroupController::class, 'detach'])
            ->name('tenant.students.groups.detach');

        Route::get('students/{student}/payment-data', [StudentPaymentController::class, 'paymentData'])
            ->name('tenant.students.payment-data');
        Route::post('students/{student}/payments/monthly', [StudentPaymentController::class, 'registerMonthly'])
            ->name('tenant.students.payments.monthly');
        Route::post('students/{student}/payments/enrollment', [StudentPaymentController::class, 'registerEnrollment'])
            ->name('tenant.students.payments.enrollment');
    });
