<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\RegisterEnrollmentPaymentRequest;
use App\Http\Requests\Tenant\RegisterMonthlyPaymentRequest;
use App\Models\Student;
use App\Services\EnrollmentFeeService;
use App\Services\FeeCalculationService;
use App\Services\MonthlyFeeService;
use Illuminate\Http\JsonResponse;

class StudentPaymentController extends Controller
{
    public function __construct(
        private MonthlyFeeService $monthlyFeeService,
        private EnrollmentFeeService $enrollmentFeeService,
        private FeeCalculationService $feeCalculationService,
    ) {}

    public function paymentData(Student $student): JsonResponse
    {
        $this->authorize('view', $student);

        $student->load('groups');

        $uncoveredPeriods = $this->monthlyFeeService->getUncoveredPeriods($student);

        return response()->json([
            'effectiveRate' => $this->feeCalculationService->getEffectiveRate($student),
            'balance' => $this->feeCalculationService->getBalance($student),
            'uncoveredPeriods' => $uncoveredPeriods,
            'uncoveredCount' => count($uncoveredPeriods),
            'latestEnrollment' => $this->enrollmentFeeService->getLatestEnrollment($student),
            'enrollmentExpired' => $this->enrollmentFeeService->isEnrollmentExpired($student),
        ]);
    }

    public function registerMonthly(RegisterMonthlyPaymentRequest $request, Student $student)
    {
        $this->authorize('update', $student);
        $validated = $request->validated();
        $this->monthlyFeeService->registerPayment($student, $validated['months'], $validated['amount'], $validated['notes'] ?? null);

        return redirect()->back()->with('success', 'Pagamento mensilità registrato.');
    }

    public function registerEnrollment(RegisterEnrollmentPaymentRequest $request, Student $student)
    {
        $this->authorize('update', $student);
        $validated = $request->validated();
        $this->enrollmentFeeService->registerEnrollment($student, $validated['amount'], $validated['notes'] ?? null);

        return redirect()->back()->with('success', 'Pagamento iscrizione registrato.');
    }
}
