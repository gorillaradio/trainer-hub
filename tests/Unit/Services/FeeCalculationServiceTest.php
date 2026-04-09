<?php

use App\Models\EnrollmentFee;
use App\Models\Group;
use App\Models\MonthlyFee;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FeeCalculationService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->tenant = Tenant::factory()->create(['owner_id' => $this->user->id]);
    tenancy()->initialize($this->tenant);
    $this->service = new FeeCalculationService();
});

afterEach(function () {
    tenancy()->end();
});

// ============================================================================
// getEffectiveRate Tests
// ============================================================================

test('getEffectiveRate returns monthly_fee_override when set', function () {
    $student = Student::factory()->create(['monthly_fee_override' => 3500]);
    $group = Group::factory()->create(['monthly_fee_amount' => 5000]);
    $student->groups()->attach($group->id);

    $rate = $this->service->getEffectiveRate($student);

    expect($rate)->toBe(3500);
});

test('getEffectiveRate returns min group fee when no override', function () {
    $student = Student::factory()->create(['monthly_fee_override' => null]);
    $group1 = Group::factory()->create(['monthly_fee_amount' => 3000]);
    $group2 = Group::factory()->create(['monthly_fee_amount' => 6000]);

    $student->groups()->attach($group1->id, ['is_primary' => false]);
    $student->groups()->attach($group2->id, ['is_primary' => false]);

    // Reload student to get fresh groups relation
    $student = $student->fresh();

    $rate = $this->service->getEffectiveRate($student);

    expect($rate)->toBe(3000);
});

test('getEffectiveRate returns null when no groups and no override', function () {
    $student = Student::factory()->create(['monthly_fee_override' => null]);

    $rate = $this->service->getEffectiveRate($student);

    expect($rate)->toBeNull();
});

test('getEffectiveRate with single group returns that fee', function () {
    $student = Student::factory()->create(['monthly_fee_override' => null]);
    $group = Group::factory()->create(['monthly_fee_amount' => 4000]);

    $student->groups()->attach($group->id);

    $student = $student->fresh();

    $rate = $this->service->getEffectiveRate($student);

    expect($rate)->toBe(4000);
});

// ============================================================================
// getBalance Tests
// ============================================================================

test('getBalance returns zero with no payments', function () {
    $student = Student::factory()->create();

    $balance = $this->service->getBalance($student);

    expect($balance)->toBe(0);
});

test('getBalance returns negative when underpaid', function () {
    $student = Student::factory()->create();

    $payment = Payment::factory()->create(['student_id' => $student->id, 'amount' => 4500]);

    MonthlyFee::create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'period' => '2024-01',
        'expected_amount' => 5000,
        'due_date' => now()->toDateString(),
    ]);

    $balance = $this->service->getBalance($student);

    expect($balance)->toBe(-500);
});

test('getBalance returns positive when overpaid', function () {
    $student = Student::factory()->create();

    $payment = Payment::factory()->create(['student_id' => $student->id, 'amount' => 5500]);

    MonthlyFee::create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'period' => '2024-01',
        'expected_amount' => 5000,
        'due_date' => now()->toDateString(),
    ]);

    $balance = $this->service->getBalance($student);

    expect($balance)->toBe(500);
});

test('getBalance includes enrollment fees', function () {
    $student = Student::factory()->create();

    $payment = Payment::factory()->create(['student_id' => $student->id, 'amount' => 10000]);

    MonthlyFee::create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'period' => '2024-01',
        'expected_amount' => 5000,
        'due_date' => now()->toDateString(),
    ]);

    EnrollmentFee::create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'expected_amount' => 5000,
        'starts_at' => now()->toDateString(),
        'expires_at' => now()->addYear()->toDateString(),
    ]);

    $balance = $this->service->getBalance($student);

    expect($balance)->toBe(0);
});

test('getBalance with multiple payments and fees', function () {
    $student = Student::factory()->create();

    $payment1 = Payment::factory()->create(['student_id' => $student->id, 'amount' => 3000]);
    $payment2 = Payment::factory()->create(['student_id' => $student->id, 'amount' => 4000]);

    MonthlyFee::create([
        'student_id' => $student->id,
        'payment_id' => $payment1->id,
        'period' => '2024-01',
        'expected_amount' => 5000,
        'due_date' => now()->toDateString(),
    ]);

    MonthlyFee::create([
        'student_id' => $student->id,
        'payment_id' => $payment2->id,
        'period' => '2024-02',
        'expected_amount' => 5000,
        'due_date' => now()->addMonth()->toDateString(),
    ]);

    // Total paid: 7000, total monthly expected: 10000
    // Balance: 7000 - 10000 = -3000
    $balance = $this->service->getBalance($student);

    expect($balance)->toBe(-3000);
});

test('getBalance with zero balance', function () {
    $student = Student::factory()->create();

    $payment = Payment::factory()->create(['student_id' => $student->id, 'amount' => 5000]);

    MonthlyFee::create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'period' => '2024-01',
        'expected_amount' => 5000,
        'due_date' => now()->toDateString(),
    ]);

    $balance = $this->service->getBalance($student);

    expect($balance)->toBe(0);
});
