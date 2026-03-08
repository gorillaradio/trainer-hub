<?php

use App\Enums\StudentStatus;
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

test('index mostra la lista allievi', function () {
    Student::factory()->count(3)->create();

    $response = $this->get("/app/{$this->tenant->slug}/students");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/students/index')
        ->has('students.data', 3)
    );
});

test('index filtra per stato', function () {
    Student::factory()->count(2)->create(['status' => StudentStatus::Active]);
    Student::factory()->inactive()->create();

    $response = $this->get("/app/{$this->tenant->slug}/students?status=active");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('students.data', 2)
    );
});

test('index cerca per nome', function () {
    Student::factory()->create(['first_name' => 'Marco', 'last_name' => 'Rossi']);
    Student::factory()->create(['first_name' => 'Luca', 'last_name' => 'Bianchi']);

    $response = $this->get("/app/{$this->tenant->slug}/students?search=Marco");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('students.data', 1)
    );
});

test('create mostra il form', function () {
    $response = $this->get("/app/{$this->tenant->slug}/students/create");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/students/create')
    );
});

test('store crea un allievo', function () {
    $response = $this->post("/app/{$this->tenant->slug}/students", [
        'first_name' => 'Marco',
        'last_name' => 'Rossi',
        'email' => 'marco@example.com',
        'status' => 'active',
    ]);

    $response->assertRedirect("/app/{$this->tenant->slug}/students");
    $this->assertDatabaseHas('students', [
        'first_name' => 'Marco',
        'last_name' => 'Rossi',
        'tenant_id' => $this->tenant->slug,
    ]);
});

test('store valida i campi obbligatori', function () {
    $response = $this->post("/app/{$this->tenant->slug}/students", []);

    $response->assertSessionHasErrors(['first_name', 'last_name']);
});

test('store valida email unica per tenant', function () {
    Student::factory()->create([
        'email' => 'mario@example.com',
    ]);

    $response = $this->post("/app/{$this->tenant->slug}/students", [
        'first_name' => 'Mario',
        'last_name' => 'Verdi',
        'email' => 'mario@example.com',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('show mostra i dettagli allievo', function () {
    $student = Student::factory()->create();

    $response = $this->get("/app/{$this->tenant->slug}/students/{$student->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/students/show')
        ->has('student')
    );
});

test('edit mostra il form con dati precompilati', function () {
    $student = Student::factory()->create();

    $response = $this->get("/app/{$this->tenant->slug}/students/{$student->id}/edit");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/students/edit')
        ->has('student')
    );
});

test('update aggiorna un allievo', function () {
    $student = Student::factory()->create();

    $response = $this->put("/app/{$this->tenant->slug}/students/{$student->id}", [
        'first_name' => 'NuovoNome',
        'last_name' => 'NuovoCognome',
    ]);

    $response->assertRedirect("/app/{$this->tenant->slug}/students/{$student->id}");
    $this->assertDatabaseHas('students', [
        'id' => $student->id,
        'first_name' => 'NuovoNome',
    ]);
});

test('destroy archivia un allievo (soft delete)', function () {
    $student = Student::factory()->create();

    $response = $this->delete("/app/{$this->tenant->slug}/students/{$student->id}");

    $response->assertRedirect("/app/{$this->tenant->slug}/students");
    $this->assertSoftDeleted('students', ['id' => $student->id]);
});

test('un utente non può accedere agli allievi di un altro tenant', function () {
    $otherUser = User::factory()->create();
    $otherTenant = Tenant::factory()->create(['owner_id' => $otherUser->id]);

    $response = $this->get("/app/{$otherTenant->slug}/students");

    $response->assertForbidden();
});
