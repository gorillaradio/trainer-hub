<?php

use App\Enums\StudentStatus;
use App\Models\EnrollmentFee;
use App\Models\Group;
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

// ─── payment-data endpoint ──────────────────────────────────────────────────

test('payment-data returns correct JSON structure', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 7));

    $group = Group::factory()->create(['monthly_fee_amount' => 5000]);
    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-03-01',
    ]);
    $student->groups()->attach($group->id, ['is_primary' => true]);

    $response = $this->getJson("/app/{$this->tenant->slug}/students/{$student->id}/payment-data");

    $response->assertOk();
    $response->assertJsonStructure([
        'effectiveRate',
        'balance',
        'uncoveredPeriods',
        'uncoveredCount',
        'latestEnrollment',
        'enrollmentExpired',
    ]);
    $response->assertJson([
        'effectiveRate' => 5000,
        'enrollmentExpired' => false,
        'latestEnrollment' => null,
    ]);
    expect($response->json('uncoveredCount'))->toBeGreaterThan(0);
    expect($response->json('uncoveredCount'))->toBe(count($response->json('uncoveredPeriods')));
});

test('payment-data returns null effectiveRate when student has no groups', function () {
    $student = Student::factory()->create();

    $response = $this->getJson("/app/{$this->tenant->slug}/students/{$student->id}/payment-data");

    $response->assertOk();
    $response->assertJson([
        'effectiveRate' => null,
        'uncoveredCount' => 0,
    ]);
});

test('payment-data requires authentication', function () {
    $student = Student::factory()->create();

    auth()->logout();

    $response = $this->getJson("/app/{$this->tenant->slug}/students/{$student->id}/payment-data");

    $response->assertUnauthorized();
});

test('payment-data respects tenant isolation', function () {
    $otherUser = User::factory()->create();
    $otherTenant = Tenant::factory()->create(['owner_id' => $otherUser->id]);

    tenancy()->end();
    tenancy()->initialize($otherTenant);
    $otherStudent = Student::factory()->create();
    tenancy()->end();
    tenancy()->initialize($this->tenant);

    $response = $this->getJson("/app/{$otherTenant->slug}/students/{$otherStudent->id}/payment-data");

    $response->assertForbidden();
});

// ─── index with payments toggle ─────────────────────────────────────────────

test('index with payments=1 includes paymentInfo', function () {
    $group = Group::factory()->create(['monthly_fee_amount' => 5000]);
    $student = Student::factory()->create([
        'current_cycle_started_at' => now()->subMonths(2)->toDateString(),
    ]);
    $student->groups()->attach($group->id, ['is_primary' => true]);

    $response = $this->get("/app/{$this->tenant->slug}/students?status=all&payments=1");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/Student/Index')
        ->has('paymentInfo')
        ->where("paymentInfo.{$student->id}.has_rate", true)
        ->where("paymentInfo.{$student->id}.uncovered_count", fn ($value) => $value >= 0)
    );
});

test('index without payments param returns empty paymentInfo', function () {
    Student::factory()->create();

    $response = $this->get("/app/{$this->tenant->slug}/students");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('paymentInfo', [])
    );
});

test('index filters include payments flag', function () {
    $response = $this->get("/app/{$this->tenant->slug}/students?payments=1");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('filters.payments', true)
    );
});

// ─── index default status filter ────────────────────────────────────────────

test('index defaults to status=active filter', function () {
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

    // 1 pending student (no enrollment)
    Student::factory()->create();

    // 1 suspended student
    Student::factory()->suspended()->create();

    $response = $this->get("/app/{$this->tenant->slug}/students");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('students', 2)
        ->where('filters.status', 'active')
    );
});

test('index with status=all shows all students', function () {
    // 2 students with valid enrollment
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

    // 1 pending student (no enrollment)
    Student::factory()->create();

    // 1 suspended student
    Student::factory()->suspended()->create();

    $response = $this->get("/app/{$this->tenant->slug}/students?status=all");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('students', 4)
        ->where('filters.status', 'all')
    );
});

test('index paymentInfo has_rate is false when student has no groups', function () {
    $student = Student::factory()->create();

    $response = $this->get("/app/{$this->tenant->slug}/students?status=all&payments=1");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where("paymentInfo.{$student->id}.has_rate", false)
        ->where("paymentInfo.{$student->id}.uncovered_count", 0)
    );
});
