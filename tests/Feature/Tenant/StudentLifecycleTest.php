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
    $student->update(['status' => 'suspended']);
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

test('enrollment payment transitions student from pending to active', function () {
    $student = Student::factory()->create();
    expect($student->effective_status)->toBe('pending');

    $service = app(EnrollmentFeeService::class);
    $service->registerEnrollment($student, 5000);

    $student->refresh();
    expect($student->effective_status)->toBe('active');
});

test('suspended student stays suspended even after enrollment payment', function () {
    $student = Student::factory()->create();
    $student->update(['status' => 'suspended']);

    $service = app(EnrollmentFeeService::class);
    $service->registerEnrollment($student, 5000);

    $student->refresh();
    expect($student->effective_status)->toBe('suspended');
});

test('reactivating suspended student with valid enrollment shows active', function () {
    $student = Student::factory()->create();
    $student->update(['status' => 'suspended']);

    $service = app(EnrollmentFeeService::class);
    $service->registerEnrollment($student, 5000);

    $this->put("/app/{$this->tenant->slug}/students/{$student->id}/reactivate");

    $student->refresh();
    expect($student->effective_status)->toBe('active');
});

test('reactivating suspended student without enrollment shows pending', function () {
    $student = Student::factory()->create();
    $student->update(['status' => 'suspended']);

    $this->put("/app/{$this->tenant->slug}/students/{$student->id}/reactivate");

    $student->refresh();
    expect($student->effective_status)->toBe('pending');
});

test('soft-deleted student is not visible in index', function () {
    $student = Student::factory()->create();
    $student->delete();

    $response = $this->get("/app/{$this->tenant->slug}/students?status=all");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('students', 0)
    );
});

test('tenant isolation: tenant A cannot see tenant B students', function () {
    Student::factory()->create();

    $otherUser = User::factory()->create();
    $otherTenant = Tenant::factory()->create(['owner_id' => $otherUser->id]);

    $response = $this->get("/app/{$otherTenant->slug}/students");

    $response->assertForbidden();
});

test('store does not accept status field', function () {
    $response = $this->post("/app/{$this->tenant->slug}/students", [
        'first_name' => 'Marco',
        'last_name' => 'Rossi',
        'status' => 'active',
    ]);

    $response->assertRedirect();
    $student = Student::where('first_name', 'Marco')->first();
    expect($student->getRawOriginal('status'))->toBeNull();
});
