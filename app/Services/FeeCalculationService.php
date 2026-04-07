<?php

namespace App\Services;

use App\Models\Student;

class FeeCalculationService
{
    /**
     * Get the effective monthly rate in cents.
     *
     * Priority:
     * 1. If $student->monthly_fee_override is not null, return it
     * 2. If student has a group with pivot.is_primary === true, return that group's monthly_fee_amount
     * 3. If student has groups but none primary, return MIN(monthly_fee_amount) across groups
     * 4. If no groups and no override, return null
     */
    public function getEffectiveRate(Student $student): ?int
    {
        // Check for override first
        if ($student->monthly_fee_override !== null) {
            return $student->monthly_fee_override;
        }

        // Load groups if not already loaded
        $groups = $student->relationLoaded('groups') ? $student->groups : $student->groups()->get();

        if ($groups->isEmpty()) {
            return null;
        }

        // Check for primary group
        $primaryGroup = $groups->firstWhere('pivot.is_primary', true);
        if ($primaryGroup) {
            return $primaryGroup->monthly_fee_amount;
        }

        // Return minimum fee amount
        return $groups->min('monthly_fee_amount');
    }

    /**
     * Get the running balance in cents.
     *
     * Positive = credit, negative = debt.
     * Balance = Total Paid - Total Monthly Expected - Total Enrollment Expected
     */
    public function getBalance(Student $student): int
    {
        $totalPaid = $student->payments()->sum('amount');
        $totalMonthlyExpected = $student->monthlyFees()->sum('expected_amount');
        $totalEnrollmentExpected = $student->enrollmentFees()->sum('expected_amount');

        return $totalPaid - $totalMonthlyExpected - $totalEnrollmentExpected;
    }
}
