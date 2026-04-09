<?php

use Carbon\Carbon;
use App\Models\EnrollmentFee;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EnrollmentFeeService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->tenant = Tenant::factory()->create(['owner_id' => $this->user->id]);
    tenancy()->initialize($this->tenant);
    $this->service = new EnrollmentFeeService();
});

afterEach(function () {
    Carbon::setTestNow();
    tenancy()->end();
});

// ============================================================================
// registerEnrollment Tests
// ============================================================================

test('registerEnrollment creates payment and enrollment fee', function () {
    Carbon::setTestNow('2026-04-07');

    $student = Student::factory()->create();

    $payment = $this->service->registerEnrollment($student, 10000);

    expect($payment)->toBeInstanceOf(Payment::class);
    expect($payment->amount)->toBe(10000);
    expect($payment->student_id)->toBe($student->id);
    expect($payment->paid_at->toDateString())->toBe('2026-04-07');

    $enrollment = EnrollmentFee::where('student_id', $student->id)->first();
    expect($enrollment)->not->toBeNull();
    expect($enrollment->payment_id)->toBe($payment->id);
    expect($enrollment->expected_amount)->toBe(10000);
    expect($enrollment->starts_at->toDateString())->toBe('2026-04-07');
    expect($enrollment->expires_at->toDateString())->toBe('2027-04-07');
});

test('registerEnrollment extends from old expiry on early renewal', function () {
    Carbon::setTestNow('2026-04-07');

    $student = Student::factory()->create();

    // Create an existing enrollment that expires in the future
    $oldPayment = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::create([
        'student_id' => $student->id,
        'payment_id' => $oldPayment->id,
        'expected_amount' => 10000,
        'starts_at' => '2025-06-01',
        'expires_at' => '2026-06-01',
    ]);

    // Register new enrollment today (early renewal)
    $newPayment = $this->service->registerEnrollment($student, 10000);

    expect($newPayment)->toBeInstanceOf(Payment::class);

    $enrollments = EnrollmentFee::where('student_id', $student->id)->get();
    expect($enrollments)->toHaveCount(2);

    $newEnrollment = $enrollments->where('payment_id', $newPayment->id)->first();
    expect($newEnrollment->starts_at->toDateString())->toBe('2026-06-01');
    expect($newEnrollment->expires_at->toDateString())->toBe('2027-06-01');
});

test('registerEnrollment starts from today when old enrollment expired', function () {
    Carbon::setTestNow('2026-04-07');

    $student = Student::factory()->create();

    // Create an old enrollment that expired in the past
    $oldPayment = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::create([
        'student_id' => $student->id,
        'payment_id' => $oldPayment->id,
        'expected_amount' => 10000,
        'starts_at' => '2025-01-01',
        'expires_at' => '2025-12-31',
    ]);

    // Register new enrollment today
    $newPayment = $this->service->registerEnrollment($student, 10000);

    $newEnrollment = EnrollmentFee::where('student_id', $student->id)
        ->where('payment_id', $newPayment->id)
        ->first();

    expect($newEnrollment->starts_at->toDateString())->toBe('2026-04-07');
    expect($newEnrollment->expires_at->toDateString())->toBe('2027-04-07');
});

test('registerEnrollment uses tenant setting for duration', function () {
    Carbon::setTestNow('2026-04-07');

    $student = Student::factory()->create();

    // Set tenant settings to use 6 months enrollment duration
    $this->tenant->update(['settings' => ['enrollment_duration_months' => 6]]);

    $payment = $this->service->registerEnrollment($student, 10000);

    $enrollment = EnrollmentFee::where('student_id', $student->id)->first();
    expect($enrollment->starts_at->toDateString())->toBe('2026-04-07');
    expect($enrollment->expires_at->toDateString())->toBe('2026-10-07');
});

// ============================================================================
// isEnrollmentExpired Tests
// ============================================================================

test('isEnrollmentExpired returns false with no enrollment', function () {
    $student = Student::factory()->create();

    $isExpired = $this->service->isEnrollmentExpired($student);

    expect($isExpired)->toBeFalse();
});

test('isEnrollmentExpired returns true when expired', function () {
    Carbon::setTestNow('2026-04-07');

    $student = Student::factory()->create();

    $payment = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'expected_amount' => 10000,
        'starts_at' => '2025-01-01',
        'expires_at' => '2026-01-01',
    ]);

    $isExpired = $this->service->isEnrollmentExpired($student);

    expect($isExpired)->toBeTrue();
});

test('isEnrollmentExpired returns false when active', function () {
    Carbon::setTestNow('2026-04-07');

    $student = Student::factory()->create();

    $payment = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'expected_amount' => 10000,
        'starts_at' => '2026-01-01',
        'expires_at' => '2027-01-01',
    ]);

    $isExpired = $this->service->isEnrollmentExpired($student);

    expect($isExpired)->toBeFalse();
});

// ============================================================================
// getLatestEnrollment Tests
// ============================================================================

test('getLatestEnrollment returns null with no enrollment', function () {
    $student = Student::factory()->create();

    $enrollment = $this->service->getLatestEnrollment($student);

    expect($enrollment)->toBeNull();
});

test('getLatestEnrollment returns most recent by expires_at', function () {
    $student = Student::factory()->create();

    $payment1 = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::create([
        'student_id' => $student->id,
        'payment_id' => $payment1->id,
        'expected_amount' => 10000,
        'starts_at' => '2025-01-01',
        'expires_at' => '2026-01-01',
    ]);

    $payment2 = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::create([
        'student_id' => $student->id,
        'payment_id' => $payment2->id,
        'expected_amount' => 10000,
        'starts_at' => '2026-01-01',
        'expires_at' => '2027-01-01',
    ]);

    $latest = $this->service->getLatestEnrollment($student);

    expect($latest)->not->toBeNull();
    expect($latest->payment_id)->toBe($payment2->id);
    expect($latest->expires_at->toDateString())->toBe('2027-01-01');
});
