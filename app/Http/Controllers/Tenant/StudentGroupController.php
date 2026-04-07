<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentGroupController extends Controller
{
    public function attach(Request $request, Student $student)
    {
        $this->authorize('update', $student);
        $validated = $request->validate([
            'group_id' => ['required', 'uuid', Rule::exists('groups', 'id')->where('tenant_id', tenant('slug'))],
        ]);
        if (! $student->groups()->where('group_id', $validated['group_id'])->exists()) {
            $student->groups()->attach($validated['group_id']);
        }
        return redirect()->back()->with('success', 'Studente aggiunto al gruppo.');
    }

    public function detach(Student $student, Group $group)
    {
        $this->authorize('update', $student);
        $student->groups()->detach($group->id);
        return redirect()->back()->with('success', 'Studente rimosso dal gruppo.');
    }

}
