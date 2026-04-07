<?php

use App\Models\EnrollmentFee;
use App\Models\Group;
use App\Models\MonthlyFee;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EnrollmentFeeService;
use App\Services\FeeCalculationService;
use App\Services\MonthlyFeeService;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->tenant = Tenant::factory()->create(['owner_id' => $this->user->id]);
    $this->user->update(['current_tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);
    tenancy()->initialize($this->tenant);

    $this->feeService = new FeeCalculationService();
    $this->monthlyService = new MonthlyFeeService($this->feeService);
    $this->enrollmentService = new EnrollmentFeeService();
});

afterEach(function () {
    Carbon::setTestNow();
    tenancy()->end();
});

// ─── Case #1: First payment sets cycle anchor ─────────────────────────────────

test('first payment sets cycle anchor to today', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 7));

    $group = Group::factory()->create(['monthly_fee_amount' => 5000]);
    $student = Student::factory()->create(['current_cycle_started_at' => null]);
    $student->groups()->attach($group->id, ['is_primary' => false]);
    $student->load('groups');

    $this->monthlyService->registerPayment($student, 1, 5000);

    $student->refresh();
    expect($student->current_cycle_started_at->toDateString())->toBe('2026-04-07');
});

// ─── Case #2: Late payment doesn't move anchor ────────────────────────────────

test('late payment does not move the cycle anchor', function () {
    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-01-16',
    ]);

    Carbon::setTestNow(Carbon::create(2026, 3, 25));

    $this->monthlyService->registerPayment($student, 1, 5000);

    $student->refresh();
    expect($student->current_cycle_started_at->toDateString())->toBe('2026-01-16');
});

// ─── Case #3: Skipped months show uncovered periods ───────────────────────────

test('skipped months appear as uncovered periods', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 20));

    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-01-16',
    ]);

    // Only January is paid
    $payment = Payment::factory()->create(['student_id' => $student->id]);
    MonthlyFee::create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'period' => '2026-01',
        'expected_amount' => 5000,
        'due_date' => '2026-01-16',
    ]);

    $uncovered = $this->monthlyService->getUncoveredPeriods($student);

    expect($uncovered)->toBe(['2026-02', '2026-03', '2026-04']);
});

// ─── Case #4: Suspension archives cycle and reactivation resets ───────────────

test('suspension archives cycle and reactivation leaves cycle null until first payment', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 7));

    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-01-16',
    ]);

    // Suspend the student
    $this->put("/app/{$this->tenant->slug}/students/{$student->id}/suspend");

    $student->refresh();
    expect($student->past_cycles)->toHaveCount(1);
    expect($student->current_cycle_started_at)->toBeNull();

    // Reactivate
    $this->put("/app/{$this->tenant->slug}/students/{$student->id}/reactivate");

    $student->refresh();
    expect($student->current_cycle_started_at)->toBeNull();

    // First payment after reactivation sets the anchor
    Carbon::setTestNow(Carbon::create(2026, 5, 1));
    $student->load('groups');
    $this->monthlyService->registerPayment($student, 1, 5000);

    $student->refresh();
    expect($student->current_cycle_started_at->toDateString())->toBe('2026-05-01');
});

// ─── Case #5: No group, no override = null rate ───────────────────────────────

test('student with no groups and no override has null effective rate', function () {
    $student = Student::factory()->create([
        'monthly_fee_override' => null,
    ]);
    $student->load('groups');

    $rate = $this->feeService->getEffectiveRate($student);

    expect($rate)->toBeNull();
});

// ─── Case #6: Removed from group, fee changes immediately ────────────────────

test('removing student from cheap group changes effective rate to remaining group', function () {
    $cheapGroup = Group::factory()->create(['monthly_fee_amount' => 4000]);
    $expensiveGroup = Group::factory()->create(['monthly_fee_amount' => 6000]);

    $student = Student::factory()->create(['monthly_fee_override' => null]);
    $student->groups()->attach($cheapGroup->id, ['is_primary' => false]);
    $student->groups()->attach($expensiveGroup->id, ['is_primary' => false]);
    $student->load('groups');

    expect($this->feeService->getEffectiveRate($student))->toBe(4000);

    $student->groups()->detach($cheapGroup->id);
    $student->load('groups');

    expect($this->feeService->getEffectiveRate($student))->toBe(6000);
});

// ─── Case #7: Anchor at 31st clamps to month end ─────────────────────────────

test('anchor day 31 clamps due dates to last day of shorter months', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 7));

    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-01-31',
    ]);

    $uncovered = $this->monthlyService->getUncoveredPeriods($student);
    expect($uncovered)->toBe(['2026-01', '2026-02', '2026-03']);

    $payment = $this->monthlyService->registerPayment($student, 3, 15000);

    $fees = MonthlyFee::where('student_id', $student->id)
        ->orderBy('period')
        ->get();

    expect($fees[0]->due_date->toDateString())->toBe('2026-01-31');
    expect($fees[1]->due_date->toDateString())->toBe('2026-02-28');
    expect($fees[2]->due_date->toDateString())->toBe('2026-03-31');
});

// ─── Case #8: Custom amount creates debt ─────────────────────────────────────

test('paying less than effective rate creates a negative balance', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 7));

    $group = Group::factory()->create(['monthly_fee_amount' => 5000]);
    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-04-01',
        'monthly_fee_override' => null,
    ]);
    $student->groups()->attach($group->id, ['is_primary' => false]);
    $student->load('groups');

    $this->monthlyService->registerPayment($student, 1, 4500);

    $balance = $this->feeService->getBalance($student);
    expect($balance)->toBe(-500);
});

// ─── Case #9: Multi-month payment creates 1 payment + N fees ─────────────────

test('multi-month payment creates one payment record and N monthly fees', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 7));

    $group = Group::factory()->create(['monthly_fee_amount' => 5000]);
    $student = Student::factory()->create([
        'current_cycle_started_at' => null,
        'monthly_fee_override' => null,
    ]);
    $student->groups()->attach($group->id, ['is_primary' => false]);
    $student->load('groups');

    $payment = $this->monthlyService->registerPayment($student, 3, 15000);

    expect(Payment::where('student_id', $student->id)->count())->toBe(1);
    expect(MonthlyFee::where('student_id', $student->id)->count())->toBe(3);
    expect($payment->amount)->toBe(15000);
});

// ─── Case #10: Expired enrollment doesn't block monthly payment ───────────────

test('expired enrollment does not block monthly payment registration', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 7));

    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-04-01',
    ]);

    $oldPayment = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::create([
        'student_id' => $student->id,
        'payment_id' => $oldPayment->id,
        'expected_amount' => 10000,
        'starts_at' => '2025-01-01',
        'expires_at' => '2026-01-01',
    ]);

    expect($this->enrollmentService->isEnrollmentExpired($student))->toBeTrue();

    $response = $this->post("/app/{$this->tenant->slug}/students/{$student->id}/payments/monthly", [
        'amount' => 50,
        'months' => 1,
    ]);

    $response->assertRedirect();
});

// ─── Case #11: Early renewal extends from old expiry ─────────────────────────

test('early renewal starts from old expiry date and extends 12 months', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 7));

    $student = Student::factory()->create();

    $oldPayment = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::create([
        'student_id' => $student->id,
        'payment_id' => $oldPayment->id,
        'expected_amount' => 10000,
        'starts_at' => '2025-06-01',
        'expires_at' => '2026-06-01',
    ]);

    $this->enrollmentService->registerEnrollment($student, 10000);

    $newEnrollment = $student->enrollmentFees()->orderByDesc('expires_at')->first();
    expect($newEnrollment->starts_at->toDateString())->toBe('2026-06-01');
    expect($newEnrollment->expires_at->toDateString())->toBe('2027-06-01');
});

// ─── Case #12: Enrollment expires during suspension ──────────────────────────

test('enrollment expiry is tracked independently from suspension cycle archiving', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 7));

    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-01-01',
    ]);

    $oldPayment = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::create([
        'student_id' => $student->id,
        'payment_id' => $oldPayment->id,
        'expected_amount' => 10000,
        'starts_at' => '2025-01-01',
        'expires_at' => '2026-01-01',
    ]);

    $this->put("/app/{$this->tenant->slug}/students/{$student->id}/suspend");

    $student->refresh();
    expect($student->past_cycles)->toHaveCount(1);

    expect($this->enrollmentService->isEnrollmentExpired($student))->toBeTrue();
});

// ─── Case #13: Default enrollment duration change only affects new ────────────

test('changing enrollment_duration_months setting only affects new enrollments', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 7));

    $student1 = Student::factory()->create();
    $student2 = Student::factory()->create();

    // Register for student1 with default 12-month duration
    $this->enrollmentService->registerEnrollment($student1, 10000);

    $enrollment1 = $student1->enrollmentFees()->first();
    expect($enrollment1->expires_at->toDateString())->toBe('2027-04-07');

    // Update tenant settings to 6-month duration
    $this->tenant->update(['settings' => ['enrollment_duration_months' => 6]]);

    // Register for student2 with new 6-month duration
    $this->enrollmentService->registerEnrollment($student2, 10000);

    $enrollment2 = $student2->enrollmentFees()->first();
    expect($enrollment2->expires_at->toDateString())->toBe('2026-10-07');

    // student1's enrollment is unchanged
    $enrollment1->refresh();
    expect($enrollment1->expires_at->toDateString())->toBe('2027-04-07');
});
