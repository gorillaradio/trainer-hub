<?php

use App\Enums\StudentStatus;
use App\Models\Group;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->tenant = Tenant::factory()->create(['owner_id' => $this->user->id]);
    $this->user->update(['current_tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);

    // Inizializza tenancy per i test
    tenancy()->initialize($this->tenant);
});

afterEach(function () {
    tenancy()->end();
});

test('search restituisce solo studenti attivi', function () {
    Student::factory()->create(['first_name' => 'Marco', 'status' => StudentStatus::Active]);
    Student::factory()->inactive()->create(['first_name' => 'Luca']);
    Student::factory()->suspended()->create(['first_name' => 'Anna']);

    $response = $this->getJson("/app/{$this->tenant->slug}/students/search");

    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonFragment(['first_name' => 'Marco']);
});

test('search filtra per nome', function () {
    Student::factory()->create(['first_name' => 'Marco', 'last_name' => 'Rossi']);
    Student::factory()->create(['first_name' => 'Luca', 'last_name' => 'Bianchi']);
    Student::factory()->create(['first_name' => 'Anna', 'last_name' => 'Marchetti']);

    $response = $this->getJson("/app/{$this->tenant->slug}/students/search?q=Marc");

    $response->assertOk();
    $response->assertJsonCount(2); // Marco Rossi + Anna Marchetti (last_name matches too)
    $response->assertJsonFragment(['first_name' => 'Marco']);
    $response->assertJsonFragment(['first_name' => 'Anna']);
});

test('search filtra per cognome', function () {
    Student::factory()->create(['first_name' => 'Marco', 'last_name' => 'Rossi']);
    Student::factory()->create(['first_name' => 'Luca', 'last_name' => 'Bianchi']);

    $response = $this->getJson("/app/{$this->tenant->slug}/students/search?q=Rossi");

    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonFragment(['last_name' => 'Rossi']);
});

test('search con exclude_group esclude studenti già nel gruppo', function () {
    $group = Group::factory()->create();
    $studentInGroup = Student::factory()->create(['first_name' => 'Marco']);
    $studentNotInGroup = Student::factory()->create(['first_name' => 'Luca']);

    $group->students()->attach($studentInGroup->id);

    $response = $this->getJson("/app/{$this->tenant->slug}/students/search?exclude_group={$group->id}");

    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonFragment(['first_name' => 'Luca']);
    $response->assertJsonMissing(['first_name' => 'Marco']);
});

test('search limita a 10 risultati', function () {
    Student::factory()->count(15)->create();

    $response = $this->getJson("/app/{$this->tenant->slug}/students/search");

    $response->assertOk();
    $response->assertJsonCount(10);
});

test('search rispetta isolamento tenant', function () {
    $otherUser = User::factory()->create();
    $otherTenant = Tenant::factory()->create(['owner_id' => $otherUser->id]);

    $response = $this->getJson("/app/{$otherTenant->slug}/students/search");

    $response->assertForbidden();
});

test('search richiede autenticazione', function () {
    auth()->logout();

    $response = $this->getJson("/app/{$this->tenant->slug}/students/search");

    $response->assertUnauthorized();
});

test('search senza query restituisce tutti gli studenti attivi', function () {
    Student::factory()->count(3)->create(['status' => StudentStatus::Active]);

    $response = $this->getJson("/app/{$this->tenant->slug}/students/search");

    $response->assertOk();
    $response->assertJsonCount(3);
});

test('search restituisce solo id, first_name e last_name', function () {
    Student::factory()->create([
        'first_name' => 'Marco',
        'last_name' => 'Rossi',
        'email' => 'marco@example.com',
    ]);

    $response = $this->getJson("/app/{$this->tenant->slug}/students/search");

    $response->assertOk();
    $student = $response->json()[0];
    expect($student)->toHaveKeys(['id', 'first_name', 'last_name']);
    expect($student)->not->toHaveKey('email');
});

test('search ordina per cognome e nome', function () {
    Student::factory()->create(['first_name' => 'Zara', 'last_name' => 'Bianchi']);
    Student::factory()->create(['first_name' => 'Anna', 'last_name' => 'Bianchi']);
    Student::factory()->create(['first_name' => 'Marco', 'last_name' => 'Rossi']);

    $response = $this->getJson("/app/{$this->tenant->slug}/students/search");

    $response->assertOk();
    $names = collect($response->json())->pluck('first_name')->all();
    expect($names)->toBe(['Anna', 'Zara', 'Marco']);
});
