<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreStudentRequest;
use App\Http\Requests\Tenant\UpdateStudentRequest;
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

        $query = Student::query();

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

        $students = $query->paginate(15)->withQueryString();

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

        Student::create($request->validated());

        return redirect()->route('tenant.students.index', tenant('slug'))
            ->with('success', 'Allievo aggiunto con successo.');
    }

    public function show(Student $student)
    {
        $this->authorize('view', $student);

        return Inertia::render('Tenant/Student/Show', [
            'student' => $student,
        ]);
    }

    public function edit(Student $student)
    {
        $this->authorize('update', $student);

        return Inertia::render('Tenant/Student/Edit', [
            'student' => $student,
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function update(UpdateStudentRequest $request, Student $student)
    {
        $this->authorize('update', $student);

        $student->update($request->validated());

        return redirect()->route('tenant.students.show', [tenant('slug'), $student])
            ->with('success', 'Allievo aggiornato con successo.');
    }

    public function destroy(Student $student)
    {
        $this->authorize('delete', $student);

        $student->delete();

        return redirect()->route('tenant.students.index', tenant('slug'))
            ->with('success', 'Allievo archiviato con successo.');
    }
}
