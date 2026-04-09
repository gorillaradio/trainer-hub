<?php

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

test('show mostra il gruppo con i suoi studenti', function () {
    $group = Group::factory()->create(['name' => 'Corso Avanzato']);
    $students = Student::factory()->count(3)->create();
    $group->students()->attach($students->pluck('id'));

    $response = $this->get("/app/{$this->tenant->slug}/groups/{$group->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/Group/Show')
        ->has('group')
        ->where('group.id', $group->id)
        ->where('group.name', 'Corso Avanzato')
        ->has('group.students', 3)
    );
});

test('show funziona con un gruppo senza studenti', function () {
    $group = Group::factory()->create();

    $response = $this->get("/app/{$this->tenant->slug}/groups/{$group->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/Group/Show')
        ->has('group')
        ->where('group.id', $group->id)
        ->has('group.students', 0)
    );
});

test('show ordina gli studenti per cognome e nome', function () {
    $group = Group::factory()->create();
    $s1 = Student::factory()->create(['first_name' => 'Zara', 'last_name' => 'Bianchi']);
    $s2 = Student::factory()->create(['first_name' => 'Anna', 'last_name' => 'Bianchi']);
    $s3 = Student::factory()->create(['first_name' => 'Marco', 'last_name' => 'Rossi']);
    $group->students()->attach([$s1->id, $s2->id, $s3->id]);

    $response = $this->get("/app/{$this->tenant->slug}/groups/{$group->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('group.students.0.first_name', 'Anna')
        ->where('group.students.1.first_name', 'Zara')
        ->where('group.students.2.first_name', 'Marco')
    );
});

test('show rispetta isolamento tenant', function () {
    $otherUser = User::factory()->create();
    $otherTenant = Tenant::factory()->create(['owner_id' => $otherUser->id]);

    // Creare gruppo nell'altro tenant
    tenancy()->end();
    tenancy()->initialize($otherTenant);
    $otherGroup = Group::factory()->create();
    tenancy()->end();
    tenancy()->initialize($this->tenant);

    $response = $this->get("/app/{$otherTenant->slug}/groups/{$otherGroup->id}");

    $response->assertForbidden();
});

test('show restituisce 404 per gruppo inesistente', function () {
    $response = $this->get("/app/{$this->tenant->slug}/groups/nonexistent-uuid");

    $response->assertNotFound();
});

test('show richiede autenticazione', function () {
    $group = Group::factory()->create();
    auth()->logout();

    $response = $this->get("/app/{$this->tenant->slug}/groups/{$group->id}");

    $response->assertRedirect();
});
