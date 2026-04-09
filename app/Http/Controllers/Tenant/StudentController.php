<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreStudentRequest;
use App\Http\Requests\Tenant\UpdateStudentRequest;
use App\Models\Group;
use App\Models\Student;
use App\Services\EnrollmentFeeService;
use App\Services\FeeCalculationService;
use App\Services\MonthlyFeeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StudentController extends Controller
{
    private function statusOptions(): array
    {
        return array_map(
            fn (StudentStatus $s) => ['value' => $s->value, 'label' => $s->name],
            StudentStatus::cases()
        );
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Student::class);

        $query = Student::with('phoneContact');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $status = $request->input('status', 'active');
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $sortField = $request->input('sort', 'last_name');
        $sortDirection = $request->input('direction', 'asc');
        $allowedSorts = ['first_name', 'last_name', 'email', 'status', 'enrolled_at', 'created_at'];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        $students = $query->get();

        $paymentInfo = [];
        if ($request->boolean('payments')) {
            $students->load('groups');
            $feeCalculation = app(FeeCalculationService::class);
            $monthlyFeeService = app(MonthlyFeeService::class);

            $uncoveredCounts = $monthlyFeeService->getUncoveredCountsBatch($students);

            foreach ($students as $student) {
                $paymentInfo[$student->id] = [
                    'uncovered_count' => $uncoveredCounts[$student->id] ?? 0,
                    'has_rate' => $feeCalculation->getEffectiveRate($student) !== null,
                ];
            }
        }

        return Inertia::render('Tenant/Student/Index', [
            'students' => $students,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $status,
                'sort' => $sortField,
                'direction' => $sortDirection,
                'payments' => $request->boolean('payments'),
            ],
            'statuses' => $this->statusOptions(),
            'paymentInfo' => $paymentInfo,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Student::class);

        return Inertia::render('Tenant/Student/Create', [
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function store(StoreStudentRequest $request)
    {
        $this->authorize('create', Student::class);

        $validated = $request->validated();
        $contacts = $validated['emergency_contacts'] ?? [];
        $phoneContactIndex = $validated['phone_contact_index'] ?? null;
        unset($validated['emergency_contacts'], $validated['phone_contact_index']);

        $student = Student::create($validated);

        if (! empty($contacts)) {
            $createdContacts = [];
            foreach ($contacts as $contact) {
                $createdContacts[] = $student->emergencyContacts()->create($contact);
            }

            if ($phoneContactIndex !== null && isset($createdContacts[$phoneContactIndex])) {
                $student->update([
                    'phone_contact_id' => $createdContacts[$phoneContactIndex]->id,
                    'phone' => null,
                ]);
            }
        }

        return redirect()->route('tenant.students.index', tenant('slug'))
            ->with('success', 'Allievo aggiunto con successo.');
    }

    public function show(Student $student, FeeCalculationService $feeCalculation, MonthlyFeeService $monthlyFeeService, EnrollmentFeeService $enrollmentFeeService)
    {
        $this->authorize('view', $student);

        $student->load('emergencyContacts', 'phoneContact', 'groups');

        return Inertia::render('Tenant/Student/Show', [
            'student' => $student,
            'availableGroups' => Group::orderBy('name')->get(['id', 'name', 'color', 'monthly_fee_amount']),
            'paymentData' => [
                'effectiveRate' => $feeCalculation->getEffectiveRate($student),
                'balance' => $feeCalculation->getBalance($student),
                'uncoveredPeriods' => $monthlyFeeService->getUncoveredPeriods($student),
                'latestEnrollment' => $enrollmentFeeService->getLatestEnrollment($student),
                'enrollmentExpired' => $enrollmentFeeService->isEnrollmentExpired($student),
                'payments' => $student->payments()
                    ->with('monthlyFees', 'enrollmentFees')
                    ->orderByDesc('paid_at')
                    ->get(),
            ],
        ]);
    }

    public function edit(Student $student)
    {
        $this->authorize('update', $student);

        $student->load('emergencyContacts', 'phoneContact');

        return Inertia::render('Tenant/Student/Edit', [
            'student' => $student,
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function update(UpdateStudentRequest $request, Student $student)
    {
        $this->authorize('update', $student);

        $validated = $request->validated();
        $contacts = $validated['emergency_contacts'] ?? [];
        $phoneContactIndex = $validated['phone_contact_index'] ?? null;
        unset($validated['emergency_contacts'], $validated['phone_contact_index']);

        // Sync emergency contacts: delete old, create new
        $student->emergencyContacts()->delete();

        $createdContacts = [];
        foreach ($contacts as $contact) {
            $createdContacts[] = $student->emergencyContacts()->create($contact);
        }

        if ($phoneContactIndex !== null && isset($createdContacts[$phoneContactIndex])) {
            $validated['phone_contact_id'] = $createdContacts[$phoneContactIndex]->id;
            $validated['phone'] = null;
        } else {
            $validated['phone_contact_id'] = null;
        }

        $student->update($validated);

        return redirect()->route('tenant.students.show', [tenant('slug'), $student])
            ->with('success', 'Allievo aggiornato con successo.');
    }

    public function suspend(Student $student)
    {
        $this->authorize('update', $student);

        if ($student->current_cycle_started_at) {
            $pastCycles = $student->past_cycles ?? [];
            $pastCycles[] = [
                'started_at' => $student->current_cycle_started_at->toDateString(),
                'ended_at' => now()->toDateString(),
                'reason' => 'suspended',
            ];
            $student->update([
                'status' => StudentStatus::Suspended,
                'current_cycle_started_at' => null,
                'past_cycles' => $pastCycles,
            ]);
        } else {
            $student->update(['status' => StudentStatus::Suspended]);
        }

        return redirect()->route('tenant.students.show', [tenant('slug'), $student])
            ->with('success', 'Allievo sospeso.');
    }

    public function archive(Student $student)
    {
        $this->authorize('update', $student);

        $student->update(['status' => StudentStatus::Inactive]);

        return redirect()->route('tenant.students.index', tenant('slug'))
            ->with('success', 'Allievo archiviato.');
    }

    public function reactivate(Student $student)
    {
        $this->authorize('update', $student);

        $student->update(['status' => StudentStatus::Active]);

        return redirect()->route('tenant.students.show', [tenant('slug'), $student])
            ->with('success', 'Allievo riattivato.');
    }

    public function search(Request $request)
    {
        $this->authorize('viewAny', Student::class);

        $query = Student::where('status', 'active')
            ->select('id', 'first_name', 'last_name');

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if ($excludeGroup = $request->input('exclude_group')) {
            $query->whereDoesntHave('groups', function ($q) use ($excludeGroup) {
                $q->where('groups.id', $excludeGroup);
            });
        }

        return response()->json(
            $query->orderBy('last_name')->orderBy('first_name')->limit(10)->get()
        );
    }

    public function destroy(Student $student)
    {
        $this->authorize('delete', $student);

        $student->delete();

        return redirect()->route('tenant.students.index', tenant('slug'))
            ->with('success', 'Allievo eliminato.');
    }
}
