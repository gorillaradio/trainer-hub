<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\MonthlyFee;
use App\Models\Payment;
use App\Models\Student;
use Carbon\Carbon;

class MonthlyFeeService
{
    public function __construct(
        private readonly FeeCalculationService $feeCalculationService,
    ) {}

    /**
     * Returns "YYYY-MM" strings for months where payment was expected
     * but no MonthlyFee record exists.
     *
     * @return list<string>
     */
    public function getUncoveredPeriods(Student $student): array
    {
        $anchor = $student->current_cycle_started_at;
        if (! $anchor) {
            return [];
        }

        $anchor = Carbon::parse($anchor);
        $today = Carbon::today();
        $anchorDay = $anchor->day;

        $coveredPeriods = $student->monthlyFees()->pluck('period')->flip()->all();

        $periods = [];
        $cursor = $anchor->copy()->startOfMonth();

        while (true) {
            $dueDate = $this->clampToMonth($cursor->year, $cursor->month, $anchorDay);
            if ($dueDate->isAfter($today)) {
                break;
            }
            $period = $cursor->format('Y-m');
            if (! isset($coveredPeriods[$period])) {
                $periods[] = $period;
            }
            $cursor->addMonth();
        }

        return $periods;
    }

    /**
     * Register a payment covering $months months, starting from oldest uncovered periods.
     */
    public function registerPayment(Student $student, int $months, int $amount, ?string $notes = null): Payment
    {
        $effectiveRate = $this->feeCalculationService->getEffectiveRate($student);

        // Set cycle start on first payment
        if ($student->current_cycle_started_at === null) {
            $student->update(['current_cycle_started_at' => Carbon::today()]);
            $student->refresh();
        }

        // Create the payment record
        $payment = Payment::create([
            'student_id' => $student->id,
            'amount' => $amount,
            'payment_method' => PaymentMethod::Cash,
            'paid_at' => now(),
            'notes' => $notes,
        ]);

        $periodsToCover = $this->getPeriodsToCover($student, $months);

        $expectedPerFee = $effectiveRate ?? (int) round($amount / count($periodsToCover));

        $anchorDay = Carbon::parse($student->current_cycle_started_at)->day;

        foreach ($periodsToCover as $period) {
            [$year, $month] = array_map('intval', explode('-', $period));
            $dueDate = $this->clampToMonth($year, $month, $anchorDay);

            MonthlyFee::create([
                'student_id' => $student->id,
                'payment_id' => $payment->id,
                'period' => $period,
                'expected_amount' => $expectedPerFee,
                'due_date' => $dueDate->toDateString(),
            ]);
        }

        return $payment;
    }

    /**
     * Determine the periods to cover: take uncovered first (FIFO), extend into future if needed.
     *
     * @return list<string>
     */
    private function getPeriodsToCover(Student $student, int $months): array
    {
        $uncovered = $this->getUncoveredPeriods($student);
        $periods = array_slice($uncovered, 0, $months);

        if (count($periods) < $months) {
            $remaining = $months - count($periods);
            $allCovered = $student->monthlyFees()->pluck('period')->flip()->all();
            foreach ($periods as $p) {
                $allCovered[$p] = true;
            }
            $anchor = Carbon::parse($student->current_cycle_started_at);
            $cursor = $anchor->copy()->startOfMonth();
            while ($remaining > 0) {
                $period = $cursor->format('Y-m');
                if (! isset($allCovered[$period])) {
                    $periods[] = $period;
                    $allCovered[$period] = true;
                    $remaining--;
                }
                $cursor->addMonth();
            }
        }

        return $periods;
    }

    /**
     * Return a Carbon date clamped so the day never exceeds the month's last day.
     */
    private function clampToMonth(int $year, int $month, int $day): Carbon
    {
        $maxDay = Carbon::create($year, $month, 1)->daysInMonth;

        return Carbon::create($year, $month, min($day, $maxDay));
    }
}
