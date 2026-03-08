<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function create()
    {
        if (auth()->user()->current_tenant_id) {
            return redirect()->route('tenant.dashboard', auth()->user()->currentTenant->slug);
        }

        return Inertia::render('central/onboarding/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $slug = Str::slug($validated['name']);

        $originalSlug = $slug;
        $counter = 1;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'owner_id' => auth()->id(),
        ]);

        auth()->user()->update(['current_tenant_id' => $tenant->id]);

        return redirect()->route('tenant.dashboard', $tenant->slug);
    }
}
