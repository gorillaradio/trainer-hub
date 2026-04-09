<?php

use App\Enums\StudentStatus;
use App\Models\EmergencyContact;
use App\Models\EnrollmentFee;
use App\Models\Payment;
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

    $response = $this->get("/app/{$this->tenant->slug}/students?status=all");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/Student/Index')
        ->has('students', 3)
    );
});

test('index filtra per stato', function () {
    // 2 students with valid enrollment → effective_status = active
    $activeStudents = Student::factory()->count(2)->create();
    foreach ($activeStudents as $student) {
        $payment = Payment::factory()->create(['student_id' => $student->id]);
        EnrollmentFee::factory()->create([
            'student_id' => $student->id,
            'payment_id' => $payment->id,
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addMonths(11),
        ]);
    }

    // 1 student without enrollment → effective_status = pending
    Student::factory()->create();

    // 1 suspended student
    Student::factory()->suspended()->create();

    $response = $this->get("/app/{$this->tenant->slug}/students?status=active");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('students', 2)
    );
});

test('index cerca per nome', function () {
    Student::factory()->create(['first_name' => 'Marco', 'last_name' => 'Rossi']);
    Student::factory()->create(['first_name' => 'Luca', 'last_name' => 'Bianchi']);

    $response = $this->get("/app/{$this->tenant->slug}/students?search=Marco&status=all");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('students', 1)
    );
});

test('create mostra il form', function () {
    $response = $this->get("/app/{$this->tenant->slug}/students/create");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/Student/Create')
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

test('store crea un allievo con contatti di emergenza', function () {
    $response = $this->post("/app/{$this->tenant->slug}/students", [
        'first_name' => 'Marco',
        'last_name' => 'Rossi',
        'status' => 'active',
        'emergency_contacts' => [
            ['name' => 'Padre Marco', 'phone' => '333-1234567'],
            ['name' => 'Madre Anna', 'phone' => '333-7654321'],
        ],
    ]);

    $response->assertRedirect("/app/{$this->tenant->slug}/students");
    $student = Student::where('first_name', 'Marco')->first();
    expect($student->emergencyContacts)->toHaveCount(2);
    $this->assertDatabaseHas('emergency_contacts', [
        'student_id' => $student->id,
        'name' => 'Padre Marco',
        'phone' => '333-1234567',
    ]);
    $this->assertDatabaseHas('emergency_contacts', [
        'student_id' => $student->id,
        'name' => 'Madre Anna',
        'phone' => '333-7654321',
    ]);
});

test('store con phone_contact_index linka il telefono al contatto', function () {
    $response = $this->post("/app/{$this->tenant->slug}/students", [
        'first_name' => 'Luca',
        'last_name' => 'Verdi',
        'status' => 'active',
        'emergency_contacts' => [
            ['name' => 'Padre', 'phone' => '333-0000001'],
            ['name' => 'Madre', 'phone' => '333-0000002'],
        ],
        'phone_contact_index' => 1,
    ]);

    $response->assertRedirect("/app/{$this->tenant->slug}/students");
    $student = Student::where('first_name', 'Luca')->first();
    expect($student->phone_contact_id)->not->toBeNull();
    expect($student->phone)->toBeNull();

    $linkedContact = $student->phoneContact;
    expect($linkedContact->name)->toBe('Madre');
    expect($linkedContact->phone)->toBe('333-0000002');
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

test('show mostra i dettagli allievo con contatti', function () {
    $student = Student::factory()->create();
    $student->emergencyContacts()->create(['name' => 'Padre', 'phone' => '333-111']);

    $response = $this->get("/app/{$this->tenant->slug}/students/{$student->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/Student/Show')
        ->has('student')
        ->has('student.emergency_contacts', 1)
        ->where('student.emergency_contacts.0.name', 'Padre')
    );
});

test('show include effective_phone', function () {
    $student = Student::factory()->create(['phone' => '333-PROPRIO']);

    $response = $this->get("/app/{$this->tenant->slug}/students/{$student->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('student.effective_phone', '333-PROPRIO')
    );
});

test('edit mostra il form con dati precompilati', function () {
    $student = Student::factory()->create();

    $response = $this->get("/app/{$this->tenant->slug}/students/{$student->id}/edit");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/Student/Edit')
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

test('update sincronizza i contatti di emergenza', function () {
    $student = Student::factory()->create();
    $student->emergencyContacts()->create(['name' => 'Vecchio', 'phone' => '000']);

    $response = $this->put("/app/{$this->tenant->slug}/students/{$student->id}", [
        'first_name' => $student->first_name,
        'last_name' => $student->last_name,
        'emergency_contacts' => [
            ['name' => 'Nuovo1', 'phone' => '111'],
            ['name' => 'Nuovo2', 'phone' => '222'],
        ],
    ]);

    $response->assertRedirect();
    $student->refresh();
    expect($student->emergencyContacts)->toHaveCount(2);
    $this->assertDatabaseMissing('emergency_contacts', ['name' => 'Vecchio']);
    $this->assertDatabaseHas('emergency_contacts', ['student_id' => $student->id, 'name' => 'Nuovo1']);
});

test('update rimuove link telefono quando phone_contact_index è null', function () {
    $student = Student::factory()->create(['phone' => null]);
    $contact = $student->emergencyContacts()->create(['name' => 'Padre', 'phone' => '333']);
    $student->update(['phone_contact_id' => $contact->id]);

    $response = $this->put("/app/{$this->tenant->slug}/students/{$student->id}", [
        'first_name' => $student->first_name,
        'last_name' => $student->last_name,
        'phone' => '555-PROPRIO',
        'phone_contact_index' => null,
        'emergency_contacts' => [
            ['name' => 'Padre', 'phone' => '333'],
        ],
    ]);

    $response->assertRedirect();
    $student->refresh();
    expect($student->phone_contact_id)->toBeNull();
    expect($student->phone)->toBe('555-PROPRIO');
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

test('emergency contacts rispettano isolamento tenant', function () {
    $student = Student::factory()->create();
    $student->emergencyContacts()->create(['name' => 'Contatto Tenant A', 'phone' => '333']);

    // Crea tenant B
    $otherUser = User::factory()->create();
    $otherTenant = Tenant::factory()->create(['owner_id' => $otherUser->id]);

    // I contatti del tenant A non devono essere visibili nel tenant B
    tenancy()->end();
    tenancy()->initialize($otherTenant);

    expect(EmergencyContact::count())->toBe(0);

    tenancy()->end();
    tenancy()->initialize($this->tenant);
});

test('suspend archivia il ciclo corrente in past_cycles', function () {
    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-01-16',
    ]);

    $response = $this->put("/app/{$this->tenant->slug}/students/{$student->id}/suspend");

    $response->assertRedirect();
    $student->refresh();
    expect($student->status)->toBe(StudentStatus::Suspended);
    expect($student->current_cycle_started_at)->toBeNull();
    expect($student->past_cycles)->toHaveCount(1);
    expect($student->past_cycles[0]['started_at'])->toBe('2026-01-16');
    expect($student->past_cycles[0]['reason'])->toBe('suspended');
});

test('suspend senza ciclo attivo non aggiunge past_cycles', function () {
    $student = Student::factory()->create([
        'current_cycle_started_at' => null,
    ]);

    $this->put("/app/{$this->tenant->slug}/students/{$student->id}/suspend");

    $student->refresh();
    expect($student->past_cycles)->toBeNull();
});

test('reactivate mantiene current_cycle_started_at null', function () {
    $student = Student::factory()->create([
        'status' => StudentStatus::Suspended,
        'current_cycle_started_at' => null,
    ]);

    $this->put("/app/{$this->tenant->slug}/students/{$student->id}/reactivate");

    $student->refresh();
    expect($student->status)->toBeNull();
    expect($student->effective_status)->toBe('pending');
    expect($student->current_cycle_started_at)->toBeNull();
});
