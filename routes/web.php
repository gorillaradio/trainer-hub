<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = auth()->user();

        if ($user->current_tenant_id && $user->currentTenant) {
            return redirect()->route('tenant.dashboard', $user->currentTenant->slug);
        }

        return redirect()->route('onboarding.create');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
