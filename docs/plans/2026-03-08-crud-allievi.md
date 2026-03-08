# CRUD Allievi (Anagrafica) — Piano di Implementazione

> **Stato: COMPLETATO** (2026-03-08)

**Goal:** CRUD completo per la gestione anagrafica allievi all'interno di un tenant, con soft delete, ricerca, filtro per stato e ordinamento.

**Architecture:** Controller RESTful `StudentController` con Form Requests per validazione, Policy per autorizzazione. Frontend con 4 pagine Inertia (index, create, show, edit) e componente form condiviso. Paginazione e filtri server-side. Soft delete con conferma via AlertDialog.

**Tech Stack:** Laravel 12, Inertia.js, React 19, TypeScript, shadcn/ui, Pest (test), stancl/tenancy (BelongsToTenant)

## Bug fixati durante l'implementazione

- FK migrazioni puntavano a `tenants.id` invece di `tenants.slug` — fixato in tutte le migrazioni
- `authorizeResource()` non funziona in Laravel 12 — sostituito con `$this->authorize()` individuali
- Base Controller mancava trait `AuthorizesRequests`
- Form requests usavano `tenant('id')` invece di `tenant()->getTenantKey()`
- Tipo `PaginatedData` aveva struttura `meta.*` — Laravel `paginate()` restituisce struttura flat
- Campi data serializzati come ISO timestamp — fixato con cast `date:Y-m-d`
- Input date nativi sostituiti con DatePicker shadcn (Calendar + Popover)
- Date nella pagina show formattate in `dd/MM/yyyy` con locale italiano
- Eccezione `TenantCouldNotBeIdentifiedByPathException` ora rende 404

---

### Task 0: Installa componenti shadcn mancanti

**Step 1: Aggiungi componenti**

```bash
cd trainer-hub
npx shadcn@latest add table alert-dialog pagination textarea
```

**Step 2: Verifica installazione**

```bash
ls resources/js/components/ui/table.tsx resources/js/components/ui/alert-dialog.tsx resources/js/components/ui/pagination.tsx resources/js/components/ui/textarea.tsx
```

**Step 3: Commit**

```bash
git add resources/js/components/ui/
git commit -m "chore: add shadcn table, alert-dialog, pagination, textarea"
```

---

### Task 1: Migrazione soft delete + Factory Student

**Files:**
- Create: `database/migrations/2026_03_08_000001_add_soft_deletes_to_students_table.php`
- Create: `database/factories/StudentFactory.php`
- Modify: `app/Models/Student.php`

**Step 1: Crea migrazione soft delete**

```bash
php artisan make:migration add_soft_deletes_to_students_table
```

Contenuto:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
```

**Step 2: Aggiungi SoftDeletes al model Student**

In `app/Models/Student.php`, aggiungere:
- `use Illuminate\Database\Eloquent\SoftDeletes;`
- Il trait `SoftDeletes` nella classe

```php
use HasUuids, BelongsToTenant, SoftDeletes;
```

**Step 3: Crea StudentFactory**

File `database/factories/StudentFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Student> */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'date_of_birth' => fake()->date(),
            'fiscal_code' => strtoupper(fake()->bothify('??????##?##?###?')),
            'address' => fake()->address(),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => fake()->phoneNumber(),
            'notes' => null,
            'status' => StudentStatus::Active,
            'enrolled_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => StudentStatus::Inactive]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => StudentStatus::Suspended]);
    }
}
```

Aggiungere `use HasFactory` al model `Student`:

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Student extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, SoftDeletes;
```

**Step 4: Esegui migrazione**

```bash
php artisan migrate
```

**Step 5: Commit**

```bash
git add database/migrations/*soft_deletes* database/factories/StudentFactory.php app/Models/Student.php
git commit -m "feat: add soft deletes to Student model and create StudentFactory"
```

---

### Task 2: Form Requests + Policy

**Files:**
- Create: `app/Http/Requests/Tenant/StoreStudentRequest.php`
- Create: `app/Http/Requests/Tenant/UpdateStudentRequest.php`
- Create: `app/Policies/StudentPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php` (registra policy se necessario)

**Step 1: Crea StoreStudentRequest**

```bash
php artisan make:request Tenant/StoreStudentRequest
```

```php
<?php

namespace App\Http\Requests\Tenant;

use App\Enums\StudentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorizzazione gestita dal middleware tenant.access
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('students')->where('tenant_id', tenant('id')),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'fiscal_code' => ['nullable', 'string', 'max:16'],
            'address' => ['nullable', 'string', 'max:500'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(StudentStatus::class)],
            'enrolled_at' => ['nullable', 'date'],
        ];
    }
}
```

**Step 2: Crea UpdateStudentRequest**

```bash
php artisan make:request Tenant/UpdateStudentRequest
```

```php
<?php

namespace App\Http\Requests\Tenant;

use App\Enums\StudentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('students')
                    ->where('tenant_id', tenant('id'))
                    ->ignore($this->route('student')),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'fiscal_code' => ['nullable', 'string', 'max:16'],
            'address' => ['nullable', 'string', 'max:500'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(StudentStatus::class)],
            'enrolled_at' => ['nullable', 'date'],
        ];
    }
}
```

**Step 3: Crea StudentPolicy**

```bash
php artisan make:policy StudentPolicy --model=Student
```

```php
<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // tenant.access middleware già verifica accesso
    }

    public function view(User $user, Student $student): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Student $student): bool
    {
        return true;
    }

    public function delete(User $user, Student $student): bool
    {
        return true;
    }
}
```

Nota: la policy ora ritorna sempre `true` perché `tenant.access` middleware copre l'autorizzazione. La struttura è pronta per aggiungere logica futura (ruoli).

**Step 4: Commit**

```bash
git add app/Http/Requests/Tenant/ app/Policies/StudentPolicy.php
git commit -m "feat: add StoreStudentRequest, UpdateStudentRequest, and StudentPolicy"
```

---

### Task 3: StudentController + Routes

**Files:**
- Create: `app/Http/Controllers/Tenant/StudentController.php`
- Modify: `routes/tenant.php`

**Step 1: Crea StudentController**

```bash
php artisan make:controller Tenant/StudentController --resource
```

```php
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
    public function index(Request $request)
    {
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

        return Inertia::render('tenant/students/index', [
            'students' => $students,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
            'statuses' => array_map(
                fn (StudentStatus $s) => ['value' => $s->value, 'label' => $s->name],
                StudentStatus::cases()
            ),
        ]);
    }

    public function create()
    {
        return Inertia::render('tenant/students/create', [
            'statuses' => array_map(
                fn (StudentStatus $s) => ['value' => $s->value, 'label' => $s->name],
                StudentStatus::cases()
            ),
        ]);
    }

    public function store(StoreStudentRequest $request)
    {
        Student::create($request->validated());

        return redirect()->route('tenant.students.index', tenant('slug'))
            ->with('success', 'Allievo aggiunto con successo.');
    }

    public function show(Student $student)
    {
        return Inertia::render('tenant/students/show', [
            'student' => $student,
        ]);
    }

    public function edit(Student $student)
    {
        return Inertia::render('tenant/students/edit', [
            'student' => $student,
            'statuses' => array_map(
                fn (StudentStatus $s) => ['value' => $s->value, 'label' => $s->name],
                StudentStatus::cases()
            ),
        ]);
    }

    public function update(UpdateStudentRequest $request, Student $student)
    {
        $student->update($request->validated());

        return redirect()->route('tenant.students.show', [tenant('slug'), $student])
            ->with('success', 'Allievo aggiornato con successo.');
    }

    public function destroy(Student $student)
    {
        $student->delete(); // soft delete

        return redirect()->route('tenant.students.index', tenant('slug'))
            ->with('success', 'Allievo archiviato con successo.');
    }
}
```

**Step 2: Aggiungi route resource**

In `routes/tenant.php`, aggiungere dentro il gruppo:

```php
use App\Http\Controllers\Tenant\StudentController;

// dentro il ->group(function () { ... })
Route::resource('students', StudentController::class)
    ->names([
        'index'   => 'tenant.students.index',
        'create'  => 'tenant.students.create',
        'store'   => 'tenant.students.store',
        'show'    => 'tenant.students.show',
        'edit'    => 'tenant.students.edit',
        'update'  => 'tenant.students.update',
        'destroy' => 'tenant.students.destroy',
    ]);
```

**Step 3: Verifica route**

```bash
php artisan route:list --path=students
```

Expected: 7 route RESTful per students sotto il prefix `app/{tenant}`.

**Step 4: Commit**

```bash
git add app/Http/Controllers/Tenant/StudentController.php routes/tenant.php
git commit -m "feat: add StudentController with RESTful routes"
```

---

### Task 4: Types TypeScript per Student

**Files:**
- Create: `resources/js/types/student.ts`
- Modify: `resources/js/types/index.ts`

**Step 1: Crea types student**

File `resources/js/types/student.ts`:

```typescript
export type Student = {
    id: string;
    first_name: string;
    last_name: string;
    email: string | null;
    phone: string | null;
    date_of_birth: string | null;
    fiscal_code: string | null;
    address: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    notes: string | null;
    status: 'active' | 'inactive' | 'suspended';
    enrolled_at: string | null;
    created_at: string;
    updated_at: string;
};

export type StudentStatus = {
    value: string;
    label: string;
};

export type StudentFilters = {
    search: string;
    status: string;
    sort: string;
    direction: 'asc' | 'desc';
};

export type PaginatedData<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
};
```

**Step 2: Esporta da index.ts**

Aggiungere in `resources/js/types/index.ts`:

```typescript
export type * from './student';
```

**Step 3: Commit**

```bash
git add resources/js/types/student.ts resources/js/types/index.ts
git commit -m "feat: add TypeScript types for Student and pagination"
```

---

### Task 5: Componente StudentForm condiviso

**Files:**
- Create: `resources/js/components/student-form.tsx`

**Step 1: Crea il componente**

File `resources/js/components/student-form.tsx`:

```tsx
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { Student, StudentStatus } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

type StudentFormData = {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    date_of_birth: string;
    fiscal_code: string;
    address: string;
    emergency_contact_name: string;
    emergency_contact_phone: string;
    notes: string;
    status: string;
    enrolled_at: string;
};

type Props = {
    student?: Student;
    statuses: StudentStatus[];
    submitLabel: string;
};

export function StudentForm({ student, statuses, submitLabel }: Props) {
    const { tenant } = usePage().props as { tenant: { slug: string } };

    const { data, setData, post, put, processing, errors } = useForm<StudentFormData>({
        first_name: student?.first_name ?? '',
        last_name: student?.last_name ?? '',
        email: student?.email ?? '',
        phone: student?.phone ?? '',
        date_of_birth: student?.date_of_birth ?? '',
        fiscal_code: student?.fiscal_code ?? '',
        address: student?.address ?? '',
        emergency_contact_name: student?.emergency_contact_name ?? '',
        emergency_contact_phone: student?.emergency_contact_phone ?? '',
        notes: student?.notes ?? '',
        status: student?.status ?? 'active',
        enrolled_at: student?.enrolled_at ?? new Date().toISOString().split('T')[0],
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        if (student) {
            put(`/app/${tenant.slug}/students/${student.id}`);
        } else {
            post(`/app/${tenant.slug}/students`);
        }
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Dati personali</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="first_name">Nome *</Label>
                        <Input
                            id="first_name"
                            value={data.first_name}
                            onChange={(e) => setData('first_name', e.target.value)}
                        />
                        {errors.first_name && <p className="text-sm text-destructive">{errors.first_name}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="last_name">Cognome *</Label>
                        <Input
                            id="last_name"
                            value={data.last_name}
                            onChange={(e) => setData('last_name', e.target.value)}
                        />
                        {errors.last_name && <p className="text-sm text-destructive">{errors.last_name}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                        {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="phone">Telefono</Label>
                        <Input
                            id="phone"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                        />
                        {errors.phone && <p className="text-sm text-destructive">{errors.phone}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="date_of_birth">Data di nascita</Label>
                        <Input
                            id="date_of_birth"
                            type="date"
                            value={data.date_of_birth}
                            onChange={(e) => setData('date_of_birth', e.target.value)}
                        />
                        {errors.date_of_birth && <p className="text-sm text-destructive">{errors.date_of_birth}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="fiscal_code">Codice fiscale</Label>
                        <Input
                            id="fiscal_code"
                            value={data.fiscal_code}
                            onChange={(e) => setData('fiscal_code', e.target.value.toUpperCase())}
                            maxLength={16}
                        />
                        {errors.fiscal_code && <p className="text-sm text-destructive">{errors.fiscal_code}</p>}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Indirizzo</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-2">
                        <Label htmlFor="address">Indirizzo</Label>
                        <Input
                            id="address"
                            value={data.address}
                            onChange={(e) => setData('address', e.target.value)}
                        />
                        {errors.address && <p className="text-sm text-destructive">{errors.address}</p>}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Contatto di emergenza</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="emergency_contact_name">Nome contatto</Label>
                        <Input
                            id="emergency_contact_name"
                            value={data.emergency_contact_name}
                            onChange={(e) => setData('emergency_contact_name', e.target.value)}
                        />
                        {errors.emergency_contact_name && <p className="text-sm text-destructive">{errors.emergency_contact_name}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="emergency_contact_phone">Telefono contatto</Label>
                        <Input
                            id="emergency_contact_phone"
                            value={data.emergency_contact_phone}
                            onChange={(e) => setData('emergency_contact_phone', e.target.value)}
                        />
                        {errors.emergency_contact_phone && <p className="text-sm text-destructive">{errors.emergency_contact_phone}</p>}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Iscrizione</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="status">Stato</Label>
                        <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {statuses.map((s) => (
                                    <SelectItem key={s.value} value={s.value}>
                                        {s.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.status && <p className="text-sm text-destructive">{errors.status}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="enrolled_at">Data iscrizione</Label>
                        <Input
                            id="enrolled_at"
                            type="date"
                            value={data.enrolled_at}
                            onChange={(e) => setData('enrolled_at', e.target.value)}
                        />
                        {errors.enrolled_at && <p className="text-sm text-destructive">{errors.enrolled_at}</p>}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Note</CardTitle>
                </CardHeader>
                <CardContent>
                    <Textarea
                        id="notes"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        rows={4}
                        placeholder="Note aggiuntive sull'allievo..."
                    />
                    {errors.notes && <p className="text-sm text-destructive">{errors.notes}</p>}
                </CardContent>
            </Card>

            <div className="flex justify-end">
                <Button type="submit" disabled={processing}>
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
```

**Step 2: Commit**

```bash
git add resources/js/components/student-form.tsx
git commit -m "feat: add StudentForm shared component"
```

---

### Task 6: Pagina lista allievi (index)

**Files:**
- Create: `resources/js/pages/tenant/students/index.tsx`

**Step 1: Crea la pagina**

File `resources/js/pages/tenant/students/index.tsx`:

```tsx
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import TenantLayout from '@/layouts/tenant-layout';
import type { PaginatedData, Student, StudentFilters, StudentStatus } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowUpDown, Plus } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = {
    students: PaginatedData<Student>;
    filters: StudentFilters;
    statuses: StudentStatus[];
};

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive'> = {
    active: 'default',
    inactive: 'secondary',
    suspended: 'destructive',
};

const statusLabel: Record<string, string> = {
    active: 'Attivo',
    inactive: 'Inattivo',
    suspended: 'Sospeso',
};

export default function StudentsIndex({ students, filters, statuses }: Props) {
    const { tenant } = usePage().props as { tenant: { slug: string } };
    const prefix = `/app/${tenant.slug}`;

    function handleSearch(value: string) {
        router.get(`${prefix}/students`, { ...filters, search: value, page: 1 }, {
            preserveState: true,
            replace: true,
        });
    }

    function handleStatusFilter(value: string) {
        router.get(`${prefix}/students`, { ...filters, status: value === 'all' ? '' : value, page: 1 }, {
            preserveState: true,
            replace: true,
        });
    }

    function handleSort(field: string) {
        const direction = filters.sort === field && filters.direction === 'asc' ? 'desc' : 'asc';
        router.get(`${prefix}/students`, { ...filters, sort: field, direction }, {
            preserveState: true,
            replace: true,
        });
    }

    function SortableHeader({ field, children }: { field: string; children: React.ReactNode }) {
        return (
            <TableHead>
                <button
                    className="flex items-center gap-1 hover:text-foreground"
                    onClick={() => handleSort(field)}
                >
                    {children}
                    <ArrowUpDown className="size-4" />
                </button>
            </TableHead>
        );
    }

    return (
        <>
            <Head title="Allievi" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Allievi</h1>
                    <Button asChild>
                        <Link href={`${prefix}/students/create`}>
                            <Plus className="mr-2 size-4" />
                            Nuovo allievo
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-2 sm:flex-row">
                    <Input
                        placeholder="Cerca per nome, cognome o email..."
                        defaultValue={filters.search}
                        onChange={(e) => handleSearch(e.target.value)}
                        className="sm:max-w-sm"
                    />
                    <Select
                        value={filters.status || 'all'}
                        onValueChange={handleStatusFilter}
                    >
                        <SelectTrigger className="sm:w-48">
                            <SelectValue placeholder="Tutti gli stati" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tutti gli stati</SelectItem>
                            {statuses.map((s) => (
                                <SelectItem key={s.value} value={s.value}>
                                    {s.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHeader field="last_name">Cognome</SortableHeader>
                                <SortableHeader field="first_name">Nome</SortableHeader>
                                <TableHead className="hidden md:table-cell">Email</TableHead>
                                <TableHead className="hidden sm:table-cell">Telefono</TableHead>
                                <SortableHeader field="status">Stato</SortableHeader>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {students.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="text-center text-muted-foreground py-8">
                                        Nessun allievo trovato.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                students.data.map((student) => (
                                    <TableRow key={student.id}>
                                        <TableCell>
                                            <Link
                                                href={`${prefix}/students/${student.id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {student.last_name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>{student.first_name}</TableCell>
                                        <TableCell className="hidden md:table-cell">
                                            {student.email ?? '—'}
                                        </TableCell>
                                        <TableCell className="hidden sm:table-cell">
                                            {student.phone ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={statusVariant[student.status]}>
                                                {statusLabel[student.status]}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {students.meta.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            {students.meta.from}–{students.meta.to} di {students.meta.total} allievi
                        </p>
                        <div className="flex gap-2">
                            {students.meta.links.map((link, i) => (
                                <Button
                                    key={i}
                                    variant={link.active ? 'default' : 'outline'}
                                    size="sm"
                                    disabled={!link.url}
                                    asChild={!!link.url}
                                >
                                    {link.url ? (
                                        <Link
                                            href={link.url}
                                            preserveState
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ) : (
                                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                    )}
                                </Button>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

StudentsIndex.layout = (page: ReactNode) => <TenantLayout breadcrumbs={[{ title: 'Allievi', href: '#' }]}>{page}</TenantLayout>;
```

**Step 2: Verifica compilazione**

```bash
npm run build
```

**Step 3: Commit**

```bash
git add resources/js/pages/tenant/students/index.tsx
git commit -m "feat: add students index page with search, filters, and sorting"
```

---

### Task 7: Pagine create, edit, show

**Files:**
- Create: `resources/js/pages/tenant/students/create.tsx`
- Create: `resources/js/pages/tenant/students/edit.tsx`
- Create: `resources/js/pages/tenant/students/show.tsx`

**Step 1: Crea pagina create**

File `resources/js/pages/tenant/students/create.tsx`:

```tsx
import { StudentForm } from '@/components/student-form';
import TenantLayout from '@/layouts/tenant-layout';
import type { StudentStatus } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

type Props = {
    statuses: StudentStatus[];
};

export default function StudentsCreate({ statuses }: Props) {
    return (
        <>
            <Head title="Nuovo allievo" />
            <div className="mx-auto w-full max-w-2xl p-4">
                <h1 className="mb-6 text-2xl font-semibold">Nuovo allievo</h1>
                <StudentForm statuses={statuses} submitLabel="Aggiungi allievo" />
            </div>
        </>
    );
}

StudentsCreate.layout = (page: ReactNode) => (
    <TenantLayout breadcrumbs={[
        { title: 'Allievi', href: '../students' },
        { title: 'Nuovo', href: '#' },
    ]}>
        {page}
    </TenantLayout>
);
```

**Step 2: Crea pagina edit**

File `resources/js/pages/tenant/students/edit.tsx`:

```tsx
import { StudentForm } from '@/components/student-form';
import TenantLayout from '@/layouts/tenant-layout';
import type { Student, StudentStatus } from '@/types';
import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

type Props = {
    student: Student;
    statuses: StudentStatus[];
};

export default function StudentsEdit({ student, statuses }: Props) {
    return (
        <>
            <Head title={`Modifica ${student.first_name} ${student.last_name}`} />
            <div className="mx-auto w-full max-w-2xl p-4">
                <h1 className="mb-6 text-2xl font-semibold">
                    Modifica allievo
                </h1>
                <StudentForm student={student} statuses={statuses} submitLabel="Salva modifiche" />
            </div>
        </>
    );
}

StudentsEdit.layout = (page: ReactNode) => {
    const { student } = page.props as unknown as Props;
    return (
        <TenantLayout breadcrumbs={[
            { title: 'Allievi', href: '../../students' },
            { title: `${student.last_name} ${student.first_name}`, href: `../../students/${student.id}` },
            { title: 'Modifica', href: '#' },
        ]}>
            {page}
        </TenantLayout>
    );
};
```

**Step 3: Crea pagina show**

File `resources/js/pages/tenant/students/show.tsx`:

```tsx
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import TenantLayout from '@/layouts/tenant-layout';
import type { Student } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = {
    student: Student;
};

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive'> = {
    active: 'default',
    inactive: 'secondary',
    suspended: 'destructive',
};

const statusLabel: Record<string, string> = {
    active: 'Attivo',
    inactive: 'Inattivo',
    suspended: 'Sospeso',
};

function Field({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="mt-1">{value || '—'}</dd>
        </div>
    );
}

export default function StudentsShow({ student }: Props) {
    const { tenant } = usePage().props as { tenant: { slug: string } };
    const prefix = `/app/${tenant.slug}`;

    function handleDelete() {
        router.delete(`${prefix}/students/${student.id}`);
    }

    return (
        <>
            <Head title={`${student.first_name} ${student.last_name}`} />
            <div className="mx-auto w-full max-w-2xl p-4">
                <div className="mb-6 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-semibold">
                            {student.last_name} {student.first_name}
                        </h1>
                        <Badge variant={statusVariant[student.status]}>
                            {statusLabel[student.status]}
                        </Badge>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`${prefix}/students/${student.id}/edit`}>
                                <Pencil className="mr-2 size-4" />
                                Modifica
                            </Link>
                        </Button>
                        <AlertDialog>
                            <AlertDialogTrigger asChild>
                                <Button variant="destructive">
                                    <Trash2 className="mr-2 size-4" />
                                    Archivia
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>Archiviare questo allievo?</AlertDialogTitle>
                                    <AlertDialogDescription>
                                        L'allievo {student.first_name} {student.last_name} verrà archiviato.
                                        Potrai recuperarlo in futuro se necessario.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>Annulla</AlertDialogCancel>
                                    <AlertDialogAction onClick={handleDelete}>
                                        Archivia
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </div>
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Dati personali</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Field label="Nome" value={student.first_name} />
                                <Field label="Cognome" value={student.last_name} />
                                <Field label="Email" value={student.email} />
                                <Field label="Telefono" value={student.phone} />
                                <Field label="Data di nascita" value={student.date_of_birth} />
                                <Field label="Codice fiscale" value={student.fiscal_code} />
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Indirizzo</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Field label="Indirizzo" value={student.address} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Contatto di emergenza</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Field label="Nome contatto" value={student.emergency_contact_name} />
                                <Field label="Telefono contatto" value={student.emergency_contact_phone} />
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Iscrizione</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Field label="Data iscrizione" value={student.enrolled_at} />
                                <Field label="Stato" value={statusLabel[student.status]} />
                            </dl>
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

StudentsShow.layout = (page: ReactNode) => {
    const { student } = page.props as unknown as Props;
    return (
        <TenantLayout breadcrumbs={[
            { title: 'Allievi', href: '../students' },
            { title: `${student.last_name} ${student.first_name}`, href: '#' },
        ]}>
            {page}
        </TenantLayout>
    );
};
```

**Step 4: Verifica compilazione**

```bash
npm run build
```

**Step 5: Commit**

```bash
git add resources/js/pages/tenant/students/
git commit -m "feat: add students create, edit, and show pages"
```

---

### Task 8: Feature tests

**Files:**
- Create: `tests/Feature/Tenant/StudentControllerTest.php`
- Create: `database/factories/TenantFactory.php`

**Step 1: Crea TenantFactory**

File `database/factories/TenantFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Tenant> */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(4),
            'owner_id' => User::factory(),
        ];
    }
}
```

**Step 2: Crea i test**

File `tests/Feature/Tenant/StudentControllerTest.php`:

```php
<?php

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->tenant = Tenant::factory()->create(['owner_id' => $this->user->id]);
    $this->user->update(['current_tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);

    // Inizializza tenancy per i test
    tenancy()->initialize($this->tenant);
});

afterEach(function () {
    tenancy()->end();
});

test('index mostra la lista allievi', function () {
    Student::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    $response = $this->get("/app/{$this->tenant->slug}/students");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/students/index')
        ->has('students.data', 3)
    );
});

test('index filtra per stato', function () {
    Student::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'status' => StudentStatus::Active]);
    Student::factory()->inactive()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->get("/app/{$this->tenant->slug}/students?status=active");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('students.data', 2)
    );
});

test('index cerca per nome', function () {
    Student::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Marco', 'last_name' => 'Rossi']);
    Student::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Luca', 'last_name' => 'Bianchi']);

    $response = $this->get("/app/{$this->tenant->slug}/students?search=Marco");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('students.data', 1)
    );
});

test('create mostra il form', function () {
    $response = $this->get("/app/{$this->tenant->slug}/students/create");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/students/create')
    );
});

test('store crea un allievo', function () {
    $response = $this->post("/app/{$this->tenant->slug}/students", [
        'first_name' => 'Marco',
        'last_name' => 'Rossi',
        'email' => 'marco@example.com',
        'status' => 'active',
    ]);

    $response->assertRedirect("/app/{$this->tenant->slug}/students");
    $this->assertDatabaseHas('students', [
        'first_name' => 'Marco',
        'last_name' => 'Rossi',
        'tenant_id' => $this->tenant->id,
    ]);
});

test('store valida i campi obbligatori', function () {
    $response = $this->post("/app/{$this->tenant->slug}/students", []);

    $response->assertSessionHasErrors(['first_name', 'last_name']);
});

test('store valida email unica per tenant', function () {
    Student::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'mario@example.com',
    ]);

    $response = $this->post("/app/{$this->tenant->slug}/students", [
        'first_name' => 'Mario',
        'last_name' => 'Verdi',
        'email' => 'mario@example.com',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('show mostra i dettagli allievo', function () {
    $student = Student::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->get("/app/{$this->tenant->slug}/students/{$student->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/students/show')
        ->has('student')
    );
});

test('edit mostra il form con dati precompilati', function () {
    $student = Student::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->get("/app/{$this->tenant->slug}/students/{$student->id}/edit");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/students/edit')
        ->has('student')
    );
});

test('update aggiorna un allievo', function () {
    $student = Student::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->put("/app/{$this->tenant->slug}/students/{$student->id}", [
        'first_name' => 'NuovoNome',
        'last_name' => 'NuovoCognome',
    ]);

    $response->assertRedirect("/app/{$this->tenant->slug}/students/{$student->id}");
    $this->assertDatabaseHas('students', [
        'id' => $student->id,
        'first_name' => 'NuovoNome',
    ]);
});

test('destroy archivia un allievo (soft delete)', function () {
    $student = Student::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->delete("/app/{$this->tenant->slug}/students/{$student->id}");

    $response->assertRedirect("/app/{$this->tenant->slug}/students");
    $this->assertSoftDeleted('students', ['id' => $student->id]);
});

test('un utente non può accedere agli allievi di un altro tenant', function () {
    $otherUser = User::factory()->create();
    $otherTenant = Tenant::factory()->create(['owner_id' => $otherUser->id]);

    $response = $this->get("/app/{$otherTenant->slug}/students");

    $response->assertForbidden();
});
```

**Step 3: Esegui i test**

```bash
php artisan test tests/Feature/Tenant/StudentControllerTest.php
```

Expected: tutti i test passano.

**Step 4: Commit**

```bash
git add database/factories/TenantFactory.php tests/Feature/Tenant/StudentControllerTest.php
git commit -m "test: add feature tests for StudentController with tenant isolation"
```

---

### Task 9: Verifica end-to-end e fix

**Step 1: Esegui tutti i test**

```bash
php artisan test
```

Expected: tutti i test passano, compresi quelli preesistenti.

**Step 2: Verifica build frontend**

```bash
npm run build
```

Expected: nessun errore TypeScript o di build.

**Step 3: Fix eventuali problemi**

Correggi errori emersi dai test o dalla build.

**Step 4: Commit finale se ci sono fix**

```bash
git add -A
git commit -m "fix: resolve issues from end-to-end verification"
```
