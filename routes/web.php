<?php

use App\Http\Controllers\Central\OnboardingController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = auth()->user();
        $tenant = $user->currentTenant ?? $user->tenants()->first();

        if ($tenant) {
            if ($user->current_tenant_id !== $tenant->id) {
                $user->update(['current_tenant_id' => $tenant->id]);
            }

            return redirect()->route('tenant.dashboard', $tenant->slug);
        }

        return redirect()->route('onboarding.create');
    })->name('dashboard');

    Route::get('/onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
});

require __DIR__.'/settings.php';
