<?php

use App\Models\Group;
use App\Models\MonthlyFee;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->tenant = Tenant::factory()->create(['owner_id' => $this->user->id]);
    $this->user->update(['current_tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);
    tenancy()->initialize($this->tenant);
});

afterEach(function () {
    Carbon::setTestNow();
    tenancy()->end();
});

// ─── registerMonthly ─────────────────────────────────────────────────────────

test('registerMonthly crea payment e monthly fee', function () {
    $student = Student::factory()->create([
        'current_cycle_started_at' => now()->startOfMonth()->toDateString(),
    ]);

    $response = $this->post("/app/{$this->tenant->slug}/students/{$student->id}/payments/monthly", [
        'amount' => 50,
        'months' => 1,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(Payment::where('student_id', $student->id)->count())->toBe(1);
    expect(MonthlyFee::where('student_id', $student->id)->count())->toBe(1);
});

test('registerMonthly valida campi obbligatori', function () {
    $student = Student::factory()->create();

    $response = $this->post("/app/{$this->tenant->slug}/students/{$student->id}/payments/monthly", []);

    $response->assertSessionHasErrors(['amount', 'months']);
});

test('registerMonthly valida importo positivo', function () {
    $student = Student::factory()->create();

    $response = $this->post("/app/{$this->tenant->slug}/students/{$student->id}/payments/monthly", [
        'amount' => 0,
        'months' => 1,
    ]);

    $response->assertSessionHasErrors(['amount']);
});

test('registerMonthly multi-month creates correct records', function () {
    Carbon::setTestNow(Carbon::create(2026, 1, 10));

    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-01-10',
    ]);

    $response = $this->post("/app/{$this->tenant->slug}/students/{$student->id}/payments/monthly", [
        'amount' => 150,
        'months' => 3,
    ]);

    $response->assertRedirect();

    $payment = Payment::where('student_id', $student->id)->first();
    expect($payment)->not->toBeNull();
    expect($payment->amount)->toBe(15000); // 150 euros → 15000 cents

    expect(MonthlyFee::where('student_id', $student->id)->count())->toBe(3);
});

test('registerMonthly valida months massimo 12', function () {
    $student = Student::factory()->create();

    $response = $this->post("/app/{$this->tenant->slug}/students/{$student->id}/payments/monthly", [
        'amount' => 50,
        'months' => 13,
    ]);

    $response->assertSessionHasErrors(['months']);
});

// ─── registerEnrollment ──────────────────────────────────────────────────────

test('registerEnrollment crea payment e enrollment fee', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 7));

    $student = Student::factory()->create();

    $response = $this->post("/app/{$this->tenant->slug}/students/{$student->id}/payments/enrollment", [
        'amount' => 100,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $payment = Payment::where('student_id', $student->id)->first();
    expect($payment)->not->toBeNull();
    expect($payment->amount)->toBe(10000); // 100 euros → 10000 cents

    $enrollment = $student->enrollmentFees()->first();
    expect($enrollment)->not->toBeNull();
    expect($enrollment->starts_at->toDateString())->toBe('2026-04-07');
    expect($enrollment->expires_at->toDateString())->toBe('2027-04-07'); // +12 months
});

test('registerEnrollment valida campi obbligatori', function () {
    $student = Student::factory()->create();

    $response = $this->post("/app/{$this->tenant->slug}/students/{$student->id}/payments/enrollment", []);

    $response->assertSessionHasErrors(['amount']);
});

test('registerEnrollment valida importo positivo', function () {
    $student = Student::factory()->create();

    $response = $this->post("/app/{$this->tenant->slug}/students/{$student->id}/payments/enrollment", [
        'amount' => 0,
    ]);

    $response->assertSessionHasErrors(['amount']);
});

// ─── Authorization ────────────────────────────────────────────────────────────

test('non si possono registrare pagamenti per studenti di un altro tenant', function () {
    $otherUser = User::factory()->create();
    $otherTenant = Tenant::factory()->create(['owner_id' => $otherUser->id]);

    tenancy()->end();
    tenancy()->initialize($otherTenant);
    $otherStudent = Student::factory()->create();
    tenancy()->end();
    tenancy()->initialize($this->tenant);

    $response = $this->post("/app/{$otherTenant->slug}/students/{$otherStudent->id}/payments/monthly", [
        'amount' => 50,
        'months' => 1,
    ]);

    $response->assertForbidden();
});

test('non si può registrare iscrizione per studenti di un altro tenant', function () {
    $otherUser = User::factory()->create();
    $otherTenant = Tenant::factory()->create(['owner_id' => $otherUser->id]);

    tenancy()->end();
    tenancy()->initialize($otherTenant);
    $otherStudent = Student::factory()->create();
    tenancy()->end();
    tenancy()->initialize($this->tenant);

    $response = $this->post("/app/{$otherTenant->slug}/students/{$otherStudent->id}/payments/enrollment", [
        'amount' => 100,
    ]);

    $response->assertForbidden();
});

// ─── show paymentData ────────────────────────────────────────────────────────

test('show include dati pagamento per lo studente', function () {
    $group = Group::factory()->create(['monthly_fee_amount' => 5000]);
    $student = Student::factory()->create([
        'current_cycle_started_at' => now()->startOfMonth()->toDateString(),
    ]);
    $student->groups()->attach($group->id, ['is_primary' => true]);

    $response = $this->get("/app/{$this->tenant->slug}/students/{$student->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/Student/Show')
        ->has('paymentData')
        ->has('paymentData.effectiveRate')
        ->has('paymentData.balance')
        ->has('paymentData.uncoveredPeriods')
        ->has('paymentData.enrollmentExpired')
        ->has('paymentData.payments')
    );
});

test('show paymentData contiene effectiveRate corretto dal gruppo primario', function () {
    $group = Group::factory()->create(['monthly_fee_amount' => 6000]);
    $student = Student::factory()->create();
    $student->groups()->attach($group->id, ['is_primary' => true]);

    $response = $this->get("/app/{$this->tenant->slug}/students/{$student->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('paymentData.effectiveRate', 6000)
    );
});

test('show paymentData payments è vuoto per nuovo studente', function () {
    $student = Student::factory()->create();

    $response = $this->get("/app/{$this->tenant->slug}/students/{$student->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('paymentData.payments', 0)
    );
});
