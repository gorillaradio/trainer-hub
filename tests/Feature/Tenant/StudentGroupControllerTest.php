<?php

use App\Models\Group;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->tenant = Tenant::factory()->create(['owner_id' => $this->user->id]);
    $this->user->update(['current_tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);

    tenancy()->initialize($this->tenant);
});

afterEach(function () {
    tenancy()->end();
});

// ─── attach ──────────────────────────────────────────────────────────────────

test('attach aggiunge uno studente a un gruppo', function () {
    $student = Student::factory()->create();
    $group = Group::factory()->create();

    $response = $this->post("/app/{$this->tenant->slug}/students/{$student->id}/groups", [
        'group_id' => $group->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('group_student', [
        'student_id' => $student->id,
        'group_id'   => $group->id,
    ]);
});

test('attach non duplica un assegnazione esistente', function () {
    $student = Student::factory()->create();
    $group = Group::factory()->create();
    $student->groups()->attach($group->id);

    $response = $this->post("/app/{$this->tenant->slug}/students/{$student->id}/groups", [
        'group_id' => $group->id,
    ]);

    $response->assertRedirect();
    expect($student->fresh()->groups()->where('group_id', $group->id)->count())->toBe(1);
});

test('attach valida che group_id sia obbligatorio', function () {
    $student = Student::factory()->create();

    $response = $this->post("/app/{$this->tenant->slug}/students/{$student->id}/groups", []);

    $response->assertSessionHasErrors(['group_id']);
});

test('attach valida che group_id sia un uuid', function () {
    $student = Student::factory()->create();

    $response = $this->post("/app/{$this->tenant->slug}/students/{$student->id}/groups", [
        'group_id' => 'not-a-uuid',
    ]);

    $response->assertSessionHasErrors(['group_id']);
});

test('attach valida che group_id esista nella tabella groups', function () {
    $student = Student::factory()->create();

    $response = $this->post("/app/{$this->tenant->slug}/students/{$student->id}/groups", [
        'group_id' => '00000000-0000-0000-0000-000000000000',
    ]);

    $response->assertSessionHasErrors(['group_id']);
});

// ─── detach ──────────────────────────────────────────────────────────────────

test('detach rimuove uno studente da un gruppo', function () {
    $student = Student::factory()->create();
    $group = Group::factory()->create();
    $student->groups()->attach($group->id);

    $response = $this->delete("/app/{$this->tenant->slug}/students/{$student->id}/groups/{$group->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('group_student', [
        'student_id' => $student->id,
        'group_id'   => $group->id,
    ]);
});

test('detach non fallisce se lo studente non era nel gruppo', function () {
    $student = Student::factory()->create();
    $group = Group::factory()->create();

    $response = $this->delete("/app/{$this->tenant->slug}/students/{$student->id}/groups/{$group->id}");

    $response->assertRedirect();
});

// ─── Authorization ────────────────────────────────────────────────────────────

test('utente non autenticato non può assegnare gruppi', function () {
    $student = Student::factory()->create();
    $group = Group::factory()->create();

    auth()->logout();

    $this->post("/app/{$this->tenant->slug}/students/{$student->id}/groups", [
        'group_id' => $group->id,
    ])->assertRedirect('/login');
});

// ─── Tenant isolation ─────────────────────────────────────────────────────────

test('un utente non può accedere ai gruppi di un altro tenant', function () {
    $otherUser = User::factory()->create();
    $otherTenant = Tenant::factory()->create(['owner_id' => $otherUser->id]);

    tenancy()->end();
    tenancy()->initialize($otherTenant);
    $otherGroup = Group::factory()->create();
    $otherStudent = Student::factory()->create();
    tenancy()->end();
    tenancy()->initialize($this->tenant);

    // Current user tries to attach a group from another tenant to a student from another tenant
    $response = $this->post("/app/{$otherTenant->slug}/students/{$otherStudent->id}/groups", [
        'group_id' => $otherGroup->id,
    ]);

    $response->assertForbidden();
});
