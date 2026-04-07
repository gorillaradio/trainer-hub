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

test('index mostra la lista gruppi', function () {
    Group::factory()->count(3)->create();

    $response = $this->get("/app/{$this->tenant->slug}/groups");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/Group/Index')
        ->has('groups', 3)
    );
});

test('index mostra il conteggio studenti per gruppo', function () {
    $group = Group::factory()->create();
    $students = Student::factory()->count(2)->create();
    $group->students()->attach($students->pluck('id'));

    $response = $this->get("/app/{$this->tenant->slug}/groups");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('groups.0.students_count', 2)
    );
});

test('create mostra il form', function () {
    $response = $this->get("/app/{$this->tenant->slug}/groups/create");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/Group/Create')
    );
});

test('store crea un gruppo', function () {
    $response = $this->post("/app/{$this->tenant->slug}/groups", [
        'name'               => 'Corso Avanzato',
        'description'        => 'Gruppo per atleti avanzati',
        'color'              => '#FF5733',
        'monthly_fee_amount' => 50.00,
    ]);

    $response->assertRedirect("/app/{$this->tenant->slug}/groups");
    $this->assertDatabaseHas('groups', [
        'name'               => 'Corso Avanzato',
        'color'              => '#FF5733',
        'monthly_fee_amount' => 5000, // 50.00 * 100 = 5000 centesimi
        'tenant_id'          => $this->tenant->slug,
    ]);
});

test('store valida i campi obbligatori', function () {
    $response = $this->post("/app/{$this->tenant->slug}/groups", []);

    $response->assertSessionHasErrors(['name', 'color', 'monthly_fee_amount']);
});

test('store valida il formato colore', function () {
    $response = $this->post("/app/{$this->tenant->slug}/groups", [
        'name'               => 'Corso Test',
        'color'              => 'rosso',
        'monthly_fee_amount' => 50.00,
    ]);

    $response->assertSessionHasErrors(['color']);
});

test('store valida importo positivo', function () {
    $response = $this->post("/app/{$this->tenant->slug}/groups", [
        'name'               => 'Corso Test',
        'color'              => '#FF5733',
        'monthly_fee_amount' => 0,
    ]);

    $response->assertSessionHasErrors(['monthly_fee_amount']);
});

test('edit mostra il form con dati precompilati', function () {
    $group = Group::factory()->create();

    $response = $this->get("/app/{$this->tenant->slug}/groups/{$group->id}/edit");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/Group/Edit')
        ->has('group')
        ->where('group.id', $group->id)
    );
});

test('edit non include la lista studenti (spostata in show)', function () {
    $group = Group::factory()->create();
    $student = Student::factory()->create();
    $group->students()->attach($student->id);

    $response = $this->get("/app/{$this->tenant->slug}/groups/{$group->id}/edit");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->missing('group.students')
    );
});

test('update aggiorna un gruppo', function () {
    $group = Group::factory()->create(['monthly_fee_amount' => 3000]);

    $response = $this->put("/app/{$this->tenant->slug}/groups/{$group->id}", [
        'name'               => 'Nome Aggiornato',
        'description'        => 'Nuova descrizione',
        'color'              => '#123456',
        'monthly_fee_amount' => 80.00,
    ]);

    $response->assertRedirect("/app/{$this->tenant->slug}/groups");
    $this->assertDatabaseHas('groups', [
        'id'                 => $group->id,
        'name'               => 'Nome Aggiornato',
        'color'              => '#123456',
        'monthly_fee_amount' => 8000,
    ]);
});

test('destroy elimina un gruppo', function () {
    $group = Group::factory()->create();

    $response = $this->delete("/app/{$this->tenant->slug}/groups/{$group->id}");

    $response->assertRedirect("/app/{$this->tenant->slug}/groups");
    $this->assertDatabaseMissing('groups', ['id' => $group->id]);
});

test('destroy rimuove le assegnazioni studenti', function () {
    $group = Group::factory()->create();
    $student = Student::factory()->create();
    $group->students()->attach($student->id);

    $this->assertDatabaseHas('group_student', [
        'group_id'   => $group->id,
        'student_id' => $student->id,
    ]);

    $response = $this->delete("/app/{$this->tenant->slug}/groups/{$group->id}");

    $response->assertRedirect("/app/{$this->tenant->slug}/groups");
    $this->assertDatabaseMissing('group_student', [
        'group_id' => $group->id,
    ]);
});

test('un utente non può accedere ai gruppi di un altro tenant', function () {
    $otherUser = User::factory()->create();
    $otherTenant = Tenant::factory()->create(['owner_id' => $otherUser->id]);

    $response = $this->get("/app/{$otherTenant->slug}/groups");

    $response->assertForbidden();
});
