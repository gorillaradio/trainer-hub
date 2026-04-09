<?php

use App\Models\EnrollmentFee;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EnrollmentFeeService;

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

test('hasValidEnrollment returns false when student has no enrollment', function () {
    $student = Student::factory()->create();
    $service = app(EnrollmentFeeService::class);

    expect($service->hasValidEnrollment($student))->toBeFalse();
});

test('hasValidEnrollment returns true when student has active enrollment', function () {
    $student = Student::factory()->create();
    $payment = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::factory()->create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'starts_at' => now()->subMonth(),
        'expires_at' => now()->addMonths(11),
    ]);
    $service = app(EnrollmentFeeService::class);

    expect($service->hasValidEnrollment($student))->toBeTrue();
});

test('hasValidEnrollment returns false when enrollment is expired', function () {
    $student = Student::factory()->create();
    $payment = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::factory()->create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'starts_at' => now()->subYear()->subMonth(),
        'expires_at' => now()->subMonth(),
    ]);
    $service = app(EnrollmentFeeService::class);

    expect($service->hasValidEnrollment($student))->toBeFalse();
});

test('new student without enrollment has effective_status pending', function () {
    $student = Student::factory()->create();

    expect($student->effective_status)->toBe('pending');
});

test('student with valid enrollment has effective_status active', function () {
    $student = Student::factory()->create();
    $payment = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::factory()->create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'starts_at' => now()->subMonth(),
        'expires_at' => now()->addMonths(11),
    ]);

    $student->refresh();
    expect($student->effective_status)->toBe('active');
});

test('student with expired enrollment has effective_status pending', function () {
    $student = Student::factory()->create();
    $payment = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::factory()->create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'starts_at' => now()->subYear()->subMonth(),
        'expires_at' => now()->subMonth(),
    ]);

    $student->refresh();
    expect($student->effective_status)->toBe('pending');
});

test('suspended student has effective_status suspended regardless of enrollment', function () {
    $student = Student::factory()->create();
    $student->forceFill(['status' => 'suspended'])->save();
    $payment = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::factory()->create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'starts_at' => now()->subMonth(),
        'expires_at' => now()->addMonths(11),
    ]);

    $student->refresh();
    expect($student->effective_status)->toBe('suspended');
});
