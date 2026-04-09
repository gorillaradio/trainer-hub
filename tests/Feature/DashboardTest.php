<?php

use App\Models\Tenant;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('user with valid current_tenant_id is redirected to tenant dashboard', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['owner_id' => $user->id]);
    $user->update(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('tenant.dashboard', $tenant->slug));
});

test('user with stale current_tenant_id but existing tenant is redirected and repaired', function () {
    $user = User::factory()->create(['current_tenant_id' => null]);
    $tenant = Tenant::factory()->create(['owner_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('tenant.dashboard', $tenant->slug));
    expect($user->fresh()->current_tenant_id)->toBe($tenant->id);
});

test('user with null current_tenant_id falls back to owned tenant', function () {
    $user = User::factory()->create(['current_tenant_id' => null]);
    $tenant = Tenant::factory()->create(['owner_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('tenant.dashboard', $tenant->slug));
    expect($user->fresh()->current_tenant_id)->toBe($tenant->id);
});

test('user with no tenant is redirected to onboarding', function () {
    $user = User::factory()->create(['current_tenant_id' => null]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('onboarding.create'));
});
