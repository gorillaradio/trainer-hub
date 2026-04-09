<?php

use App\Models\Tenant;
use App\Models\User;

test('guests cannot access onboarding', function () {
    $this->get(route('onboarding.create'))->assertRedirect(route('login'));
    $this->post(route('onboarding.store'), ['name' => 'Test'])->assertRedirect(route('login'));
});

test('user who already owns a tenant is redirected from onboarding create', function () {
    $user = User::factory()->create(['current_tenant_id' => null]);
    $tenant = Tenant::factory()->create(['owner_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('onboarding.create'));

    $response->assertRedirect(route('tenant.dashboard', $tenant->slug));
    expect($user->fresh()->current_tenant_id)->toBe($tenant->id);
});

test('user who already owns a tenant cannot create another via store', function () {
    $user = User::factory()->create(['current_tenant_id' => null]);
    $tenant = Tenant::factory()->create(['owner_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('onboarding.store'), [
        'name' => 'Duplicate Gym',
    ]);

    $response->assertRedirect(route('tenant.dashboard', $tenant->slug));
    expect(Tenant::where('owner_id', $user->id)->count())->toBe(1);
});

test('user without tenant sees onboarding page', function () {
    $user = User::factory()->create(['current_tenant_id' => null]);

    $response = $this->actingAs($user)->get(route('onboarding.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Central/Onboarding/Create'));
});

test('user without tenant can create one via onboarding', function () {
    $user = User::factory()->create(['current_tenant_id' => null]);

    $response = $this->actingAs($user)->post(route('onboarding.store'), [
        'name' => 'Palestra Gorilla',
    ]);

    $tenant = Tenant::where('owner_id', $user->id)->first();
    expect($tenant)->not->toBeNull();
    expect($tenant->slug)->toBe('palestra-gorilla');
    expect($user->fresh()->current_tenant_id)->toBe($tenant->id);
    $response->assertRedirect(route('tenant.dashboard', $tenant->slug));
});

test('onboarding store requires a name', function () {
    $user = User::factory()->create(['current_tenant_id' => null]);

    $response = $this->actingAs($user)->post(route('onboarding.store'), [
        'name' => '',
    ]);

    $response->assertSessionHasErrors('name');
    expect(Tenant::where('owner_id', $user->id)->count())->toBe(0);
});

test('onboarding store rejects name longer than 255 characters', function () {
    $user = User::factory()->create(['current_tenant_id' => null]);

    $response = $this->actingAs($user)->post(route('onboarding.store'), [
        'name' => str_repeat('a', 256),
    ]);

    $response->assertSessionHasErrors('name');
});

test('onboarding create repairs null current_tenant_id when user owns a tenant', function () {
    $user = User::factory()->create(['current_tenant_id' => null]);
    $tenant = Tenant::factory()->create(['owner_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('onboarding.create'));

    $response->assertRedirect(route('tenant.dashboard', $tenant->slug));
    expect($user->fresh()->current_tenant_id)->toBe($tenant->id);
});
