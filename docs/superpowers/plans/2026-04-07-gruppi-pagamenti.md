# Gruppi e Pagamenti — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build group management, monthly fee tracking with FIFO payment logic, enrollment fee tracking with expiry, and a unified payment registration system for trainers managing cash payments.

**Architecture:** Fee-centric model with three core tables: `groups` (fee source), `payments` (cash transaction), and restructured `monthly_fees`/`enrollment_fees` (coverage records linked to payments). Services encapsulate all business logic (fee calculation, payment registration, cycle management). Frontend lives primarily in Student/Show with tabs for groups and payments.

**Tech Stack:** Laravel 12, PHP 8.3, React 19, Inertia.js 2.x, TypeScript, shadcn/ui, Tailwind CSS 4, Pest (testing)

**Design Spec:** `docs/superpowers/specs/2026-04-07-gruppi-pagamenti-design.md`

---

## File Structure

### New files

| Path | Responsibility |
|------|---------------|
| `database/migrations/2026_04_07_000001_create_groups_table.php` | Groups schema |
| `database/migrations/2026_04_07_000002_create_group_student_table.php` | Pivot schema |
| `database/migrations/2026_04_07_000003_create_payments_table.php` | Payment transactions schema |
| `database/migrations/2026_04_07_000004_restructure_monthly_fees_table.php` | Drop & recreate monthly_fees |
| `database/migrations/2026_04_07_000005_restructure_enrollment_fees_table.php` | Drop & recreate enrollment_fees |
| `database/migrations/2026_04_07_000006_add_fee_columns_to_students_table.php` | monthly_fee_override, current_cycle_started_at, past_cycles |
| `app/Models/Group.php` | Group model |
| `app/Models/Payment.php` | Payment transaction model |
| `database/factories/GroupFactory.php` | Group factory |
| `database/factories/PaymentFactory.php` | Payment factory |
| `app/Services/FeeCalculationService.php` | Effective rate + balance logic |
| `app/Services/MonthlyFeeService.php` | Monthly payment registration + FIFO |
| `app/Services/EnrollmentFeeService.php` | Enrollment registration + renewal |
| `app/Http/Controllers/Tenant/GroupController.php` | Group CRUD |
| `app/Http/Controllers/Tenant/StudentGroupController.php` | Assign/remove/set-primary |
| `app/Http/Controllers/Tenant/StudentPaymentController.php` | Register monthly/enrollment payments |
| `app/Http/Requests/Tenant/StoreGroupRequest.php` | Group validation |
| `app/Http/Requests/Tenant/UpdateGroupRequest.php` | Group validation |
| `app/Http/Requests/Tenant/RegisterMonthlyPaymentRequest.php` | Monthly payment validation |
| `app/Http/Requests/Tenant/RegisterEnrollmentPaymentRequest.php` | Enrollment payment validation |
| `app/Policies/GroupPolicy.php` | Group authorization |
| `tests/Feature/Tenant/GroupControllerTest.php` | Group CRUD tests |
| `tests/Feature/Tenant/StudentGroupControllerTest.php` | Group assignment tests |
| `tests/Feature/Tenant/StudentPaymentControllerTest.php` | Payment registration tests |
| `tests/Unit/Services/FeeCalculationServiceTest.php` | Fee calculation unit tests |
| `tests/Unit/Services/MonthlyFeeServiceTest.php` | Monthly fee unit tests |
| `tests/Unit/Services/EnrollmentFeeServiceTest.php` | Enrollment fee unit tests |
| `resources/js/types/group.ts` | Group TypeScript types |
| `resources/js/types/payment.ts` | Payment TypeScript types |
| `resources/js/pages/Tenant/Group/Index.tsx` | Groups list page |
| `resources/js/pages/Tenant/Group/Create.tsx` | Group create page |
| `resources/js/pages/Tenant/Group/Edit.tsx` | Group edit page |
| `resources/js/components/group-form.tsx` | Shared group form |
| `resources/js/components/student-groups-card.tsx` | Groups card in Student/Show |
| `resources/js/components/student-payments-tab.tsx` | Payments tab in Student/Show |
| `resources/js/components/register-monthly-dialog.tsx` | Monthly payment dialog |
| `resources/js/components/register-enrollment-dialog.tsx` | Enrollment payment dialog |

### Modified files

| Path | Changes |
|------|---------|
| `app/Models/Student.php` | Add groups relation, fee columns, past_cycles cast, remove unpaidFees |
| `app/Models/MonthlyFee.php` | Restructure fillable/casts/relations for new schema |
| `app/Models/EnrollmentFee.php` | Restructure fillable/casts/relations for new schema |
| `app/Enums/PaymentMethod.php` | Keep only Cash (remove Transfer, Card, Online) |
| `app/Http/Controllers/Tenant/StudentController.php` | Extend show() with payment data, extend suspend/reactivate with cycle logic |
| `routes/tenant.php` | Add group, student-group, student-payment routes |
| `resources/js/types/index.ts` | Export group + payment types |
| `resources/js/types/student.ts` | Add group/payment fields to Student type |
| `resources/js/lib/tenant-nav.ts` | Add Gruppi nav item |
| `resources/js/pages/Tenant/Student/Show.tsx` | Add tabs (Dati personali, Pagamenti), groups card |
| `database/factories/StudentFactory.php` | Add new nullable columns |

---

### Task 1: Database Migrations & Models

**Files:**
- Create: `database/migrations/2026_04_07_000001_create_groups_table.php`
- Create: `database/migrations/2026_04_07_000002_create_group_student_table.php`
- Create: `database/migrations/2026_04_07_000003_create_payments_table.php`
- Create: `database/migrations/2026_04_07_000004_restructure_monthly_fees_table.php`
- Create: `database/migrations/2026_04_07_000005_restructure_enrollment_fees_table.php`
- Create: `database/migrations/2026_04_07_000006_add_fee_columns_to_students_table.php`
- Create: `app/Models/Group.php`
- Create: `app/Models/Payment.php`
- Modify: `app/Models/Student.php`
- Modify: `app/Models/MonthlyFee.php`
- Modify: `app/Models/EnrollmentFee.php`
- Modify: `app/Enums/PaymentMethod.php`
- Create: `database/factories/GroupFactory.php`
- Create: `database/factories/PaymentFactory.php`
- Modify: `database/factories/StudentFactory.php`

- [ ] **Step 1: Create groups migration**

```php
<?php
// database/migrations/2026_04_07_000001_create_groups_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('slug')->on('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 7); // hex #RRGGBB
            $table->integer('monthly_fee_amount'); // cents
            $table->timestamps();

            $table->index(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
```

- [ ] **Step 2: Create group_student pivot migration**

```php
<?php
// database/migrations/2026_04_07_000002_create_group_student_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_student', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('slug')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'group_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_student');
    }
};
```

- [ ] **Step 3: Create payments migration**

```php
<?php
// database/migrations/2026_04_07_000003_create_payments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('slug')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->integer('amount'); // cents
            $table->string('payment_method');
            $table->timestamp('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'student_id']);
            $table->index(['tenant_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
```

- [ ] **Step 4: Restructure monthly_fees migration**

No production data exists, so drop and recreate.

```php
<?php
// database/migrations/2026_04_07_000004_restructure_monthly_fees_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('monthly_fees');

        Schema::create('monthly_fees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('slug')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('period'); // "2026-04"
            $table->integer('expected_amount'); // cents
            $table->date('due_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'student_id', 'period']);
            $table->index(['tenant_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_fees');

        // Recreate original schema
        Schema::create('monthly_fees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('slug')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->integer('amount');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('period');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'student_id', 'period']);
            $table->index(['tenant_id', 'due_date', 'paid_at']);
        });
    }
};
```

- [ ] **Step 5: Restructure enrollment_fees migration**

```php
<?php
// database/migrations/2026_04_07_000005_restructure_enrollment_fees_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('enrollment_fees');

        Schema::create('enrollment_fees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('slug')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->integer('expected_amount'); // cents
            $table->date('starts_at');
            $table->date('expires_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'student_id']);
            $table->index(['tenant_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_fees');

        Schema::create('enrollment_fees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('slug')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->integer('amount');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('academic_year');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'student_id', 'academic_year']);
        });
    }
};
```

- [ ] **Step 6: Add fee columns to students migration**

```php
<?php
// database/migrations/2026_04_07_000006_add_fee_columns_to_students_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->integer('monthly_fee_override')->nullable()->after('enrolled_at');
            $table->date('current_cycle_started_at')->nullable()->after('monthly_fee_override');
            $table->json('past_cycles')->nullable()->after('current_cycle_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['monthly_fee_override', 'current_cycle_started_at', 'past_cycles']);
        });
    }
};
```

- [ ] **Step 7: Run migrations**

Run: `php artisan migrate`

Expected: All 6 migrations run successfully.

- [ ] **Step 8: Create Group model**

```php
<?php
// app/Models/Group.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Group extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'name', 'description', 'color', 'monthly_fee_amount',
    ];

    protected $casts = [
        'monthly_fee_amount' => 'integer',
    ];

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'group_student')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}
```

- [ ] **Step 9: Create Payment model**

```php
<?php
// app/Models/Payment.php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Payment extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'student_id', 'amount', 'payment_method', 'paid_at', 'notes',
    ];

    protected $casts = [
        'amount' => 'integer',
        'payment_method' => PaymentMethod::class,
        'paid_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function monthlyFees(): HasMany
    {
        return $this->hasMany(MonthlyFee::class);
    }

    public function enrollmentFees(): HasMany
    {
        return $this->hasMany(EnrollmentFee::class);
    }
}
```

- [ ] **Step 10: Update Student model**

Replace the full content of `app/Models/Student.php`:

```php
<?php

namespace App\Models;

use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Student extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone',
        'date_of_birth', 'fiscal_code', 'address',
        'phone_contact_id',
        'notes', 'status', 'enrolled_at',
        'monthly_fee_override', 'current_cycle_started_at', 'past_cycles',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'enrolled_at' => 'date:Y-m-d',
        'current_cycle_started_at' => 'date:Y-m-d',
        'status' => StudentStatus::class,
        'monthly_fee_override' => 'integer',
        'past_cycles' => 'array',
    ];

    protected $appends = ['effective_phone'];

    protected function effectivePhone(): Attribute
    {
        return Attribute::get(function () {
            if ($this->phone_contact_id && $this->relationLoaded('phoneContact') && $this->phoneContact) {
                return $this->phoneContact->phone;
            }

            if ($this->phone_contact_id) {
                return $this->phoneContact?->phone;
            }

            return $this->phone;
        });
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    public function phoneContact(): BelongsTo
    {
        return $this->belongsTo(EmergencyContact::class, 'phone_contact_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_student')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function enrollmentFees(): HasMany
    {
        return $this->hasMany(EnrollmentFee::class);
    }

    public function monthlyFees(): HasMany
    {
        return $this->hasMany(MonthlyFee::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function expiringDocuments(): HasMany
    {
        return $this->documents()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30));
    }
}
```

Key changes: added `groups()` relation, `payments()` relation, fee columns to fillable/casts, removed `unpaidFees()` (no longer applicable with new schema).

- [ ] **Step 11: Update MonthlyFee model**

Replace the full content of `app/Models/MonthlyFee.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class MonthlyFee extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'student_id', 'payment_id', 'period',
        'expected_amount', 'due_date', 'notes',
    ];

    protected $casts = [
        'expected_amount' => 'integer',
        'due_date' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
```

- [ ] **Step 12: Update EnrollmentFee model**

Replace the full content of `app/Models/EnrollmentFee.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class EnrollmentFee extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'student_id', 'payment_id', 'expected_amount',
        'starts_at', 'expires_at', 'notes',
    ];

    protected $casts = [
        'expected_amount' => 'integer',
        'starts_at' => 'date',
        'expires_at' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
```

- [ ] **Step 13: Simplify PaymentMethod enum**

Replace the full content of `app/Enums/PaymentMethod.php`:

```php
<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
}
```

- [ ] **Step 14: Create GroupFactory**

```php
<?php
// database/factories/GroupFactory.php

namespace Database\Factories;

use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Group> */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'color' => fake()->hexColor(),
            'monthly_fee_amount' => fake()->randomElement([3000, 4000, 5000, 6000]),
        ];
    }
}
```

- [ ] **Step 15: Create PaymentFactory**

```php
<?php
// database/factories/PaymentFactory.php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'amount' => fake()->randomElement([3000, 4000, 5000, 6000]),
            'payment_method' => PaymentMethod::Cash,
            'paid_at' => now(),
            'notes' => null,
        ];
    }
}
```

- [ ] **Step 16: Update StudentFactory**

Add new nullable columns to `database/factories/StudentFactory.php`. In the `definition()` method, add after the `enrolled_at` line:

```php
'monthly_fee_override' => null,
'current_cycle_started_at' => null,
'past_cycles' => null,
```

- [ ] **Step 17: Run existing tests to verify nothing broke**

Run: `php artisan test`

Expected: All existing tests pass. The `unpaidFees()` method was removed from Student, but it's not used in any tests or controllers.

- [ ] **Step 18: Commit**

```bash
git add database/migrations/2026_04_07_*.php app/Models/ app/Enums/PaymentMethod.php database/factories/
git commit -m "feat: add groups, payments schema and restructure fee tables

New tables: groups, group_student, payments.
Restructured: monthly_fees (coverage linked to payment),
enrollment_fees (starts_at/expires_at replaces academic_year).
Added fee columns to students: monthly_fee_override,
current_cycle_started_at, past_cycles."
```

---

### Task 2: Group CRUD Backend

**Files:**
- Create: `app/Http/Controllers/Tenant/GroupController.php`
- Create: `app/Http/Requests/Tenant/StoreGroupRequest.php`
- Create: `app/Http/Requests/Tenant/UpdateGroupRequest.php`
- Create: `app/Policies/GroupPolicy.php`
- Modify: `routes/tenant.php`
- Create: `tests/Feature/Tenant/GroupControllerTest.php`

- [ ] **Step 1: Write GroupController tests**

```php
<?php
// tests/Feature/Tenant/GroupControllerTest.php

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

// --- INDEX ---

test('index mostra la lista gruppi', function () {
    Group::factory()->count(3)->create();

    $response = $this->get("/app/{$this->tenant->slug}/groups");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/Group/Index')
        ->has('groups', 3)
    );
});

test('index mostra il conteggio studenti per gruppo', function () {
    $group = Group::factory()->create();
    $students = Student::factory()->count(2)->create();
    $group->students()->attach($students->pluck('id'));

    $response = $this->get("/app/{$this->tenant->slug}/groups");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('groups.0.students_count', 2)
    );
});

// --- CREATE ---

test('create mostra il form', function () {
    $response = $this->get("/app/{$this->tenant->slug}/groups/create");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/Group/Create')
    );
});

// --- STORE ---

test('store crea un gruppo', function () {
    $response = $this->post("/app/{$this->tenant->slug}/groups", [
        'name' => 'Under 14',
        'description' => 'Gruppo under 14',
        'color' => '#FF5733',
        'monthly_fee_amount' => 40,
    ]);

    $response->assertRedirect("/app/{$this->tenant->slug}/groups");
    $this->assertDatabaseHas('groups', [
        'name' => 'Under 14',
        'color' => '#FF5733',
        'monthly_fee_amount' => 4000, // stored in cents
        'tenant_id' => $this->tenant->slug,
    ]);
});

test('store valida i campi obbligatori', function () {
    $response = $this->post("/app/{$this->tenant->slug}/groups", []);

    $response->assertSessionHasErrors(['name', 'color', 'monthly_fee_amount']);
});

test('store valida il formato colore', function () {
    $response = $this->post("/app/{$this->tenant->slug}/groups", [
        'name' => 'Test',
        'color' => 'rosso',
        'monthly_fee_amount' => 40,
    ]);

    $response->assertSessionHasErrors(['color']);
});

test('store valida importo positivo', function () {
    $response = $this->post("/app/{$this->tenant->slug}/groups", [
        'name' => 'Test',
        'color' => '#FF5733',
        'monthly_fee_amount' => 0,
    ]);

    $response->assertSessionHasErrors(['monthly_fee_amount']);
});

// --- EDIT ---

test('edit mostra il form con dati precompilati', function () {
    $group = Group::factory()->create();

    $response = $this->get("/app/{$this->tenant->slug}/groups/{$group->id}/edit");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/Group/Edit')
        ->has('group')
        ->where('group.id', $group->id)
    );
});

test('edit include la lista studenti del gruppo', function () {
    $group = Group::factory()->create();
    $student = Student::factory()->create();
    $group->students()->attach($student->id);

    $response = $this->get("/app/{$this->tenant->slug}/groups/{$group->id}/edit");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('group.students', 1)
    );
});

// --- UPDATE ---

test('update aggiorna un gruppo', function () {
    $group = Group::factory()->create(['name' => 'Vecchio Nome']);

    $response = $this->put("/app/{$this->tenant->slug}/groups/{$group->id}", [
        'name' => 'Nuovo Nome',
        'color' => '#00FF00',
        'monthly_fee_amount' => 50,
    ]);

    $response->assertRedirect("/app/{$this->tenant->slug}/groups");
    $this->assertDatabaseHas('groups', [
        'id' => $group->id,
        'name' => 'Nuovo Nome',
        'monthly_fee_amount' => 5000,
    ]);
});

// --- DESTROY ---

test('destroy elimina un gruppo', function () {
    $group = Group::factory()->create();

    $response = $this->delete("/app/{$this->tenant->slug}/groups/{$group->id}");

    $response->assertRedirect("/app/{$this->tenant->slug}/groups");
    $this->assertDatabaseMissing('groups', ['id' => $group->id]);
});

test('destroy rimuove le assegnazioni studenti', function () {
    $group = Group::factory()->create();
    $student = Student::factory()->create();
    $group->students()->attach($student->id);

    $this->delete("/app/{$this->tenant->slug}/groups/{$group->id}");

    $this->assertDatabaseMissing('group_student', ['group_id' => $group->id]);
});

// --- TENANT ISOLATION ---

test('un utente non può accedere ai gruppi di un altro tenant', function () {
    $otherUser = User::factory()->create();
    $otherTenant = Tenant::factory()->create(['owner_id' => $otherUser->id]);

    $response = $this->get("/app/{$otherTenant->slug}/groups");

    $response->assertForbidden();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Tenant/GroupControllerTest.php`

Expected: All tests FAIL (controller, routes, requests don't exist yet).

- [ ] **Step 3: Create GroupPolicy**

```php
<?php
// app/Policies/GroupPolicy.php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Group $group): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Group $group): bool
    {
        return true;
    }

    public function delete(User $user, Group $group): bool
    {
        return true;
    }
}
```

- [ ] **Step 4: Create StoreGroupRequest**

```php
<?php
// app/Http/Requests/Tenant/StoreGroupRequest.php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'monthly_fee_amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Convert euros to cents for storage
        if ($this->has('monthly_fee_amount') && is_numeric($this->monthly_fee_amount)) {
            $this->merge([
                'monthly_fee_amount' => (int) round($this->monthly_fee_amount * 100),
            ]);
        }
    }
}
```

- [ ] **Step 5: Create UpdateGroupRequest**

```php
<?php
// app/Http/Requests/Tenant/UpdateGroupRequest.php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'monthly_fee_amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('monthly_fee_amount') && is_numeric($this->monthly_fee_amount)) {
            $this->merge([
                'monthly_fee_amount' => (int) round($this->monthly_fee_amount * 100),
            ]);
        }
    }
}
```

- [ ] **Step 6: Create GroupController**

```php
<?php
// app/Http/Controllers/Tenant/GroupController.php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreGroupRequest;
use App\Http\Requests\Tenant\UpdateGroupRequest;
use App\Models\Group;
use Inertia\Inertia;

class GroupController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Group::class);

        $groups = Group::withCount('students')
            ->orderBy('name')
            ->get();

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

        $group->load('students:id,first_name,last_name');

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
```

- [ ] **Step 7: Add group routes to `routes/tenant.php`**

Add inside the existing `->group(function () { ... })` block, after the student reactivate route:

```php
Route::resource('groups', \App\Http\Controllers\Tenant\GroupController::class)
    ->except('show')
    ->names('tenant.groups');
```

Also add the use statement at the top of the file:

```php
use App\Http\Controllers\Tenant\GroupController;
```

- [ ] **Step 8: Run tests**

Run: `php artisan test tests/Feature/Tenant/GroupControllerTest.php`

Expected: All tests PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Tenant/GroupController.php app/Http/Requests/Tenant/StoreGroupRequest.php app/Http/Requests/Tenant/UpdateGroupRequest.php app/Policies/GroupPolicy.php routes/tenant.php tests/Feature/Tenant/GroupControllerTest.php
git commit -m "feat: add Group CRUD backend with tests"
```

---

### Task 3: Group CRUD Frontend

**Files:**
- Create: `resources/js/types/group.ts`
- Modify: `resources/js/types/index.ts`
- Create: `resources/js/components/group-form.tsx`
- Create: `resources/js/pages/Tenant/Group/Index.tsx`
- Create: `resources/js/pages/Tenant/Group/Create.tsx`
- Create: `resources/js/pages/Tenant/Group/Edit.tsx`
- Modify: `resources/js/lib/tenant-nav.ts`

- [ ] **Step 1: Create group TypeScript types**

```typescript
// resources/js/types/group.ts

export type Group = {
    id: string;
    name: string;
    description: string | null;
    color: string;
    monthly_fee_amount: number; // cents
    students_count?: number;
    students?: Array<{ id: string; first_name: string; last_name: string }>;
    created_at: string;
    updated_at: string;
};
```

- [ ] **Step 2: Export group types from index**

Add to `resources/js/types/index.ts`:

```typescript
export type * from './group';
```

- [ ] **Step 3: Add Gruppi to nav**

In `resources/js/lib/tenant-nav.ts`, add `Layers` import and the Gruppi nav item between Allievi and Pagamenti:

```typescript
import { CreditCard, FileText, Layers, LayoutGrid, Users } from 'lucide-react';
import type { NavItem } from '@/types';

export function getTenantNavItems(tenantSlug: string): NavItem[] {
    const prefix = `/app/${tenantSlug}`;

    return [
        { title: 'Dashboard', href: `${prefix}/dashboard`, icon: LayoutGrid },
        { title: 'Allievi', href: `${prefix}/students`, icon: Users },
        { title: 'Gruppi', href: `${prefix}/groups`, icon: Layers },
        { title: 'Pagamenti', href: `${prefix}/payments`, icon: CreditCard },
        { title: 'Documenti', href: `${prefix}/documents`, icon: FileText },
    ];
}
```

- [ ] **Step 4: Create group-form component**

```tsx
// resources/js/components/group-form.tsx

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import type { Group } from '@/types';
import { useForm } from '@inertiajs/react';
import { useTenant } from '@/hooks/use-tenant';
import type { FormEventHandler } from 'react';

type Props = {
    group?: Group;
    submitLabel: string;
};

export function GroupForm({ group, submitLabel }: Props) {
    const tenant = useTenant();
    const prefix = `/app/${tenant.slug}`;

    const form = useForm({
        name: group?.name ?? '',
        description: group?.description ?? '',
        color: group?.color ?? '#3B82F6',
        monthly_fee_amount: group ? (group.monthly_fee_amount / 100).toString() : '',
    });

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        if (group) {
            form.put(`${prefix}/groups/${group.id}`);
        } else {
            form.post(`${prefix}/groups`);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-6">
            <Field>
                <FieldLabel htmlFor="name">Nome *</FieldLabel>
                <Input
                    id="name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                />
                <FieldError message={form.errors.name} />
            </Field>

            <Field>
                <FieldLabel htmlFor="description">Descrizione</FieldLabel>
                <Textarea
                    id="description"
                    value={form.data.description}
                    onChange={(e) => form.setData('description', e.target.value)}
                    rows={3}
                />
                <FieldError message={form.errors.description} />
            </Field>

            <div className="grid gap-6 sm:grid-cols-2">
                <Field>
                    <FieldLabel htmlFor="color">Colore *</FieldLabel>
                    <div className="flex items-center gap-3">
                        <input
                            type="color"
                            id="color"
                            value={form.data.color}
                            onChange={(e) => form.setData('color', e.target.value)}
                            className="h-10 w-14 cursor-pointer rounded border"
                        />
                        <Input
                            value={form.data.color}
                            onChange={(e) => form.setData('color', e.target.value)}
                            placeholder="#3B82F6"
                            className="flex-1"
                        />
                    </div>
                    <FieldError message={form.errors.color} />
                </Field>

                <Field>
                    <FieldLabel htmlFor="monthly_fee_amount">Tariffa mensile (€) *</FieldLabel>
                    <Input
                        id="monthly_fee_amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        value={form.data.monthly_fee_amount}
                        onChange={(e) => form.setData('monthly_fee_amount', e.target.value)}
                        placeholder="40.00"
                    />
                    <FieldError message={form.errors.monthly_fee_amount} />
                </Field>
            </div>

            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
```

- [ ] **Step 5: Create Group Index page**

```tsx
// resources/js/pages/Tenant/Group/Index.tsx

import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import TenantLayout from '@/layouts/tenant-layout';
import { useTenant } from '@/hooks/use-tenant';
import type { Group } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { ReactElement } from 'react';

type Props = {
    groups: Group[];
};

function formatCurrency(cents: number): string {
    return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(cents / 100);
}

export default function GroupsIndex({ groups }: Props) {
    const tenant = useTenant();
    const prefix = `/app/${tenant.slug}`;

    return (
        <>
            <Head title="Gruppi" />
            <div className="mx-auto w-full max-w-2xl p-4">
                <PageHeader
                    sticky
                    title={<h1 className="text-2xl font-semibold">Gruppi</h1>}
                    actions={
                        <Button asChild>
                            <Link href={`${prefix}/groups/create`}>
                                <Plus data-icon="inline-start" />
                                Nuovo gruppo
                            </Link>
                        </Button>
                    }
                />

                {groups.length === 0 ? (
                    <p className="py-12 text-center text-muted-foreground">
                        Nessun gruppo. Crea il primo gruppo per iniziare.
                    </p>
                ) : (
                    <div className="flex flex-col gap-3">
                        {groups.map((group) => (
                            <Link key={group.id} href={`${prefix}/groups/${group.id}/edit`}>
                                <Card className="transition-colors hover:bg-muted/50">
                                    <CardContent className="flex items-center gap-4 py-4">
                                        <div
                                            className="size-4 shrink-0 rounded-full"
                                            style={{ backgroundColor: group.color }}
                                        />
                                        <div className="min-w-0 flex-1">
                                            <p className="font-medium">{group.name}</p>
                                            {group.description && (
                                                <p className="truncate text-sm text-muted-foreground">
                                                    {group.description}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Badge variant="secondary">
                                                {group.students_count ?? 0} allievi
                                            </Badge>
                                            <span className="text-sm font-medium">
                                                {formatCurrency(group.monthly_fee_amount)}/mese
                                            </span>
                                        </div>
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

GroupsIndex.layout = (page: ReactElement) => (
    <TenantLayout breadcrumbs={[{ title: 'Gruppi', href: '#' }]}>
        {page}
    </TenantLayout>
);
```

- [ ] **Step 6: Create Group Create page**

```tsx
// resources/js/pages/Tenant/Group/Create.tsx

import { GroupForm } from '@/components/group-form';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import TenantLayout from '@/layouts/tenant-layout';
import { Head } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactElement } from 'react';

export default function GroupsCreate() {
    return (
        <>
            <Head title="Nuovo gruppo" />
            <div className="mx-auto w-full max-w-2xl p-4">
                <PageHeader
                    sticky
                    title={<h1 className="text-2xl font-semibold">Nuovo gruppo</h1>}
                    actions={
                        <Button variant="outline" onClick={() => window.history.back()}>
                            <ArrowLeft data-icon="inline-start" />
                            Indietro
                        </Button>
                    }
                />
                <GroupForm submitLabel="Crea gruppo" />
            </div>
        </>
    );
}

GroupsCreate.layout = (page: ReactElement) => (
    <TenantLayout breadcrumbs={[
        { title: 'Gruppi', href: 'groups' },
        { title: 'Nuovo gruppo', href: '#' },
    ]}>
        {page}
    </TenantLayout>
);
```

- [ ] **Step 7: Create Group Edit page**

```tsx
// resources/js/pages/Tenant/Group/Edit.tsx

import { GroupForm } from '@/components/group-form';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import TenantLayout from '@/layouts/tenant-layout';
import { useTenant } from '@/hooks/use-tenant';
import type { Group } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import type { ReactElement } from 'react';

type Props = {
    group: Group;
};

export default function GroupsEdit({ group }: Props) {
    const tenant = useTenant();
    const prefix = `/app/${tenant.slug}`;

    function handleDelete() {
        if (confirm('Sei sicuro di voler eliminare questo gruppo?')) {
            router.delete(`${prefix}/groups/${group.id}`);
        }
    }

    return (
        <>
            <Head title={`Modifica ${group.name}`} />
            <div className="mx-auto w-full max-w-2xl p-4">
                <PageHeader
                    sticky
                    title={<h1 className="text-2xl font-semibold">Modifica gruppo</h1>}
                    actions={
                        <>
                            <Button variant="outline" onClick={() => window.history.back()}>
                                <ArrowLeft data-icon="inline-start" />
                                Indietro
                            </Button>
                            <Button variant="destructive" onClick={handleDelete}>
                                <Trash2 data-icon="inline-start" />
                                Elimina
                            </Button>
                        </>
                    }
                />

                <div className="flex flex-col gap-6">
                    <GroupForm group={group} submitLabel="Salva modifiche" />

                    {group.students && group.students.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Allievi nel gruppo ({group.students.length})</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-col gap-2">
                                    {group.students.map((student) => (
                                        <Link
                                            key={student.id}
                                            href={`${prefix}/students/${student.id}`}
                                            className="text-sm hover:underline"
                                        >
                                            {student.last_name} {student.first_name}
                                        </Link>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}

GroupsEdit.layout = (page: ReactElement<Props>) => {
    const { group } = page.props;
    return (
        <TenantLayout breadcrumbs={[
            { title: 'Gruppi', href: 'groups' },
            { title: group.name, href: '#' },
        ]}>
            {page}
        </TenantLayout>
    );
};
```

- [ ] **Step 8: Verify frontend builds**

Run: `npx tsc --noEmit`

Expected: No TypeScript errors.

- [ ] **Step 9: Run all tests**

Run: `php artisan test`

Expected: All tests pass.

- [ ] **Step 10: Commit**

```bash
git add resources/js/types/group.ts resources/js/types/index.ts resources/js/lib/tenant-nav.ts resources/js/components/group-form.tsx resources/js/pages/Tenant/Group/
git commit -m "feat: add Group CRUD frontend pages and nav"
```

---

### Task 4: FeeCalculationService

**Files:**
- Create: `app/Services/FeeCalculationService.php`
- Create: `tests/Unit/Services/FeeCalculationServiceTest.php`

- [ ] **Step 1: Write FeeCalculationService tests**

```php
<?php
// tests/Unit/Services/FeeCalculationServiceTest.php

use App\Models\Group;
use App\Models\Payment;
use App\Models\MonthlyFee;
use App\Models\EnrollmentFee;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FeeCalculationService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->tenant = Tenant::factory()->create(['owner_id' => $this->user->id]);
    tenancy()->initialize($this->tenant);
    $this->service = new FeeCalculationService();
});

afterEach(function () {
    tenancy()->end();
});

// --- getEffectiveRate ---

test('getEffectiveRate returns monthly_fee_override when set', function () {
    $student = Student::factory()->create(['monthly_fee_override' => 3500]);
    $group = Group::factory()->create(['monthly_fee_amount' => 5000]);
    $student->groups()->attach($group->id);

    expect($this->service->getEffectiveRate($student))->toBe(3500);
});

test('getEffectiveRate returns primary group fee when set', function () {
    $student = Student::factory()->create();
    $cheap = Group::factory()->create(['monthly_fee_amount' => 3000]);
    $expensive = Group::factory()->create(['monthly_fee_amount' => 6000]);
    $student->groups()->attach($cheap->id, ['is_primary' => false]);
    $student->groups()->attach($expensive->id, ['is_primary' => true]);

    $student->load('groups');
    expect($this->service->getEffectiveRate($student))->toBe(6000);
});

test('getEffectiveRate returns min group fee without primary', function () {
    $student = Student::factory()->create();
    $cheap = Group::factory()->create(['monthly_fee_amount' => 3000]);
    $expensive = Group::factory()->create(['monthly_fee_amount' => 6000]);
    $student->groups()->attach([$cheap->id, $expensive->id]);

    $student->load('groups');
    expect($this->service->getEffectiveRate($student))->toBe(3000);
});

test('getEffectiveRate returns null when no groups and no override', function () {
    $student = Student::factory()->create();

    $student->load('groups');
    expect($this->service->getEffectiveRate($student))->toBeNull();
});

test('getEffectiveRate with single group returns that fee', function () {
    $student = Student::factory()->create();
    $group = Group::factory()->create(['monthly_fee_amount' => 4000]);
    $student->groups()->attach($group->id);

    $student->load('groups');
    expect($this->service->getEffectiveRate($student))->toBe(4000);
});

// --- getBalance ---

test('getBalance returns zero with no payments', function () {
    $student = Student::factory()->create();

    expect($this->service->getBalance($student))->toBe(0);
});

test('getBalance returns negative when underpaid', function () {
    $student = Student::factory()->create();
    $payment = Payment::factory()->create(['student_id' => $student->id, 'amount' => 4500]);
    MonthlyFee::create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'period' => '2026-03',
        'expected_amount' => 5000,
        'due_date' => '2026-03-16',
    ]);

    expect($this->service->getBalance($student))->toBe(-500); // paid 45, owed 50
});

test('getBalance returns positive when overpaid', function () {
    $student = Student::factory()->create();
    $payment = Payment::factory()->create(['student_id' => $student->id, 'amount' => 5500]);
    MonthlyFee::create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'period' => '2026-03',
        'expected_amount' => 5000,
        'due_date' => '2026-03-16',
    ]);

    expect($this->service->getBalance($student))->toBe(500);
});

test('getBalance includes enrollment fees', function () {
    $student = Student::factory()->create();
    $payment = Payment::factory()->create(['student_id' => $student->id, 'amount' => 10000]);
    EnrollmentFee::create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'expected_amount' => 10000,
        'starts_at' => '2026-01-01',
        'expires_at' => '2027-01-01',
    ]);

    expect($this->service->getBalance($student))->toBe(0);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/Services/FeeCalculationServiceTest.php`

Expected: FAIL — `FeeCalculationService` does not exist.

- [ ] **Step 3: Implement FeeCalculationService**

```php
<?php
// app/Services/FeeCalculationService.php

namespace App\Services;

use App\Models\Student;

class FeeCalculationService
{
    /**
     * Get the effective monthly rate for a student.
     * Priority: override > primary group > min group > null.
     */
    public function getEffectiveRate(Student $student): ?int
    {
        if ($student->monthly_fee_override !== null) {
            return $student->monthly_fee_override;
        }

        $groups = $student->relationLoaded('groups')
            ? $student->groups
            : $student->groups()->get();

        if ($groups->isEmpty()) {
            return null;
        }

        $primary = $groups->firstWhere('pivot.is_primary', true);
        if ($primary) {
            return $primary->monthly_fee_amount;
        }

        return $groups->min('monthly_fee_amount');
    }

    /**
     * Get the running balance for a student.
     * Positive = credit, negative = debt.
     */
    public function getBalance(Student $student): int
    {
        $totalPaid = $student->payments()->sum('amount');
        $totalMonthlyExpected = $student->monthlyFees()->sum('expected_amount');
        $totalEnrollmentExpected = $student->enrollmentFees()->sum('expected_amount');

        return $totalPaid - $totalMonthlyExpected - $totalEnrollmentExpected;
    }
}
```

- [ ] **Step 4: Run tests**

Run: `php artisan test tests/Unit/Services/FeeCalculationServiceTest.php`

Expected: All tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/FeeCalculationService.php tests/Unit/Services/FeeCalculationServiceTest.php
git commit -m "feat: add FeeCalculationService with rate priority and balance"
```

---

### Task 5: Group-Student Assignment

**Files:**
- Create: `app/Http/Controllers/Tenant/StudentGroupController.php`
- Create: `tests/Feature/Tenant/StudentGroupControllerTest.php`
- Modify: `routes/tenant.php`
- Create: `resources/js/components/student-groups-card.tsx`
- Modify: `resources/js/pages/Tenant/Student/Show.tsx`
- Modify: `resources/js/types/student.ts`
- Modify: `app/Http/Controllers/Tenant/StudentController.php` (show method)

- [ ] **Step 1: Write StudentGroupController tests**

```php
<?php
// tests/Feature/Tenant/StudentGroupControllerTest.php

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

test('attach aggiunge uno studente a un gruppo', function () {
    $student = Student::factory()->create();
    $group = Group::factory()->create();

    $response = $this->post(
        "/app/{$this->tenant->slug}/students/{$student->id}/groups",
        ['group_id' => $group->id]
    );

    $response->assertRedirect();
    $this->assertDatabaseHas('group_student', [
        'student_id' => $student->id,
        'group_id' => $group->id,
        'is_primary' => false,
    ]);
});

test('attach non duplica assegnazione esistente', function () {
    $student = Student::factory()->create();
    $group = Group::factory()->create();
    $student->groups()->attach($group->id);

    $response = $this->post(
        "/app/{$this->tenant->slug}/students/{$student->id}/groups",
        ['group_id' => $group->id]
    );

    $response->assertRedirect();
    expect($student->groups()->count())->toBe(1);
});

test('detach rimuove uno studente da un gruppo', function () {
    $student = Student::factory()->create();
    $group = Group::factory()->create();
    $student->groups()->attach($group->id);

    $response = $this->delete(
        "/app/{$this->tenant->slug}/students/{$student->id}/groups/{$group->id}"
    );

    $response->assertRedirect();
    $this->assertDatabaseMissing('group_student', [
        'student_id' => $student->id,
        'group_id' => $group->id,
    ]);
});

test('setPrimary segna un gruppo come principale', function () {
    $student = Student::factory()->create();
    $group1 = Group::factory()->create();
    $group2 = Group::factory()->create();
    $student->groups()->attach($group1->id, ['is_primary' => true]);
    $student->groups()->attach($group2->id);

    $response = $this->put(
        "/app/{$this->tenant->slug}/students/{$student->id}/groups/{$group2->id}/primary"
    );

    $response->assertRedirect();
    $this->assertDatabaseHas('group_student', [
        'student_id' => $student->id,
        'group_id' => $group2->id,
        'is_primary' => true,
    ]);
    $this->assertDatabaseHas('group_student', [
        'student_id' => $student->id,
        'group_id' => $group1->id,
        'is_primary' => false,
    ]);
});

test('clearPrimary rimuove il gruppo principale', function () {
    $student = Student::factory()->create();
    $group = Group::factory()->create();
    $student->groups()->attach($group->id, ['is_primary' => true]);

    $response = $this->delete(
        "/app/{$this->tenant->slug}/students/{$student->id}/groups/primary"
    );

    $response->assertRedirect();
    $this->assertDatabaseHas('group_student', [
        'student_id' => $student->id,
        'group_id' => $group->id,
        'is_primary' => false,
    ]);
});

test('tenant isolation: non si accede ai gruppi di un altro tenant', function () {
    $otherUser = User::factory()->create();
    $otherTenant = Tenant::factory()->create(['owner_id' => $otherUser->id]);
    $student = Student::factory()->create();

    $response = $this->post(
        "/app/{$otherTenant->slug}/students/{$student->id}/groups",
        ['group_id' => 'fake-id']
    );

    $response->assertForbidden();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Tenant/StudentGroupControllerTest.php`

Expected: FAIL.

- [ ] **Step 3: Create StudentGroupController**

```php
<?php
// app/Http/Controllers/Tenant/StudentGroupController.php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentGroupController extends Controller
{
    public function attach(Request $request, Student $student)
    {
        $this->authorize('update', $student);

        $validated = $request->validate([
            'group_id' => ['required', 'uuid', 'exists:groups,id'],
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

    public function setPrimary(Student $student, Group $group)
    {
        $this->authorize('update', $student);

        // Clear all primary flags for this student
        $student->groups()->updateExistingPivot(
            $student->groups->pluck('id')->toArray(),
            ['is_primary' => false]
        );

        // Set the specified group as primary
        $student->groups()->updateExistingPivot($group->id, ['is_primary' => true]);

        return redirect()->back()->with('success', 'Gruppo principale aggiornato.');
    }

    public function clearPrimary(Student $student)
    {
        $this->authorize('update', $student);

        $student->groups()->updateExistingPivot(
            $student->groups->pluck('id')->toArray(),
            ['is_primary' => false]
        );

        return redirect()->back()->with('success', 'Gruppo principale rimosso.');
    }
}
```

- [ ] **Step 4: Add routes for group assignment**

In `routes/tenant.php`, add inside the group block, after the groups resource:

```php
use App\Http\Controllers\Tenant\StudentGroupController;

// Student group management
Route::post('students/{student}/groups', [StudentGroupController::class, 'attach'])
    ->name('tenant.students.groups.attach');
Route::delete('students/{student}/groups/primary', [StudentGroupController::class, 'clearPrimary'])
    ->name('tenant.students.groups.clear-primary');
Route::delete('students/{student}/groups/{group}', [StudentGroupController::class, 'detach'])
    ->name('tenant.students.groups.detach');
Route::put('students/{student}/groups/{group}/primary', [StudentGroupController::class, 'setPrimary'])
    ->name('tenant.students.groups.set-primary');
```

Note: the `clearPrimary` DELETE route must come BEFORE the `detach` DELETE route to avoid `primary` being matched as a `{group}` parameter.

- [ ] **Step 5: Update StudentController show() to load groups and available groups**

In `app/Http/Controllers/Tenant/StudentController.php`, modify the `show()` method:

```php
public function show(Student $student)
{
    $this->authorize('view', $student);

    $student->load('emergencyContacts', 'phoneContact', 'groups');

    return Inertia::render('Tenant/Student/Show', [
        'student' => $student,
        'availableGroups' => Group::orderBy('name')->get(['id', 'name', 'color', 'monthly_fee_amount']),
    ]);
}
```

Add the `Group` import at the top of the file:

```php
use App\Models\Group;
```

- [ ] **Step 6: Run tests**

Run: `php artisan test tests/Feature/Tenant/StudentGroupControllerTest.php`

Expected: All tests PASS.

- [ ] **Step 7: Update Student TypeScript type**

In `resources/js/types/student.ts`, add the groups field to the `Student` type. Add after `effective_phone`:

```typescript
groups?: Array<{
    id: string;
    name: string;
    color: string;
    monthly_fee_amount: number;
    pivot: { is_primary: boolean };
}>;
monthly_fee_override: number | null;
```

- [ ] **Step 8: Create student-groups-card component**

```tsx
// resources/js/components/student-groups-card.tsx

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTenant } from '@/hooks/use-tenant';
import type { Group } from '@/types';
import { router } from '@inertiajs/react';
import { Crown, Plus, X } from 'lucide-react';
import { useState } from 'react';

type StudentGroup = {
    id: string;
    name: string;
    color: string;
    monthly_fee_amount: number;
    pivot: { is_primary: boolean };
};

type Props = {
    studentId: string;
    groups: StudentGroup[];
    availableGroups: Group[];
    effectiveRate: number | null;
    monthlyFeeOverride: number | null;
};

function formatCurrency(cents: number): string {
    return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(cents / 100);
}

export function StudentGroupsCard({ studentId, groups, availableGroups, effectiveRate, monthlyFeeOverride }: Props) {
    const tenant = useTenant();
    const prefix = `/app/${tenant.slug}`;
    const [selectedGroupId, setSelectedGroupId] = useState('');

    const assignedIds = new Set(groups.map(g => g.id));
    const unassigned = availableGroups.filter(g => !assignedIds.has(g.id));

    function handleAdd() {
        if (!selectedGroupId) return;
        router.post(`${prefix}/students/${studentId}/groups`, { group_id: selectedGroupId }, {
            preserveScroll: true,
            onSuccess: () => setSelectedGroupId(''),
        });
    }

    function handleRemove(groupId: string) {
        router.delete(`${prefix}/students/${studentId}/groups/${groupId}`, {
            preserveScroll: true,
        });
    }

    function handleSetPrimary(groupId: string) {
        router.put(`${prefix}/students/${studentId}/groups/${groupId}/primary`, {}, {
            preserveScroll: true,
        });
    }

    function handleClearPrimary() {
        router.delete(`${prefix}/students/${studentId}/groups/primary`, {
            preserveScroll: true,
        });
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Gruppi e tariffa</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {groups.length > 0 ? (
                    <div className="flex flex-col gap-2">
                        {groups.map((group) => (
                            <div key={group.id} className="flex items-center gap-2">
                                <div
                                    className="size-3 shrink-0 rounded-full"
                                    style={{ backgroundColor: group.color }}
                                />
                                <span className="flex-1 text-sm">{group.name}</span>
                                <span className="text-xs text-muted-foreground">
                                    {formatCurrency(group.monthly_fee_amount)}
                                </span>
                                {group.pivot.is_primary ? (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={handleClearPrimary}
                                        title="Rimuovi come principale"
                                    >
                                        <Crown className="size-4 text-yellow-500" />
                                    </Button>
                                ) : (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => handleSetPrimary(group.id)}
                                        title="Imposta come principale"
                                    >
                                        <Crown className="size-4 text-muted-foreground" />
                                    </Button>
                                )}
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => handleRemove(group.id)}
                                    title="Rimuovi dal gruppo"
                                >
                                    <X className="size-4" />
                                </Button>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">Nessun gruppo assegnato</p>
                )}

                {unassigned.length > 0 && (
                    <div className="flex items-center gap-2">
                        <Select value={selectedGroupId} onValueChange={setSelectedGroupId}>
                            <SelectTrigger className="flex-1">
                                <SelectValue placeholder="Aggiungi a gruppo..." />
                            </SelectTrigger>
                            <SelectContent>
                                {unassigned.map((g) => (
                                    <SelectItem key={g.id} value={g.id}>
                                        <div className="flex items-center gap-2">
                                            <div
                                                className="size-3 rounded-full"
                                                style={{ backgroundColor: g.color }}
                                            />
                                            {g.name}
                                        </div>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button size="sm" onClick={handleAdd} disabled={!selectedGroupId}>
                            <Plus className="size-4" />
                        </Button>
                    </div>
                )}

                <div className="border-t pt-3">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">Tariffa effettiva</span>
                        <span className="font-medium">
                            {monthlyFeeOverride !== null
                                ? `${formatCurrency(monthlyFeeOverride)} (override)`
                                : effectiveRate !== null
                                    ? `${formatCurrency(effectiveRate)}/mese`
                                    : 'Nessuna tariffa'
                            }
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
```

- [ ] **Step 9: Update Student/Show.tsx to include groups card**

Replace the full content of `resources/js/pages/Tenant/Student/Show.tsx`. The key change is adding the `StudentGroupsCard` inside the Dati personali section and adding `availableGroups` prop:

```tsx
// resources/js/pages/Tenant/Student/Show.tsx

import { PageHeader } from '@/components/page-header';
import { StudentGroupsCard } from '@/components/student-groups-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import TenantLayout from '@/layouts/tenant-layout';
import { statusLabel, statusVariant } from '@/lib/student-status';
import type { Group, Student } from '@/types';
import { useTenant } from '@/hooks/use-tenant';
import { Head, Link } from '@inertiajs/react';
import { format, parse } from 'date-fns';
import { it } from 'date-fns/locale';
import { ArrowLeft, Pencil } from 'lucide-react';
import type { ReactElement } from 'react';

function formatDate(value: string | null): string | null {
    if (!value) return null;
    return format(parse(value, 'yyyy-MM-dd', new Date()), 'dd/MM/yyyy', { locale: it });
}

type Props = {
    student: Student;
    availableGroups: Group[];
};

function Field({ label, value }: { label: string; value: string | null }) {
    if (!value) return null;
    return (
        <div>
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="mt-1">{value}</p>
        </div>
    );
}

function computeEffectiveRate(student: Student): number | null {
    if (student.monthly_fee_override !== null) return student.monthly_fee_override;
    const groups = student.groups ?? [];
    if (groups.length === 0) return null;
    const primary = groups.find(g => g.pivot.is_primary);
    if (primary) return primary.monthly_fee_amount;
    return Math.min(...groups.map(g => g.monthly_fee_amount));
}

export default function StudentsShow({ student, availableGroups }: Props) {
    const tenant = useTenant();
    const prefix = `/app/${tenant.slug}`;

    return (
        <>
            <Head title={`${student.first_name} ${student.last_name}`} />
            <div className="mx-auto w-full max-w-2xl p-4">
                <PageHeader
                    sticky
                    title={
                        <h1 className="text-2xl font-semibold">
                            {student.last_name} {student.first_name}
                        </h1>
                    }
                    actions={
                        <>
                            <Button variant="outline" onClick={() => window.history.back()}>
                                <ArrowLeft data-icon="inline-start" />
                                Indietro
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={`${prefix}/students/${student.id}/edit`}>
                                    <Pencil data-icon="inline-start" />
                                    Modifica
                                </Link>
                            </Button>
                        </>
                    }
                />

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle>Dati personali</CardTitle>
                                <Badge variant={statusVariant[student.status]}>
                                    {statusLabel[student.status]}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Nome" value={student.first_name} />
                                <Field label="Cognome" value={student.last_name} />
                                <Field label="Email" value={student.email} />
                                <Field
                                    label="Telefono"
                                    value={
                                        student.effective_phone
                                            ? student.phone_contact_id
                                                ? `${student.effective_phone} (da contatto: ${student.emergency_contacts?.find(c => c.id === student.phone_contact_id)?.name ?? '—'})`
                                                : student.effective_phone
                                            : null
                                    }
                                />
                                <Field label="Data di nascita" value={formatDate(student.date_of_birth)} />
                                <Field label="Codice fiscale" value={student.fiscal_code} />
                            </div>
                        </CardContent>
                    </Card>

                    <StudentGroupsCard
                        studentId={student.id}
                        groups={student.groups ?? []}
                        availableGroups={availableGroups}
                        effectiveRate={computeEffectiveRate(student)}
                        monthlyFeeOverride={student.monthly_fee_override}
                    />

                    {student.address && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Indirizzo</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Field label="Indirizzo" value={student.address} />
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Contatti di emergenza</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {student.emergency_contacts?.length > 0 ? (
                                <div className="flex flex-col gap-4">
                                    {student.emergency_contacts.map((contact) => (
                                        <div key={contact.id} className="grid gap-4 sm:grid-cols-2">
                                            <Field label="Nome contatto" value={contact.name} />
                                            <Field label="Telefono contatto" value={contact.phone} />
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">Nessun contatto di emergenza</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Iscrizione</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Field label="Data iscrizione" value={formatDate(student.enrolled_at)} />
                        </CardContent>
                    </Card>

                    {student.notes && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Note</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="whitespace-pre-wrap">{student.notes}</p>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}

StudentsShow.layout = (page: ReactElement<Props>) => {
    const { student } = page.props;
    return (
        <TenantLayout breadcrumbs={[
            { title: 'Allievi', href: 'students' },
            { title: `${student.last_name} ${student.first_name}`, href: '#' },
        ]}>
            {page}
        </TenantLayout>
    );
};
```

- [ ] **Step 10: Verify frontend builds**

Run: `npx tsc --noEmit`

Expected: No TypeScript errors.

- [ ] **Step 11: Run all tests**

Run: `php artisan test`

Expected: All tests pass.

- [ ] **Step 12: Commit**

```bash
git add app/Http/Controllers/Tenant/StudentGroupController.php app/Http/Controllers/Tenant/StudentController.php routes/tenant.php tests/Feature/Tenant/StudentGroupControllerTest.php resources/js/components/student-groups-card.tsx resources/js/pages/Tenant/Student/Show.tsx resources/js/types/student.ts
git commit -m "feat: add group-student assignment with UI card in Student/Show"
```

---

### Task 6: MonthlyFeeService

**Files:**
- Create: `app/Services/MonthlyFeeService.php`
- Create: `tests/Unit/Services/MonthlyFeeServiceTest.php`

- [ ] **Step 1: Write MonthlyFeeService tests**

```php
<?php
// tests/Unit/Services/MonthlyFeeServiceTest.php

use App\Enums\PaymentMethod;
use App\Models\Group;
use App\Models\MonthlyFee;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FeeCalculationService;
use App\Services\MonthlyFeeService;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->tenant = Tenant::factory()->create(['owner_id' => $this->user->id]);
    tenancy()->initialize($this->tenant);
    $this->service = new MonthlyFeeService(new FeeCalculationService());
});

afterEach(function () {
    tenancy()->end();
});

// --- getUncoveredPeriods ---

test('getUncoveredPeriods returns empty when no cycle started', function () {
    $student = Student::factory()->create();

    expect($this->service->getUncoveredPeriods($student))->toBe([]);
});

test('getUncoveredPeriods returns months from anchor to today', function () {
    Carbon::setTestNow('2026-04-07');
    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-01-16',
    ]);

    $periods = $this->service->getUncoveredPeriods($student);

    // Jan 16 → anchor covers Jan, Feb, Mar (Apr not yet due on Apr 7 < Apr 16)
    expect($periods)->toBe(['2026-01', '2026-02', '2026-03']);

    Carbon::setTestNow();
});

test('getUncoveredPeriods excludes already paid periods', function () {
    Carbon::setTestNow('2026-04-07');
    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-01-16',
    ]);
    $payment = Payment::factory()->create(['student_id' => $student->id]);
    MonthlyFee::create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'period' => '2026-01',
        'expected_amount' => 5000,
        'due_date' => '2026-01-16',
    ]);

    $periods = $this->service->getUncoveredPeriods($student);

    expect($periods)->toBe(['2026-02', '2026-03']);

    Carbon::setTestNow();
});

// --- Edge case #7: anchor at 31st ---

test('getUncoveredPeriods clamps to last day of month', function () {
    Carbon::setTestNow('2026-04-07');
    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-01-31',
    ]);

    $periods = $this->service->getUncoveredPeriods($student);

    // Jan 31 → due dates: Jan 31, Feb 28, Mar 31 (all before Apr 7)
    expect($periods)->toBe(['2026-01', '2026-02', '2026-03']);

    Carbon::setTestNow();
});

// --- registerPayment ---

test('registerPayment creates payment and monthly fee records', function () {
    $student = Student::factory()->create();
    $group = Group::factory()->create(['monthly_fee_amount' => 5000]);
    $student->groups()->attach($group->id);
    $student->load('groups');

    $result = $this->service->registerPayment($student, 1, 5000);

    expect($result)->toBeInstanceOf(\App\Models\Payment::class);
    expect($result->amount)->toBe(5000);
    expect($result->payment_method)->toBe(PaymentMethod::Cash);
    expect($student->fresh()->current_cycle_started_at)->not->toBeNull();
    expect(MonthlyFee::where('student_id', $student->id)->count())->toBe(1);
});

test('registerPayment sets cycle start on first payment', function () {
    Carbon::setTestNow('2026-04-07');
    $student = Student::factory()->create();

    $this->service->registerPayment($student, 1, 5000);

    expect($student->fresh()->current_cycle_started_at->toDateString())->toBe('2026-04-07');

    Carbon::setTestNow();
});

test('registerPayment does not move cycle anchor on subsequent payments', function () {
    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-01-16',
    ]);

    Carbon::setTestNow('2026-04-07');
    $this->service->registerPayment($student, 1, 5000);

    expect($student->fresh()->current_cycle_started_at->toDateString())->toBe('2026-01-16');

    Carbon::setTestNow();
});

// --- FIFO coverage ---

test('registerPayment covers oldest uncovered months first', function () {
    Carbon::setTestNow('2026-04-20');
    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-01-16',
    ]);

    // Pay for 1 month — should cover January (oldest uncovered)
    $this->service->registerPayment($student, 1, 5000);

    $fee = MonthlyFee::where('student_id', $student->id)->first();
    expect($fee->period)->toBe('2026-01');

    Carbon::setTestNow();
});

// --- Multi-month payment (edge case #9) ---

test('registerPayment creates multiple fee records for multi-month', function () {
    Carbon::setTestNow('2026-04-20');
    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-01-16',
    ]);

    $payment = $this->service->registerPayment($student, 3, 15000);

    $fees = MonthlyFee::where('student_id', $student->id)->orderBy('period')->get();
    expect($fees)->toHaveCount(3);
    expect($fees[0]->period)->toBe('2026-01');
    expect($fees[1]->period)->toBe('2026-02');
    expect($fees[2]->period)->toBe('2026-03');
    expect($fees->every(fn ($f) => $f->payment_id === $payment->id))->toBeTrue();

    Carbon::setTestNow();
});

// --- Custom amount (edge case #8) ---

test('registerPayment accepts custom amount creating debt', function () {
    $student = Student::factory()->create();
    $group = Group::factory()->create(['monthly_fee_amount' => 5000]);
    $student->groups()->attach($group->id);
    $student->load('groups');

    $payment = $this->service->registerPayment($student, 1, 4500);

    expect($payment->amount)->toBe(4500);
    $fee = MonthlyFee::where('student_id', $student->id)->first();
    expect($fee->expected_amount)->toBe(5000);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/Services/MonthlyFeeServiceTest.php`

Expected: FAIL.

- [ ] **Step 3: Implement MonthlyFeeService**

```php
<?php
// app/Services/MonthlyFeeService.php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\MonthlyFee;
use App\Models\Payment;
use App\Models\Student;
use Carbon\Carbon;

class MonthlyFeeService
{
    public function __construct(
        private FeeCalculationService $feeCalculation,
    ) {}

    /**
     * Get periods that are due but not yet covered by a payment.
     * Returns array of "YYYY-MM" strings ordered chronologically (FIFO).
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

        $coveredPeriods = $student->monthlyFees()
            ->pluck('period')
            ->flip()
            ->all();

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
     * Register a monthly payment covering N months.
     * Months are covered FIFO: oldest uncovered first, then future.
     */
    public function registerPayment(Student $student, int $months, int $amount): Payment
    {
        $effectiveRate = $this->feeCalculation->getEffectiveRate($student);

        // Set cycle anchor on first payment
        if (! $student->current_cycle_started_at) {
            $student->update(['current_cycle_started_at' => Carbon::today()]);
            $student->refresh();
        }

        $payment = Payment::create([
            'student_id' => $student->id,
            'amount' => $amount,
            'payment_method' => PaymentMethod::Cash,
            'paid_at' => now(),
        ]);

        $periodsTocover = $this->getPeriodsTocover($student, $months);
        $anchor = Carbon::parse($student->current_cycle_started_at);

        $perMonthExpected = $effectiveRate ?? (int) round($amount / count($periodsTocover));

        foreach ($periodsTocover as $period) {
            $date = Carbon::createFromFormat('Y-m', $period);
            $dueDate = $this->clampToMonth($date->year, $date->month, $anchor->day);

            MonthlyFee::create([
                'student_id' => $student->id,
                'payment_id' => $payment->id,
                'period' => $period,
                'expected_amount' => $perMonthExpected,
                'due_date' => $dueDate,
            ]);
        }

        return $payment;
    }

    /**
     * Determine which periods to cover: uncovered first (FIFO), then next future months.
     */
    private function getPeriodsTocover(Student $student, int $months): array
    {
        $uncovered = $this->getUncoveredPeriods($student);
        $periods = array_slice($uncovered, 0, $months);

        // If we need more months than uncovered, extend into the future
        if (count($periods) < $months) {
            $remaining = $months - count($periods);
            $allCovered = $student->monthlyFees()->pluck('period')->flip()->all();
            // Merge newly assigned periods
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
     * Clamp a day to the last day of a given month.
     * e.g., day 31 in February → Feb 28.
     */
    private function clampToMonth(int $year, int $month, int $day): Carbon
    {
        $maxDay = Carbon::create($year, $month, 1)->daysInMonth;

        return Carbon::create($year, $month, min($day, $maxDay));
    }
}
```

- [ ] **Step 4: Run tests**

Run: `php artisan test tests/Unit/Services/MonthlyFeeServiceTest.php`

Expected: All tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/MonthlyFeeService.php tests/Unit/Services/MonthlyFeeServiceTest.php
git commit -m "feat: add MonthlyFeeService with FIFO coverage and multi-month support"
```

---

### Task 7: EnrollmentFeeService

**Files:**
- Create: `app/Services/EnrollmentFeeService.php`
- Create: `tests/Unit/Services/EnrollmentFeeServiceTest.php`

- [ ] **Step 1: Write EnrollmentFeeService tests**

```php
<?php
// tests/Unit/Services/EnrollmentFeeServiceTest.php

use App\Enums\PaymentMethod;
use App\Models\EnrollmentFee;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EnrollmentFeeService;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->tenant = Tenant::factory()->create(['owner_id' => $this->user->id]);
    tenancy()->initialize($this->tenant);
    $this->service = new EnrollmentFeeService();
});

afterEach(function () {
    tenancy()->end();
});

// --- registerEnrollment ---

test('registerEnrollment creates payment and enrollment fee', function () {
    Carbon::setTestNow('2026-04-07');
    $student = Student::factory()->create();

    $payment = $this->service->registerEnrollment($student, 10000);

    expect($payment->amount)->toBe(10000);
    $fee = EnrollmentFee::where('student_id', $student->id)->first();
    expect($fee->starts_at->toDateString())->toBe('2026-04-07');
    expect($fee->expires_at->toDateString())->toBe('2027-04-07');
    expect($fee->expected_amount)->toBe(10000);

    Carbon::setTestNow();
});

// --- Edge case #11: early renewal extends from old expiry ---

test('registerEnrollment extends from old expiry on early renewal', function () {
    Carbon::setTestNow('2026-04-07');
    $student = Student::factory()->create();
    $oldPayment = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::create([
        'student_id' => $student->id,
        'payment_id' => $oldPayment->id,
        'expected_amount' => 10000,
        'starts_at' => '2025-06-01',
        'expires_at' => '2026-06-01', // expires in 2 months
    ]);

    $payment = $this->service->registerEnrollment($student, 10000);

    $fee = EnrollmentFee::where('student_id', $student->id)
        ->where('payment_id', $payment->id)
        ->first();
    expect($fee->starts_at->toDateString())->toBe('2026-06-01'); // from old expiry
    expect($fee->expires_at->toDateString())->toBe('2027-06-01'); // extended

    Carbon::setTestNow();
});

test('registerEnrollment starts from today when old enrollment expired', function () {
    Carbon::setTestNow('2026-04-07');
    $student = Student::factory()->create();
    $oldPayment = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::create([
        'student_id' => $student->id,
        'payment_id' => $oldPayment->id,
        'expected_amount' => 10000,
        'starts_at' => '2024-01-01',
        'expires_at' => '2025-01-01', // already expired
    ]);

    $payment = $this->service->registerEnrollment($student, 10000);

    $fee = EnrollmentFee::where('student_id', $student->id)
        ->where('payment_id', $payment->id)
        ->first();
    expect($fee->starts_at->toDateString())->toBe('2026-04-07');

    Carbon::setTestNow();
});

// --- isEnrollmentExpired ---

test('isEnrollmentExpired returns false with no enrollment', function () {
    $student = Student::factory()->create();

    expect($this->service->isEnrollmentExpired($student))->toBeFalse();
});

test('isEnrollmentExpired returns true when expired', function () {
    Carbon::setTestNow('2026-04-07');
    $student = Student::factory()->create();
    $payment = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'expected_amount' => 10000,
        'starts_at' => '2025-01-01',
        'expires_at' => '2026-01-01',
    ]);

    expect($this->service->isEnrollmentExpired($student))->toBeTrue();

    Carbon::setTestNow();
});

test('isEnrollmentExpired returns false when active', function () {
    Carbon::setTestNow('2026-04-07');
    $student = Student::factory()->create();
    $payment = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'expected_amount' => 10000,
        'starts_at' => '2026-01-01',
        'expires_at' => '2027-01-01',
    ]);

    expect($this->service->isEnrollmentExpired($student))->toBeFalse();

    Carbon::setTestNow();
});

// --- Edge case #13: tenant enrollment duration setting ---

test('registerEnrollment uses tenant setting for duration', function () {
    Carbon::setTestNow('2026-04-07');
    $this->tenant->update(['settings' => ['enrollment_duration_months' => 6]]);
    $student = Student::factory()->create();

    $this->service->registerEnrollment($student, 10000);

    $fee = EnrollmentFee::where('student_id', $student->id)->first();
    expect($fee->expires_at->toDateString())->toBe('2026-10-07'); // 6 months

    Carbon::setTestNow();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/Services/EnrollmentFeeServiceTest.php`

Expected: FAIL.

- [ ] **Step 3: Implement EnrollmentFeeService**

```php
<?php
// app/Services/EnrollmentFeeService.php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\EnrollmentFee;
use App\Models\Payment;
use App\Models\Student;
use Carbon\Carbon;

class EnrollmentFeeService
{
    /**
     * Register an enrollment payment.
     * If active enrollment exists, extends from old expiry (case #11).
     */
    public function registerEnrollment(Student $student, int $amount): Payment
    {
        $latest = $this->getLatestEnrollment($student);
        $today = Carbon::today();

        if ($latest && $latest->expires_at->isFuture()) {
            // Early renewal: extend from old expiry
            $startsAt = $latest->expires_at;
        } else {
            $startsAt = $today;
        }

        $durationMonths = $this->getEnrollmentDurationMonths();
        $expiresAt = $startsAt->copy()->addMonths($durationMonths);

        $payment = Payment::create([
            'student_id' => $student->id,
            'amount' => $amount,
            'payment_method' => PaymentMethod::Cash,
            'paid_at' => now(),
        ]);

        EnrollmentFee::create([
            'student_id' => $student->id,
            'payment_id' => $payment->id,
            'expected_amount' => $amount,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
        ]);

        return $payment;
    }

    public function isEnrollmentExpired(Student $student): bool
    {
        $latest = $this->getLatestEnrollment($student);
        if (! $latest) {
            return false;
        }

        return $latest->expires_at->isPast();
    }

    public function getLatestEnrollment(Student $student): ?EnrollmentFee
    {
        return $student->enrollmentFees()
            ->orderByDesc('expires_at')
            ->first();
    }

    private function getEnrollmentDurationMonths(): int
    {
        $settings = tenant()?->settings ?? [];

        return $settings['enrollment_duration_months'] ?? 12;
    }
}
```

- [ ] **Step 4: Run tests**

Run: `php artisan test tests/Unit/Services/EnrollmentFeeServiceTest.php`

Expected: All tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/EnrollmentFeeService.php tests/Unit/Services/EnrollmentFeeServiceTest.php
git commit -m "feat: add EnrollmentFeeService with renewal and expiry logic"
```

---

### Task 8: Student Suspension Cycle Extension

**Files:**
- Modify: `app/Http/Controllers/Tenant/StudentController.php`
- Modify: `tests/Feature/Tenant/StudentControllerTest.php`

- [ ] **Step 1: Add suspension cycle tests to existing StudentControllerTest**

Append to `tests/Feature/Tenant/StudentControllerTest.php`:

```php
test('suspend archivia il ciclo corrente in past_cycles', function () {
    $student = Student::factory()->create([
        'status' => StudentStatus::Active,
        'current_cycle_started_at' => '2026-01-16',
    ]);

    $response = $this->put("/app/{$this->tenant->slug}/students/{$student->id}/suspend");

    $response->assertRedirect();
    $student->refresh();
    expect($student->status)->toBe(StudentStatus::Suspended);
    expect($student->current_cycle_started_at)->toBeNull();
    expect($student->past_cycles)->toHaveCount(1);
    expect($student->past_cycles[0]['started_at'])->toBe('2026-01-16');
    expect($student->past_cycles[0]['reason'])->toBe('suspended');
});

test('suspend senza ciclo attivo non aggiunge past_cycles', function () {
    $student = Student::factory()->create([
        'status' => StudentStatus::Active,
        'current_cycle_started_at' => null,
    ]);

    $this->put("/app/{$this->tenant->slug}/students/{$student->id}/suspend");

    $student->refresh();
    expect($student->past_cycles)->toBeNull();
});

test('reactivate mantiene current_cycle_started_at null', function () {
    $student = Student::factory()->create([
        'status' => StudentStatus::Suspended,
        'current_cycle_started_at' => null,
    ]);

    $this->put("/app/{$this->tenant->slug}/students/{$student->id}/reactivate");

    $student->refresh();
    expect($student->status)->toBe(StudentStatus::Active);
    expect($student->current_cycle_started_at)->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify the new ones fail**

Run: `php artisan test tests/Feature/Tenant/StudentControllerTest.php --filter=suspend`

Expected: New suspension tests FAIL (cycle archiving not implemented).

- [ ] **Step 3: Update suspend method in StudentController**

In `app/Http/Controllers/Tenant/StudentController.php`, replace the `suspend` method:

```php
public function suspend(Student $student)
{
    $this->authorize('update', $student);

    // Archive current cycle if one exists
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
```

- [ ] **Step 4: Run tests**

Run: `php artisan test tests/Feature/Tenant/StudentControllerTest.php`

Expected: All tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Tenant/StudentController.php tests/Feature/Tenant/StudentControllerTest.php
git commit -m "feat: archive payment cycle on student suspension"
```

---

### Task 9: Payment Registration Backend

**Files:**
- Create: `app/Http/Controllers/Tenant/StudentPaymentController.php`
- Create: `app/Http/Requests/Tenant/RegisterMonthlyPaymentRequest.php`
- Create: `app/Http/Requests/Tenant/RegisterEnrollmentPaymentRequest.php`
- Modify: `routes/tenant.php`
- Modify: `app/Http/Controllers/Tenant/StudentController.php` (show method — add payment data)
- Create: `tests/Feature/Tenant/StudentPaymentControllerTest.php`

- [ ] **Step 1: Write StudentPaymentController tests**

```php
<?php
// tests/Feature/Tenant/StudentPaymentControllerTest.php

use App\Enums\StudentStatus;
use App\Models\EnrollmentFee;
use App\Models\Group;
use App\Models\MonthlyFee;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->tenant = Tenant::factory()->create(['owner_id' => $this->user->id]);
    $this->user->update(['current_tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);
    tenancy()->initialize($this->tenant);
});

afterEach(function () {
    Carbon::setTestNow();
    tenancy()->end();
});

// --- Monthly Payment ---

test('registerMonthly crea payment e monthly fee', function () {
    $student = Student::factory()->create();
    $group = Group::factory()->create(['monthly_fee_amount' => 5000]);
    $student->groups()->attach($group->id);

    $response = $this->post(
        "/app/{$this->tenant->slug}/students/{$student->id}/payments/monthly",
        ['amount' => 50, 'months' => 1]
    );

    $response->assertRedirect();
    expect(Payment::where('student_id', $student->id)->count())->toBe(1);
    expect(MonthlyFee::where('student_id', $student->id)->count())->toBe(1);
});

test('registerMonthly valida campi obbligatori', function () {
    $student = Student::factory()->create();

    $response = $this->post(
        "/app/{$this->tenant->slug}/students/{$student->id}/payments/monthly",
        []
    );

    $response->assertSessionHasErrors(['amount', 'months']);
});

test('registerMonthly valida importo positivo', function () {
    $student = Student::factory()->create();

    $response = $this->post(
        "/app/{$this->tenant->slug}/students/{$student->id}/payments/monthly",
        ['amount' => 0, 'months' => 1]
    );

    $response->assertSessionHasErrors(['amount']);
});

test('registerMonthly multi-month creates correct records', function () {
    $student = Student::factory()->create();

    $response = $this->post(
        "/app/{$this->tenant->slug}/students/{$student->id}/payments/monthly",
        ['amount' => 150, 'months' => 3]
    );

    $response->assertRedirect();
    expect(Payment::where('student_id', $student->id)->count())->toBe(1);
    expect(MonthlyFee::where('student_id', $student->id)->count())->toBe(3);
    expect(Payment::where('student_id', $student->id)->first()->amount)->toBe(15000);
});

// --- Enrollment Payment ---

test('registerEnrollment crea payment e enrollment fee', function () {
    Carbon::setTestNow('2026-04-07');
    $student = Student::factory()->create();

    $response = $this->post(
        "/app/{$this->tenant->slug}/students/{$student->id}/payments/enrollment",
        ['amount' => 100]
    );

    $response->assertRedirect();
    expect(Payment::where('student_id', $student->id)->count())->toBe(1);
    $fee = EnrollmentFee::where('student_id', $student->id)->first();
    expect($fee->starts_at->toDateString())->toBe('2026-04-07');
    expect($fee->expires_at->toDateString())->toBe('2027-04-07');
});

test('registerEnrollment valida campi obbligatori', function () {
    $student = Student::factory()->create();

    $response = $this->post(
        "/app/{$this->tenant->slug}/students/{$student->id}/payments/enrollment",
        []
    );

    $response->assertSessionHasErrors(['amount']);
});

// --- Tenant isolation ---

test('non si possono registrare pagamenti per studenti di un altro tenant', function () {
    $otherUser = User::factory()->create();
    $otherTenant = Tenant::factory()->create(['owner_id' => $otherUser->id]);
    $student = Student::factory()->create();

    $response = $this->post(
        "/app/{$otherTenant->slug}/students/{$student->id}/payments/monthly",
        ['amount' => 50, 'months' => 1]
    );

    $response->assertForbidden();
});

// --- Show includes payment data ---

test('show include dati pagamento per lo studente', function () {
    Carbon::setTestNow('2026-04-07');
    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-01-16',
    ]);
    $group = Group::factory()->create(['monthly_fee_amount' => 5000]);
    $student->groups()->attach($group->id);

    $response = $this->get("/app/{$this->tenant->slug}/students/{$student->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('paymentData')
        ->has('paymentData.effectiveRate')
        ->has('paymentData.balance')
        ->has('paymentData.uncoveredPeriods')
        ->has('paymentData.latestEnrollment')
        ->has('paymentData.enrollmentExpired')
        ->has('paymentData.payments')
    );
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Tenant/StudentPaymentControllerTest.php`

Expected: FAIL.

- [ ] **Step 3: Create RegisterMonthlyPaymentRequest**

```php
<?php
// app/Http/Requests/Tenant/RegisterMonthlyPaymentRequest.php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class RegisterMonthlyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'months' => ['required', 'integer', 'min:1', 'max:12'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('amount') && is_numeric($this->amount)) {
            $this->merge([
                'amount' => (int) round($this->amount * 100),
            ]);
        }
    }
}
```

- [ ] **Step 4: Create RegisterEnrollmentPaymentRequest**

```php
<?php
// app/Http/Requests/Tenant/RegisterEnrollmentPaymentRequest.php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class RegisterEnrollmentPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('amount') && is_numeric($this->amount)) {
            $this->merge([
                'amount' => (int) round($this->amount * 100),
            ]);
        }
    }
}
```

- [ ] **Step 5: Create StudentPaymentController**

```php
<?php
// app/Http/Controllers/Tenant/StudentPaymentController.php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\RegisterEnrollmentPaymentRequest;
use App\Http\Requests\Tenant\RegisterMonthlyPaymentRequest;
use App\Models\Student;
use App\Services\EnrollmentFeeService;
use App\Services\FeeCalculationService;
use App\Services\MonthlyFeeService;

class StudentPaymentController extends Controller
{
    public function __construct(
        private MonthlyFeeService $monthlyFeeService,
        private EnrollmentFeeService $enrollmentFeeService,
    ) {}

    public function registerMonthly(RegisterMonthlyPaymentRequest $request, Student $student)
    {
        $this->authorize('update', $student);

        $validated = $request->validated();

        $this->monthlyFeeService->registerPayment(
            $student,
            $validated['months'],
            $validated['amount'],
        );

        return redirect()->back()->with('success', 'Pagamento mensilità registrato.');
    }

    public function registerEnrollment(RegisterEnrollmentPaymentRequest $request, Student $student)
    {
        $this->authorize('update', $student);

        $validated = $request->validated();

        $this->enrollmentFeeService->registerEnrollment(
            $student,
            $validated['amount'],
        );

        return redirect()->back()->with('success', 'Pagamento iscrizione registrato.');
    }
}
```

- [ ] **Step 6: Add payment routes to `routes/tenant.php`**

Add inside the group block:

```php
use App\Http\Controllers\Tenant\StudentPaymentController;

// Student payments
Route::post('students/{student}/payments/monthly', [StudentPaymentController::class, 'registerMonthly'])
    ->name('tenant.students.payments.monthly');
Route::post('students/{student}/payments/enrollment', [StudentPaymentController::class, 'registerEnrollment'])
    ->name('tenant.students.payments.enrollment');
```

- [ ] **Step 7: Update StudentController show() to include payment data**

Replace the `show()` method in `app/Http/Controllers/Tenant/StudentController.php`:

```php
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
```

Add the necessary imports at the top of `StudentController.php`:

```php
use App\Services\FeeCalculationService;
use App\Services\MonthlyFeeService;
use App\Services\EnrollmentFeeService;
```

- [ ] **Step 8: Run tests**

Run: `php artisan test tests/Feature/Tenant/StudentPaymentControllerTest.php`

Expected: All tests PASS.

- [ ] **Step 9: Run full test suite**

Run: `php artisan test`

Expected: All tests pass.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Tenant/StudentPaymentController.php app/Http/Controllers/Tenant/StudentController.php app/Http/Requests/Tenant/RegisterMonthlyPaymentRequest.php app/Http/Requests/Tenant/RegisterEnrollmentPaymentRequest.php routes/tenant.php tests/Feature/Tenant/StudentPaymentControllerTest.php
git commit -m "feat: add payment registration endpoints with monthly and enrollment support"
```

---

### Task 10: Payments Tab Frontend

**Files:**
- Create: `resources/js/types/payment.ts`
- Modify: `resources/js/types/index.ts`
- Create: `resources/js/components/register-monthly-dialog.tsx`
- Create: `resources/js/components/register-enrollment-dialog.tsx`
- Create: `resources/js/components/student-payments-tab.tsx`
- Modify: `resources/js/pages/Tenant/Student/Show.tsx`

- [ ] **Step 1: Create payment TypeScript types**

```typescript
// resources/js/types/payment.ts

export type PaymentMonthlyFee = {
    id: string;
    period: string;
    expected_amount: number;
    due_date: string;
};

export type PaymentEnrollmentFee = {
    id: string;
    expected_amount: number;
    starts_at: string;
    expires_at: string;
};

export type Payment = {
    id: string;
    amount: number;
    payment_method: string;
    paid_at: string;
    notes: string | null;
    monthly_fees: PaymentMonthlyFee[];
    enrollment_fees: PaymentEnrollmentFee[];
};

export type LatestEnrollment = {
    id: string;
    starts_at: string;
    expires_at: string;
    expected_amount: number;
} | null;

export type PaymentData = {
    effectiveRate: number | null;
    balance: number;
    uncoveredPeriods: string[];
    latestEnrollment: LatestEnrollment;
    enrollmentExpired: boolean;
    payments: Payment[];
};
```

- [ ] **Step 2: Export payment types from index**

Add to `resources/js/types/index.ts`:

```typescript
export type * from './payment';
```

- [ ] **Step 3: Create register-monthly-dialog component**

```tsx
// resources/js/components/register-monthly-dialog.tsx

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useTenant } from '@/hooks/use-tenant';
import { useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import type { FormEventHandler } from 'react';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    studentId: string;
    effectiveRate: number | null;
    balance: number;
    enrollmentExpired: boolean;
    uncoveredCount: number;
};

function formatCurrency(cents: number): string {
    return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(cents / 100);
}

export function RegisterMonthlyDialog({
    open, onOpenChange, studentId, effectiveRate, balance, enrollmentExpired, uncoveredCount,
}: Props) {
    const tenant = useTenant();
    const prefix = `/app/${tenant.slug}`;

    const defaultMonths = Math.max(1, Math.min(uncoveredCount, 1));
    const suggestedAmount = effectiveRate
        ? ((effectiveRate * defaultMonths - balance) / 100)
        : '';

    const form = useForm({
        amount: suggestedAmount.toString(),
        months: defaultMonths.toString(),
        notes: '',
    });

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(`${prefix}/students/${studentId}/payments/monthly`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Registra mensilità</DialogTitle>
                </DialogHeader>

                {enrollmentExpired && (
                    <Alert variant="destructive">
                        <AlertTriangle className="size-4" />
                        <AlertDescription>
                            Iscrizione scaduta. Il pagamento sarà comunque registrato.
                        </AlertDescription>
                    </Alert>
                )}

                {balance < 0 && (
                    <Alert>
                        <AlertDescription>
                            Debito precedente: {formatCurrency(Math.abs(balance))}
                        </AlertDescription>
                    </Alert>
                )}

                {balance > 0 && (
                    <Alert>
                        <AlertDescription>
                            Credito precedente: {formatCurrency(balance)}
                        </AlertDescription>
                    </Alert>
                )}

                <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field>
                            <FieldLabel htmlFor="months">Mesi da coprire</FieldLabel>
                            <Input
                                id="months"
                                type="number"
                                min="1"
                                max="12"
                                value={form.data.months}
                                onChange={(e) => {
                                    const months = parseInt(e.target.value) || 1;
                                    form.setData(prev => ({
                                        ...prev,
                                        months: e.target.value,
                                        amount: effectiveRate
                                            ? ((effectiveRate * months - balance) / 100).toString()
                                            : prev.amount,
                                    }));
                                }}
                            />
                            <FieldError message={form.errors.months} />
                        </Field>

                        <Field>
                            <FieldLabel htmlFor="amount">Importo (€)</FieldLabel>
                            <Input
                                id="amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={form.data.amount}
                                onChange={(e) => form.setData('amount', e.target.value)}
                                placeholder={effectiveRate ? (effectiveRate / 100).toString() : ''}
                            />
                            <FieldError message={form.errors.amount} />
                        </Field>
                    </div>

                    <Field>
                        <FieldLabel htmlFor="notes">Note</FieldLabel>
                        <Textarea
                            id="notes"
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            rows={2}
                        />
                    </Field>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Annulla
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Registra pagamento
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 4: Create register-enrollment-dialog component**

```tsx
// resources/js/components/register-enrollment-dialog.tsx

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { useTenant } from '@/hooks/use-tenant';
import { useForm } from '@inertiajs/react';
import type { LatestEnrollment } from '@/types';
import type { FormEventHandler } from 'react';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    studentId: string;
    latestEnrollment: LatestEnrollment;
};

export function RegisterEnrollmentDialog({
    open, onOpenChange, studentId, latestEnrollment,
}: Props) {
    const tenant = useTenant();
    const prefix = `/app/${tenant.slug}`;

    const isRenewal = latestEnrollment && new Date(latestEnrollment.expires_at) > new Date();

    const form = useForm({
        amount: latestEnrollment ? (latestEnrollment.expected_amount / 100).toString() : '',
        notes: '',
    });

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(`${prefix}/students/${studentId}/payments/enrollment`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isRenewal ? 'Rinnova iscrizione' : 'Registra iscrizione'}
                    </DialogTitle>
                </DialogHeader>

                {isRenewal && (
                    <p className="text-sm text-muted-foreground">
                        Rinnovo da {new Date(latestEnrollment.expires_at).toLocaleDateString('it-IT')}
                    </p>
                )}

                <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                    <Field>
                        <FieldLabel htmlFor="enrollment-amount">Importo (€)</FieldLabel>
                        <Input
                            id="enrollment-amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            value={form.data.amount}
                            onChange={(e) => form.setData('amount', e.target.value)}
                        />
                        <FieldError message={form.errors.amount} />
                    </Field>

                    <Field>
                        <FieldLabel htmlFor="enrollment-notes">Note</FieldLabel>
                        <Textarea
                            id="enrollment-notes"
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            rows={2}
                        />
                    </Field>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Annulla
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {isRenewal ? 'Rinnova iscrizione' : 'Registra iscrizione'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 5: Create student-payments-tab component**

```tsx
// resources/js/components/student-payments-tab.tsx

import { RegisterMonthlyDialog } from '@/components/register-monthly-dialog';
import { RegisterEnrollmentDialog } from '@/components/register-enrollment-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { PaymentData } from '@/types';
import { format } from 'date-fns';
import { it } from 'date-fns/locale';
import { Banknote, GraduationCap } from 'lucide-react';
import { useState } from 'react';

type Props = {
    studentId: string;
    paymentData: PaymentData;
};

function formatCurrency(cents: number): string {
    return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(cents / 100);
}

function formatDate(dateStr: string): string {
    return format(new Date(dateStr), 'dd/MM/yyyy', { locale: it });
}

export function StudentPaymentsTab({ studentId, paymentData }: Props) {
    const [monthlyOpen, setMonthlyOpen] = useState(false);
    const [enrollmentOpen, setEnrollmentOpen] = useState(false);

    const { effectiveRate, balance, uncoveredPeriods, latestEnrollment, enrollmentExpired, payments } = paymentData;

    return (
        <div className="flex flex-col gap-6">
            {/* Summary */}
            <Card>
                <CardHeader>
                    <CardTitle>Riepilogo</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-3">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">Tariffa corrente</span>
                        <span className="font-medium">
                            {effectiveRate !== null ? `${formatCurrency(effectiveRate)}/mese` : 'Nessuna tariffa'}
                        </span>
                    </div>

                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">Saldo</span>
                        <span className={
                            balance < 0 ? 'font-medium text-destructive' :
                            balance > 0 ? 'font-medium text-green-600' :
                            'font-medium'
                        }>
                            {balance === 0 ? 'In pari' :
                             balance < 0 ? `Debito: ${formatCurrency(Math.abs(balance))}` :
                             `Credito: ${formatCurrency(balance)}`}
                        </span>
                    </div>

                    {uncoveredPeriods.length > 0 && (
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">Mesi scoperti</span>
                            <Badge variant="destructive">{uncoveredPeriods.length}</Badge>
                        </div>
                    )}

                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">Iscrizione</span>
                        {latestEnrollment ? (
                            enrollmentExpired ? (
                                <span className="font-medium text-destructive">
                                    Scaduta il {formatDate(latestEnrollment.expires_at)}
                                </span>
                            ) : (
                                <span className="font-medium">
                                    Scade il {formatDate(latestEnrollment.expires_at)}
                                </span>
                            )
                        ) : (
                            <span className="text-muted-foreground">Nessuna iscrizione</span>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Actions */}
            <div className="flex gap-3">
                <Button className="flex-1" onClick={() => setMonthlyOpen(true)}>
                    <Banknote data-icon="inline-start" />
                    Registra mensilità
                </Button>
                <Button className="flex-1" variant="outline" onClick={() => setEnrollmentOpen(true)}>
                    <GraduationCap data-icon="inline-start" />
                    Registra iscrizione
                </Button>
            </div>

            {/* Payment history */}
            {payments.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Storico pagamenti</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col gap-4">
                            {payments.map((payment) => (
                                <div key={payment.id} className="flex items-start justify-between border-b pb-3 last:border-0">
                                    <div>
                                        <p className="text-sm font-medium">
                                            {formatDate(payment.paid_at)}
                                        </p>
                                        {payment.monthly_fees.length > 0 && (
                                            <p className="text-xs text-muted-foreground">
                                                Mensilità: {payment.monthly_fees.map(f => f.period).join(', ')}
                                            </p>
                                        )}
                                        {payment.enrollment_fees.length > 0 && (
                                            <p className="text-xs text-muted-foreground">
                                                Iscrizione: {payment.enrollment_fees.map(f =>
                                                    `${formatDate(f.starts_at)} → ${formatDate(f.expires_at)}`
                                                ).join(', ')}
                                            </p>
                                        )}
                                        {payment.notes && (
                                            <p className="mt-1 text-xs text-muted-foreground italic">
                                                {payment.notes}
                                            </p>
                                        )}
                                    </div>
                                    <span className="shrink-0 font-medium">
                                        {formatCurrency(payment.amount)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Dialogs */}
            <RegisterMonthlyDialog
                open={monthlyOpen}
                onOpenChange={setMonthlyOpen}
                studentId={studentId}
                effectiveRate={effectiveRate}
                balance={balance}
                enrollmentExpired={enrollmentExpired}
                uncoveredCount={uncoveredPeriods.length}
            />

            <RegisterEnrollmentDialog
                open={enrollmentOpen}
                onOpenChange={setEnrollmentOpen}
                studentId={studentId}
                latestEnrollment={latestEnrollment}
            />
        </div>
    );
}
```

- [ ] **Step 6: Update Student/Show.tsx with tabs (Dati personali + Pagamenti)**

This is a significant update to `resources/js/pages/Tenant/Student/Show.tsx`. The page now uses a simple tab system. Replace the full content:

The key structural change: wrap the existing cards in a "Dati personali" tab and add a new "Pagamenti" tab. Use a simple state-based tab switcher (no need for a heavy tab library — two buttons and conditional rendering).

In the component, add these imports at the top:

```tsx
import { StudentPaymentsTab } from '@/components/student-payments-tab';
import type { Group, PaymentData, Student } from '@/types';
import { useState } from 'react';
```

Update the Props type:

```tsx
type Props = {
    student: Student;
    availableGroups: Group[];
    paymentData: PaymentData;
};
```

Inside the component, add state and tab buttons after `PageHeader`, before the card sections:

```tsx
const [tab, setTab] = useState<'personal' | 'payments'>('personal');
```

Add tab buttons after the `PageHeader` closing tag and before the cards `div`:

```tsx
<div className="mb-6 flex gap-1 rounded-lg bg-muted p-1">
    <button
        type="button"
        onClick={() => setTab('personal')}
        className={cn(
            'flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
            tab === 'personal' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground',
        )}
    >
        Dati personali
    </button>
    <button
        type="button"
        onClick={() => setTab('payments')}
        className={cn(
            'flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
            tab === 'payments' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground',
        )}
    >
        Pagamenti
    </button>
</div>
```

Import `cn` from `@/lib/utils`.

Wrap the existing cards in `{tab === 'personal' && ( ... )}` and add the payments tab:

```tsx
{tab === 'payments' && (
    <StudentPaymentsTab studentId={student.id} paymentData={paymentData} />
)}
```

- [ ] **Step 7: Verify frontend builds**

Run: `npx tsc --noEmit`

Expected: No TypeScript errors.

- [ ] **Step 8: Run full test suite**

Run: `php artisan test`

Expected: All tests pass.

- [ ] **Step 9: Commit**

```bash
git add resources/js/types/payment.ts resources/js/types/index.ts resources/js/components/register-monthly-dialog.tsx resources/js/components/register-enrollment-dialog.tsx resources/js/components/student-payments-tab.tsx resources/js/pages/Tenant/Student/Show.tsx
git commit -m "feat: add Payments tab with registration dialogs and history in Student/Show"
```

---

### Task 11: Edge Case Integration Tests

**Files:**
- Create: `tests/Feature/Tenant/PaymentEdgeCasesTest.php`

- [ ] **Step 1: Write comprehensive edge case tests**

```php
<?php
// tests/Feature/Tenant/PaymentEdgeCasesTest.php

use App\Enums\PaymentMethod;
use App\Models\EnrollmentFee;
use App\Models\Group;
use App\Models\MonthlyFee;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EnrollmentFeeService;
use App\Services\FeeCalculationService;
use App\Services\MonthlyFeeService;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->tenant = Tenant::factory()->create(['owner_id' => $this->user->id]);
    $this->user->update(['current_tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);
    tenancy()->initialize($this->tenant);

    $this->feeService = new FeeCalculationService();
    $this->monthlyService = new MonthlyFeeService($this->feeService);
    $this->enrollmentService = new EnrollmentFeeService();
});

afterEach(function () {
    Carbon::setTestNow();
    tenancy()->end();
});

// --- Case #1: First payment sets cycle anchor ---

test('caso 1: primo pagamento setta current_cycle_started_at', function () {
    Carbon::setTestNow('2026-04-07');
    $student = Student::factory()->create();
    $group = Group::factory()->create(['monthly_fee_amount' => 5000]);
    $student->groups()->attach($group->id);
    $student->load('groups');

    $this->monthlyService->registerPayment($student, 1, 5000);

    expect($student->fresh()->current_cycle_started_at->toDateString())->toBe('2026-04-07');
});

// --- Case #2: Late payment doesn't move anchor ---

test('caso 2: pagamento in ritardo non sposta ancora', function () {
    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-03-16',
    ]);

    Carbon::setTestNow('2026-03-25');
    $this->monthlyService->registerPayment($student, 1, 5000);

    expect($student->fresh()->current_cycle_started_at->toDateString())->toBe('2026-03-16');
});

// --- Case #3: Trainer decides on skipped months ---

test('caso 3: studente con mesi saltati mostra periodi scoperti', function () {
    Carbon::setTestNow('2026-04-20');
    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-01-16',
    ]);

    // Only January paid
    $payment = Payment::factory()->create(['student_id' => $student->id]);
    MonthlyFee::create([
        'student_id' => $student->id,
        'payment_id' => $payment->id,
        'period' => '2026-01',
        'expected_amount' => 5000,
        'due_date' => '2026-01-16',
    ]);

    $uncovered = $this->monthlyService->getUncoveredPeriods($student);
    expect($uncovered)->toBe(['2026-02', '2026-03', '2026-04']);
});

// --- Case #4: Suspension archives cycle ---

test('caso 4: sospensione archivia ciclo e riattivazione lo resetta', function () {
    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-01-16',
    ]);

    // Suspend
    $this->put("/app/{$this->tenant->slug}/students/{$student->id}/suspend");
    $student->refresh();
    expect($student->current_cycle_started_at)->toBeNull();
    expect($student->past_cycles)->toHaveCount(1);

    // Reactivate
    $this->put("/app/{$this->tenant->slug}/students/{$student->id}/reactivate");
    $student->refresh();
    expect($student->current_cycle_started_at)->toBeNull();

    // First payment after reactivation starts new cycle
    Carbon::setTestNow('2026-05-01');
    $student->load('groups');
    $this->monthlyService->registerPayment($student, 1, 5000);
    expect($student->fresh()->current_cycle_started_at->toDateString())->toBe('2026-05-01');
});

// --- Case #5: No group, no override — null rate ---

test('caso 5: senza gruppi e override la tariffa è null', function () {
    $student = Student::factory()->create();
    $student->load('groups');

    expect($this->feeService->getEffectiveRate($student))->toBeNull();
});

// --- Case #6: Removed from group, fee changes immediately ---

test('caso 6: rimosso da gruppo la tariffa cambia subito', function () {
    $student = Student::factory()->create();
    $under16 = Group::factory()->create(['monthly_fee_amount' => 4000]);
    $agonisti = Group::factory()->create(['monthly_fee_amount' => 6000]);
    $student->groups()->attach([$under16->id, $agonisti->id]);

    $student->load('groups');
    expect($this->feeService->getEffectiveRate($student))->toBe(4000); // min

    $student->groups()->detach($under16->id);
    $student->load('groups');
    expect($this->feeService->getEffectiveRate($student))->toBe(6000);
});

// --- Case #7: Anchor at 31st clamps to month end ---

test('caso 7: ancora al 31 viene limitata a fine mese', function () {
    Carbon::setTestNow('2026-04-07');
    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-01-31',
    ]);

    $uncovered = $this->monthlyService->getUncoveredPeriods($student);
    expect($uncovered)->toBe(['2026-01', '2026-02', '2026-03']);

    // Register payment and check due dates
    $student->load('groups');
    $this->monthlyService->registerPayment($student, 3, 15000);

    $fees = MonthlyFee::where('student_id', $student->id)->orderBy('period')->get();
    expect($fees[0]->due_date->toDateString())->toBe('2026-01-31');
    expect($fees[1]->due_date->toDateString())->toBe('2026-02-28');
    expect($fees[2]->due_date->toDateString())->toBe('2026-03-31');
});

// --- Case #8: Custom amount creates debt ---

test('caso 8: importo diverso dalla tariffa genera debito', function () {
    $student = Student::factory()->create();
    $group = Group::factory()->create(['monthly_fee_amount' => 5000]);
    $student->groups()->attach($group->id);
    $student->load('groups');

    $this->monthlyService->registerPayment($student, 1, 4500);

    expect($this->feeService->getBalance($student))->toBe(-500);
});

// --- Case #9: Multi-month payment ---

test('caso 9: pagamento multi-mese crea un payment e N fee', function () {
    $student = Student::factory()->create();
    $group = Group::factory()->create(['monthly_fee_amount' => 5000]);
    $student->groups()->attach($group->id);
    $student->load('groups');

    $payment = $this->monthlyService->registerPayment($student, 3, 15000);

    expect(Payment::where('student_id', $student->id)->count())->toBe(1);
    expect(MonthlyFee::where('student_id', $student->id)->count())->toBe(3);
    expect($payment->amount)->toBe(15000);
});

// --- Case #10: Expired enrollment, monthly payment still allowed ---

test('caso 10: iscrizione scaduta non blocca mensilità', function () {
    Carbon::setTestNow('2026-04-07');
    $student = Student::factory()->create();
    $oldPayment = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::create([
        'student_id' => $student->id,
        'payment_id' => $oldPayment->id,
        'expected_amount' => 10000,
        'starts_at' => '2025-01-01',
        'expires_at' => '2026-01-01', // expired
    ]);

    expect($this->enrollmentService->isEnrollmentExpired($student))->toBeTrue();

    // Monthly payment still works
    $response = $this->post(
        "/app/{$this->tenant->slug}/students/{$student->id}/payments/monthly",
        ['amount' => 50, 'months' => 1]
    );
    $response->assertRedirect();
});

// --- Case #11: Early renewal extends from old expiry ---

test('caso 11: rinnovo anticipato estende dalla vecchia scadenza', function () {
    Carbon::setTestNow('2026-04-07');
    $student = Student::factory()->create();
    $oldPayment = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::create([
        'student_id' => $student->id,
        'payment_id' => $oldPayment->id,
        'expected_amount' => 10000,
        'starts_at' => '2025-06-01',
        'expires_at' => '2026-06-01',
    ]);

    $payment = $this->enrollmentService->registerEnrollment($student, 10000);
    $fee = EnrollmentFee::where('payment_id', $payment->id)->first();
    expect($fee->starts_at->toDateString())->toBe('2026-06-01');
    expect($fee->expires_at->toDateString())->toBe('2027-06-01');
});

// --- Case #12: Enrollment expires during suspension ---

test('caso 12: iscrizione scade durante sospensione', function () {
    Carbon::setTestNow('2026-04-07');
    $student = Student::factory()->create([
        'current_cycle_started_at' => '2026-01-01',
    ]);
    $oldPayment = Payment::factory()->create(['student_id' => $student->id]);
    EnrollmentFee::create([
        'student_id' => $student->id,
        'payment_id' => $oldPayment->id,
        'expected_amount' => 10000,
        'starts_at' => '2025-01-01',
        'expires_at' => '2026-01-01',
    ]);

    // Suspend
    $this->put("/app/{$this->tenant->slug}/students/{$student->id}/suspend");
    $student->refresh();

    // Enrollment still expired (tracked independently)
    expect($this->enrollmentService->isEnrollmentExpired($student))->toBeTrue();
    expect($student->past_cycles)->toHaveCount(1);
});

// --- Case #13: Default enrollment duration change ---

test('caso 13: cambio durata default iscrizione si applica solo alle nuove', function () {
    Carbon::setTestNow('2026-04-07');
    $student = Student::factory()->create();

    // Register with default 12 months
    $payment1 = $this->enrollmentService->registerEnrollment($student, 10000);
    $fee1 = EnrollmentFee::where('payment_id', $payment1->id)->first();
    expect($fee1->expires_at->toDateString())->toBe('2027-04-07');

    // Change default to 6 months
    $this->tenant->update(['settings' => ['enrollment_duration_months' => 6]]);

    // New enrollment for another student uses 6 months
    $student2 = Student::factory()->create();
    $payment2 = $this->enrollmentService->registerEnrollment($student2, 10000);
    $fee2 = EnrollmentFee::where('payment_id', $payment2->id)->first();
    expect($fee2->expires_at->toDateString())->toBe('2026-10-07');

    // Old enrollment unchanged
    expect($fee1->fresh()->expires_at->toDateString())->toBe('2027-04-07');
});
```

- [ ] **Step 2: Run edge case tests**

Run: `php artisan test tests/Feature/Tenant/PaymentEdgeCasesTest.php`

Expected: All 13 edge case tests PASS.

- [ ] **Step 3: Run full test suite**

Run: `php artisan test`

Expected: All tests pass.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Tenant/PaymentEdgeCasesTest.php
git commit -m "test: add all 13 payment edge case integration tests"
```
