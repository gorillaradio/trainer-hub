<?php

use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->tenant = Tenant::factory()->create(['owner_id' => $this->user->id]);
    $this->user->update(['current_tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);

    tenancy()->initialize($this->tenant);
});

afterEach(function () {
    tenancy()->end();
});

// ─── Store with monthly_fee_override ─────────────────────────────────────────

test('store crea uno studente con monthly_fee_override convertito da euro a centesimi', function () {
    $response = $this->post("/app/{$this->tenant->slug}/students", [
        'first_name' => 'Marco',
        'last_name' => 'Rossi',
        'status' => 'active',
        'monthly_fee_override' => 50.00,
    ]);

    $response->assertRedirect("/app/{$this->tenant->slug}/students");
    $this->assertDatabaseHas('students', [
        'first_name' => 'Marco',
        'last_name' => 'Rossi',
        'monthly_fee_override' => 5000,
    ]);
});

test('store crea uno studente con monthly_fee_override null', function () {
    $response = $this->post("/app/{$this->tenant->slug}/students", [
        'first_name' => 'Luca',
        'last_name' => 'Bianchi',
        'status' => 'active',
        'monthly_fee_override' => null,
    ]);

    $response->assertRedirect("/app/{$this->tenant->slug}/students");
    $student = Student::where('first_name', 'Luca')->first();
    expect($student->monthly_fee_override)->toBeNull();
});

test('store converte correttamente importi decimali (es. 49.99 euro)', function () {
    $response = $this->post("/app/{$this->tenant->slug}/students", [
        'first_name' => 'Anna',
        'last_name' => 'Verdi',
        'status' => 'active',
        'monthly_fee_override' => 49.99,
    ]);

    $response->assertRedirect("/app/{$this->tenant->slug}/students");
    $this->assertDatabaseHas('students', [
        'first_name' => 'Anna',
        'monthly_fee_override' => 4999,
    ]);
});

test('store rifiuta monthly_fee_override negativo', function () {
    $response = $this->post("/app/{$this->tenant->slug}/students", [
        'first_name' => 'Marco',
        'last_name' => 'Rossi',
        'status' => 'active',
        'monthly_fee_override' => -10,
    ]);

    $response->assertSessionHasErrors(['monthly_fee_override']);
});

// ─── Update with monthly_fee_override ────────────────────────────────────────

test('update aggiorna monthly_fee_override convertendo da euro a centesimi', function () {
    $student = Student::factory()->create(['monthly_fee_override' => null]);

    $response = $this->put("/app/{$this->tenant->slug}/students/{$student->id}", [
        'first_name' => $student->first_name,
        'last_name' => $student->last_name,
        'monthly_fee_override' => 75.50,
    ]);

    $response->assertRedirect("/app/{$this->tenant->slug}/students/{$student->id}");
    $student->refresh();
    expect($student->monthly_fee_override)->toBe(7550);
});

test('update imposta monthly_fee_override a null', function () {
    $student = Student::factory()->create(['monthly_fee_override' => 5000]);

    $response = $this->put("/app/{$this->tenant->slug}/students/{$student->id}", [
        'first_name' => $student->first_name,
        'last_name' => $student->last_name,
        'monthly_fee_override' => null,
    ]);

    $response->assertRedirect("/app/{$this->tenant->slug}/students/{$student->id}");
    $student->refresh();
    expect($student->monthly_fee_override)->toBeNull();
});

test('update rifiuta monthly_fee_override negativo', function () {
    $student = Student::factory()->create();

    $response = $this->put("/app/{$this->tenant->slug}/students/{$student->id}", [
        'first_name' => $student->first_name,
        'last_name' => $student->last_name,
        'monthly_fee_override' => -5,
    ]);

    $response->assertSessionHasErrors(['monthly_fee_override']);
});

test('update con monthly_fee_override zero è valido', function () {
    $student = Student::factory()->create();

    $response = $this->put("/app/{$this->tenant->slug}/students/{$student->id}", [
        'first_name' => $student->first_name,
        'last_name' => $student->last_name,
        'monthly_fee_override' => 0,
    ]);

    $response->assertRedirect("/app/{$this->tenant->slug}/students/{$student->id}");
    $student->refresh();
    expect($student->monthly_fee_override)->toBe(0);
});
