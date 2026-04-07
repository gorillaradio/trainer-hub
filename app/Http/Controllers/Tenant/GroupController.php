<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreGroupRequest;
use App\Http\Requests\Tenant\UpdateGroupRequest;
use App\Models\Group;
use App\Models\Student;
use Inertia\Inertia;

class GroupController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Group::class);

        $groups = Group::withCount('students')->orderBy('name')->get();

        return Inertia::render('Tenant/Group/Index', [
            'groups' => $groups,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Group::class);

        return Inertia::render('Tenant/Group/Create');
    }

    public function store(StoreGroupRequest $request)
    {
        $this->authorize('create', Group::class);

        Group::create($request->validated());

        return redirect()->route('tenant.groups.index', tenant('slug'))
            ->with('success', 'Gruppo creato con successo.');
    }

    public function edit(Group $group)
    {
        $this->authorize('update', $group);

        $group->load(['students:id,first_name,last_name']);

        return Inertia::render('Tenant/Group/Edit', [
            'group' => $group,
        ]);
    }

    public function update(UpdateGroupRequest $request, Group $group)
    {
        $this->authorize('update', $group);

        $group->update($request->validated());

        return redirect()->route('tenant.groups.index', tenant('slug'))
            ->with('success', 'Gruppo aggiornato con successo.');
    }

    public function destroy(Group $group)
    {
        $this->authorize('delete', $group);

        $group->delete();

        return redirect()->route('tenant.groups.index', tenant('slug'))
            ->with('success', 'Gruppo eliminato.');
    }
}
