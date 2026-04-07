<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreStudentRequest;
use App\Http\Requests\Tenant\UpdateStudentRequest;
use App\Models\Group;
use App\Models\Student;
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

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $sortField = $request->input('sort', 'last_name');
        $sortDirection = $request->input('direction', 'asc');
        $allowedSorts = ['first_name', 'last_name', 'email', 'status', 'enrolled_at', 'created_at'];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        $students = $query->get();

        return Inertia::render('Tenant/Student/Index', [
            'students' => $students,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
            'statuses' => $this->statusOptions(),
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

    public function show(Student $student)
    {
        $this->authorize('view', $student);

        $student->load('emergencyContacts', 'phoneContact', 'groups');

        return Inertia::render('Tenant/Student/Show', [
            'student' => $student,
            'availableGroups' => Group::orderBy('name')->get(['id', 'name', 'color', 'monthly_fee_amount']),
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

        $student->update(['status' => StudentStatus::Suspended]);

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

    public function destroy(Student $student)
    {
        $this->authorize('delete', $student);

        $student->delete();

        return redirect()->route('tenant.students.index', tenant('slug'))
            ->with('success', 'Allievo eliminato.');
    }
}
