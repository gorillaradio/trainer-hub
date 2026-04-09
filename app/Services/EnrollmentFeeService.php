<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\EnrollmentFee;
use App\Models\Payment;
use App\Models\Student;
use Carbon\Carbon;

class EnrollmentFeeService
{
    /**
     * Register a new enrollment fee for a student.
     *
     * If an active enrollment exists, starts from its expiry (early renewal).
     * Otherwise, starts from today.
     */
    public function registerEnrollment(Student $student, int $amount, ?string $notes = null): Payment
    {
        // Get the duration from tenant settings, default 12 months
        $durationMonths = tenant()?->settings['enrollment_duration_months'] ?? 12;

        // Determine the start date
        $latestEnrollment = $this->getLatestEnrollment($student);
        if ($latestEnrollment && $latestEnrollment->expires_at->isFuture()) {
            $startsAt = $latestEnrollment->expires_at;
        } else {
            $startsAt = Carbon::today();
        }

        // Calculate expiry date
        $expiresAt = $startsAt->copy()->addMonths($durationMonths);

        // Create the payment
        $payment = Payment::create([
            'student_id' => $student->id,
            'amount' => $amount,
            'payment_method' => PaymentMethod::Cash,
            'paid_at' => now(),
            'notes' => $notes,
        ]);

        // Create the enrollment fee
        EnrollmentFee::create([
            'student_id' => $student->id,
            'payment_id' => $payment->id,
            'expected_amount' => $amount,
            'starts_at' => $startsAt->toDateString(),
            'expires_at' => $expiresAt->toDateString(),
        ]);

        return $payment;
    }

    /**
     * Check if a student's enrollment has expired.
     *
     * Returns false if they have never enrolled.
     * Returns true only if latest enrollment is in the past.
     */
    public function isEnrollmentExpired(Student $student): bool
    {
        $latest = $this->getLatestEnrollment($student);

        if ($latest === null) {
            return false;
        }

        return $latest->expires_at->isPast();
    }

    /**
     * Check if a student has a currently valid enrollment.
     */
    public function hasValidEnrollment(Student $student): bool
    {
        $latest = $this->getLatestEnrollment($student);

        if ($latest === null) {
            return false;
        }

        return $latest->expires_at->isFuture();
    }

    /**
     * Get the latest enrollment fee for a student.
     */
    public function getLatestEnrollment(Student $student): ?EnrollmentFee
    {
        return $student->enrollmentFees()
            ->orderByDesc('expires_at')
            ->first();
    }
}
