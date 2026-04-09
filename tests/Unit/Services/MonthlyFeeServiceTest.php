<?php

use Carbon\Carbon;
use App\Models\Group;
use App\Models\MonthlyFee;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FeeCalculationService;
use App\Services\MonthlyFeeService;
use App\Enums\PaymentMethod;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->tenant = Tenant::factory()->create(['owner_id' => $this->user->id]);
    tenancy()->initialize($this->tenant);
    $this->service = new MonthlyFeeService(new FeeCalculationService());
});

afterEach(function () {
    Carbon::setTestNow();
    tenancy()->end();
});

// ============================================================================
// getUncoveredPeriods Tests
// ============================================================================

test('getUncoveredPeriods returns empty when no cycle started', function () {
    $student = Student::factory()->create(['current_cycle_started_at' => null]);

    $result = $this->service->getUncoveredPeriods($student);

    expect($result)->toBe([]);
});

test('getUncoveredPeriods returns months from anchor to today', function () {
    Carbon::setTestNow('2026-04-07');

    // Anchor Jan 16: due dates Jan 16, Feb 16, Mar 16 are all before Apr 7
    // Apr 16 is after Apr 7, so excluded
    $student = Student::factory()->create(['current_cycle_started_at' => '2026-01-16']);

    $result = $this->service->getUncoveredPeriods($student);

    expect($result)->toBe(['2026-01', '2026-02', '2026-03']);
});

test('getUncoveredPeriods excludes already paid periods', function () {
    Carbon::setTestNow('2026-04-07');

    $student = Student::factory()->create(['current_cycle_started_at' => '2026-01-16']);

    // Create a payment and cover January
    $payment = Payment::factory()->create(['student_id' => $student->id, 'amount' => 5000]);
    MonthlyFee::create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'period' => '2026-01',
        'expected_amount' => 5000,
        'due_date' => '2026-01-16',
    ]);

    $result = $this->service->getUncoveredPeriods($student);

    expect($result)->toBe(['2026-02', '2026-03']);
});

test('getUncoveredPeriods clamps to last day of month', function () {
    Carbon::setTestNow('2026-04-07');

    // Anchor Jan 31: due dates Jan 31, Feb 28, Mar 31 are all before Apr 7
    // Apr 30 is after Apr 7, so excluded
    $student = Student::factory()->create(['current_cycle_started_at' => '2026-01-31']);

    $result = $this->service->getUncoveredPeriods($student);

    expect($result)->toBe(['2026-01', '2026-02', '2026-03']);
});

// ============================================================================
// registerPayment Tests
// ============================================================================

test('registerPayment creates payment and monthly fee records', function () {
    Carbon::setTestNow('2026-04-07');

    $student = Student::factory()->create(['current_cycle_started_at' => '2026-01-16']);

    $payment = $this->service->registerPayment($student, 1, 5000);

    expect($payment)->toBeInstanceOf(Payment::class);
    expect($payment->amount)->toBe(5000);
    expect($payment->payment_method)->toBe(PaymentMethod::Cash);

    expect(MonthlyFee::where('student_id', $student->id)->count())->toBe(1);
});

test('registerPayment sets cycle start on first payment', function () {
    Carbon::setTestNow('2026-04-07');

    $student = Student::factory()->create(['current_cycle_started_at' => null]);

    $this->service->registerPayment($student, 1, 5000);

    $student->refresh();
    expect($student->current_cycle_started_at->toDateString())->toBe('2026-04-07');
});

test('registerPayment does not move cycle anchor on subsequent payments', function () {
    Carbon::setTestNow('2026-04-07');

    $student = Student::factory()->create(['current_cycle_started_at' => '2026-01-16']);

    $this->service->registerPayment($student, 1, 5000);

    $student->refresh();
    expect($student->current_cycle_started_at->toDateString())->toBe('2026-01-16');
});

test('registerPayment covers oldest uncovered months first (FIFO)', function () {
    Carbon::setTestNow('2026-04-20');

    // Anchor Jan 16 → Jan 16, Feb 16, Mar 16, Apr 16 all due before Apr 20
    $student = Student::factory()->create(['current_cycle_started_at' => '2026-01-16']);

    // Pay 1 month → should cover January (oldest)
    $this->service->registerPayment($student, 1, 5000);

    $fee = MonthlyFee::where('student_id', $student->id)->first();
    expect($fee->period)->toBe('2026-01');
});

test('registerPayment creates multiple fee records for multi-month payment', function () {
    Carbon::setTestNow('2026-04-20');

    // Anchor Jan 16 → Jan 16, Feb 16, Mar 16, Apr 16 all due before Apr 20
    $student = Student::factory()->create(['current_cycle_started_at' => '2026-01-16']);

    $this->service->registerPayment($student, 3, 15000);

    $periods = MonthlyFee::where('student_id', $student->id)
        ->orderBy('period')
        ->pluck('period')
        ->toArray();

    expect($periods)->toBe(['2026-01', '2026-02', '2026-03']);
});

test('registerPayment accepts custom amount creating debt', function () {
    Carbon::setTestNow('2026-04-07');

    $student = Student::factory()->create(['current_cycle_started_at' => '2026-01-16']);

    // Group fee is 5000, but student only pays 4500
    $group = Group::factory()->create(['monthly_fee_amount' => 5000]);
    $student->groups()->attach($group->id, ['is_primary' => true]);
    $student = $student->fresh();

    $payment = $this->service->registerPayment($student, 1, 4500);

    expect($payment->amount)->toBe(4500);

    $fee = MonthlyFee::where('student_id', $student->id)->first();
    expect($fee->expected_amount)->toBe(5000);
});
