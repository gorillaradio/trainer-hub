# TrainerHub Fondamenta v2 — Piano di Implementazione

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Setup completo di TrainerHub — database multi-tenant con stancl/tenancy, auth adattata, onboarding tenant, middleware e layout.

**Architecture:** Single database multi-tenant con stancl/tenancy in modalità single-database + path-based identification (`/app/{tenant}/...`). UUID come primary key. Laravel 12 + React 19 via starter kit, Inertia.js 2.x, shadcn/ui.

**Tech Stack:** PHP 8.3, Laravel 12, React 19, TypeScript, Inertia.js 2.x, Tailwind CSS 4, shadcn/ui, MySQL 8, Vite, stancl/tenancy v3

**Riferimenti:**
- Convenzioni: `CLAUDE.md`
- Architettura: `docs/architecture.md`
- Piano precedente (v1): `docs/plans/2026-03-08-trainerhub-fondamenta.md`

**Cambiamenti rispetto a v1:**
- ULID → UUID ovunque (compatibilità stancl/tenancy, supporto nativo Laravel)
- Trait `BelongsToTenant` custom → `Stancl\Tenancy\Database\Concerns\BelongsToTenant`
- Middleware `IdentifyTenant` custom → `InitializeTenancyByPath` di stancl
- Aggiunto setup e configurazione stancl/tenancy

**Stato di partenza:** Rollback a commit `40551ef` (Task 3 completato — scaffolding, shadcn/ui, database MySQL configurato). I task 4-7 del piano v1 vengono rifatti con le nuove convenzioni.

---

## Come usare questo piano

- Ogni task è indipendente e può essere ripreso in una sessione diversa
- I checkbox `[ ]` → `[x]` tracciano lo stato di completamento
- Ogni task ha un **commit** finale — se il commit è fatto, il task è completato
- All'inizio di ogni sessione, leggi questo file per sapere dove sei rimasto

---

## Task 0: Rollback e Preparazione

**Stato:** `[x]` — Commit: `2027dd3`

**Goal:** Tornare al commit `40551ef` e preparare il terreno per le nuove migrazioni.

**Steps:**

- [ ] **0.1** Rollback al commit `40551ef`:
  ```bash
  cd trainer-hub
  git reset --hard 40551ef
  ```

- [ ] **0.2** Aggiorna `CLAUDE.md`: sostituisci tutte le menzioni di ULID con UUID e aggiungi stancl/tenancy allo stack:
  - `IDs: UUID ovunque (trait HasUuids)` (era `ULID ovunque (trait HasUlids)`)
  - Aggiungi `Multi-tenancy: stancl/tenancy v3 in modalità single-database` allo stack
  - `BelongsToTenant` ora è `Stancl\Tenancy\Database\Concerns\BelongsToTenant`

- [ ] **0.3** Aggiorna `docs/architecture.md`: sostituisci tutte le menzioni di ULID con UUID:
  - Schema database: `id (ULID)` → `id (UUID)` ovunque
  - Modelli: `HasUlids` → `HasUuids`
  - File storage paths: `{tenant_ulid}` → `{tenant_uuid}`, `{student_ulid}` → `{student_uuid}`

- [ ] **0.4** Svuota il database per ripartire puliti:
  ```bash
  php artisan db:wipe
  ```

- [ ] **0.5** Commit:
  ```bash
  git add CLAUDE.md docs/architecture.md
  git commit -m "chore: switch from ULID to UUID, align docs with stancl/tenancy"
  ```

---

## Task 1: Installare stancl/tenancy

**Stato:** `[x]` — Commit: `33b0262`

**Goal:** Installare il pacchetto e configurarlo per single-database + path-based.

**Steps:**

- [ ] **1.1** Installa il pacchetto:
  ```bash
  composer require stancl/tenancy
  ```

- [ ] **1.2** Esegui il comando di installazione:
  ```bash
  php artisan tenancy:install
  ```
  Questo crea:
  - `config/tenancy.php`
  - `app/Providers/TenancyServiceProvider.php`
  - `routes/tenant.php` (lo sovrascriveremo)
  - Migrazioni in `database/migrations/` (tenant tables del pacchetto)

- [ ] **1.3** Registra il service provider in `bootstrap/providers.php`:
  ```php
  return [
      App\Providers\AppServiceProvider::class,
      App\Providers\FortifyServiceProvider::class,
      App\Providers\TenancyServiceProvider::class, // ← aggiungi
  ];
  ```

- [ ] **1.4** Configura `config/tenancy.php` per single-database:
  - Imposta `'tenant_model' => App\Models\Tenant::class`
  - Nella sezione `bootstrappers`, commenta/rimuovi `DatabaseTenancyBootstrapper` (non serve, usiamo un solo database)

- [ ] **1.5** Configura `TenancyServiceProvider` per single-database:
  - Apri `app/Providers/TenancyServiceProvider.php`
  - Nel metodo `events()`, commenta/rimuovi i job `CreateDatabase`, `MigrateDatabase`, `SeedDatabase` dall'evento `TenantCreated`
  - Commenta/rimuovi il job `DeleteDatabase` dall'evento `TenantDeleted`

- [ ] **1.6** Elimina le migrazioni generate dal pacchetto per il multi-database (se presenti):
  - Le migrazioni `create_tenants_table` e `create_domains_table` generate da stancl vanno eliminate perché creeremo le nostre migrazioni custom
  - Verifica con `ls database/migrations/*tenant*` e `ls database/migrations/*domain*`

- [ ] **1.7** Verifica:
  ```bash
  php artisan about | grep -i tenancy
  ```

- [ ] **1.8** Commit:
  ```bash
  git add -A
  git commit -m "chore: install and configure stancl/tenancy for single-database mode"
  ```

---

## Task 2: Migrazioni Database (UUID)

**Stato:** `[x]` — Commit: `6490803`

**Goal:** Creare tutte le migrazioni con UUID come primary key.

**Dipende da:** Task 1

**Note:** La migrazione users originale va modificata (il file esiste già dallo starter kit). Le altre migrazioni vanno create da zero. stancl/tenancy richiede che la colonna `tenant_id` sia `string` (non UUID type) perché il pacchetto usa ID string di default.

**Files da creare/modificare:**

- [ ] **2.1** Modifica `database/migrations/0001_01_01_000000_create_users_table.php`:
  ```php
  // Cambia $table->id() in:
  $table->uuid('id')->primary();
  // Cambia la FK sessions in:
  $table->foreignUuid('user_id')->nullable()->index();
  ```

- [ ] **2.2** Crea `database/migrations/0001_01_01_000003_create_tenants_table.php`:
  ```php
  Schema::create('tenants', function (Blueprint $table) {
      $table->string('id')->primary();  // stancl/tenancy usa string ID
      $table->string('name');
      $table->string('slug')->unique();
      $table->string('domain')->nullable();
      $table->foreignUuid('owner_id')->constrained('users');
      $table->json('settings')->nullable();
      $table->json('data')->nullable();  // richiesto da stancl/tenancy
      $table->string('stripe_account_id')->nullable();
      $table->string('status')->default('active');
      $table->timestamp('trial_ends_at')->nullable();
      $table->timestamps();
  });
  ```
  **Nota:** stancl/tenancy usa `string` per l'ID del tenant e genera UUID internamente. La colonna `data` è richiesta dal pacchetto per attributi dinamici.

- [ ] **2.3** Crea `database/migrations/0001_01_01_000004_add_current_tenant_id_to_users_table.php`:
  ```php
  Schema::table('users', function (Blueprint $table) {
      $table->string('current_tenant_id')->nullable();
      $table->foreign('current_tenant_id')->references('id')->on('tenants')->nullOnDelete();
  });
  ```
  **Nota:** `string` FK perché il tenant ID è string.

- [ ] **2.4** Crea `database/migrations/0001_01_01_000005_create_students_table.php`:
  ```php
  Schema::create('students', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->string('tenant_id');
      $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
      $table->string('first_name');
      $table->string('last_name');
      $table->string('email')->nullable();
      $table->string('phone')->nullable();
      $table->date('date_of_birth')->nullable();
      $table->string('fiscal_code')->nullable();
      $table->string('address')->nullable();
      $table->string('emergency_contact_name')->nullable();
      $table->string('emergency_contact_phone')->nullable();
      $table->text('notes')->nullable();
      $table->string('status')->default('active');
      $table->date('enrolled_at')->nullable();
      $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamps();

      $table->index(['tenant_id', 'status']);
      $table->index(['tenant_id', 'last_name', 'first_name']);
  });
  ```
  **Nota:** `tenant_id` è `string` + FK manuale (non `foreignUuid`) perché il tenant ID è string.

- [ ] **2.5** Crea `database/migrations/0001_01_01_000006_create_enrollment_fees_table.php`:
  ```php
  Schema::create('enrollment_fees', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->string('tenant_id');
      $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
      $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
      $table->integer('amount');
      $table->timestamp('paid_at')->nullable();
      $table->string('payment_method')->nullable();
      $table->string('academic_year');
      $table->text('notes')->nullable();
      $table->timestamps();

      $table->unique(['tenant_id', 'student_id', 'academic_year']);
  });
  ```

- [ ] **2.6** Crea `database/migrations/0001_01_01_000007_create_monthly_fees_table.php`:
  ```php
  Schema::create('monthly_fees', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->string('tenant_id');
      $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
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
  ```

- [ ] **2.7** Crea `database/migrations/0001_01_01_000008_create_documents_table.php`:
  ```php
  Schema::create('documents', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->string('tenant_id');
      $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
      $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
      $table->string('type');
      $table->string('title');
      $table->string('file_path');
      $table->date('delivered_at')->nullable();
      $table->date('expires_at')->nullable();
      $table->string('status')->default('pending');
      $table->text('notes')->nullable();
      $table->timestamps();

      $table->index(['tenant_id', 'student_id', 'type']);
      $table->index(['tenant_id', 'expires_at']);
  });
  ```

- [ ] **2.8** Esegui le migrazioni e verifica:
  ```bash
  php artisan migrate:fresh
  php artisan migrate:status
  ```

- [ ] **2.9** Commit:
  ```bash
  git add database/migrations/
  git commit -m "feat: add database migrations for all tables (UUID, stancl/tenancy compatible)"
  ```

---

## Task 3: Enums PHP

**Stato:** `[x]` — Commit: `49349a4`

**Goal:** Creare i backed enums per status e tipi. (Identico al Task 5 del piano v1)

**Files da creare:**

- [ ] **3.1** Crea `app/Enums/StudentStatus.php`:
  ```php
  <?php

  namespace App\Enums;

  enum StudentStatus: string
  {
      case Active = 'active';
      case Inactive = 'inactive';
      case Suspended = 'suspended';
  }
  ```

- [ ] **3.2** Crea `app/Enums/PaymentMethod.php`:
  ```php
  <?php

  namespace App\Enums;

  enum PaymentMethod: string
  {
      case Cash = 'cash';
      case Transfer = 'transfer';
      case Card = 'card';
      case Online = 'online';
  }
  ```

- [ ] **3.3** Crea `app/Enums/DocumentType.php`:
  ```php
  <?php

  namespace App\Enums;

  enum DocumentType: string
  {
      case MedicalCertificate = 'medical_certificate';
      case IdentityDoc = 'identity_doc';
      case PrivacyConsent = 'privacy_consent';
      case Other = 'other';
  }
  ```

- [ ] **3.4** Crea `app/Enums/DocumentStatus.php`:
  ```php
  <?php

  namespace App\Enums;

  enum DocumentStatus: string
  {
      case Pending = 'pending';
      case Delivered = 'delivered';
      case Expired = 'expired';
      case ExpiringSoon = 'expiring_soon';
  }
  ```

- [ ] **3.5** Commit:
  ```bash
  git add app/Enums/
  git commit -m "feat: add PHP enums for student status, payment method, document type/status"
  ```

---

## Task 4: Modello Tenant (stancl/tenancy)

**Stato:** `[x]` — Commit: `cc4ab74`

**Goal:** Creare il modello Tenant che estende il modello base di stancl/tenancy.

**Dipende da:** Task 1, Task 2

**File da creare:** `app/Models/Tenant.php`

- [ ] **4.1** Crea `app/Models/Tenant.php`:
  ```php
  <?php

  namespace App\Models;

  use Illuminate\Database\Eloquent\Relations\BelongsTo;
  use Illuminate\Database\Eloquent\Relations\HasMany;
  use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

  class Tenant extends BaseTenant
  {
      public static function getCustomColumns(): array
      {
          return [
              'id',
              'name',
              'slug',
              'domain',
              'owner_id',
              'settings',
              'stripe_account_id',
              'status',
              'trial_ends_at',
          ];
      }

      protected $casts = [
          'settings' => 'array',
          'trial_ends_at' => 'datetime',
      ];

      public function owner(): BelongsTo
      {
          return $this->belongsTo(User::class, 'owner_id');
      }

      public function students(): HasMany
      {
          return $this->hasMany(Student::class);
      }
  }
  ```

- [ ] **4.2** Verifica che `config/tenancy.php` punti al modello giusto:
  ```php
  'tenant_model' => \App\Models\Tenant::class,
  ```

- [ ] **4.3** Verifica con tinker:
  ```bash
  php artisan tinker --execute="echo App\Models\Tenant::class;"
  ```

- [ ] **4.4** Commit:
  ```bash
  git add app/Models/Tenant.php config/tenancy.php
  git commit -m "feat: add Tenant model extending stancl/tenancy base"
  ```

---

## Task 5: Modelli Eloquent

**Stato:** `[x]` — Commit: `e0ef319`

**Goal:** Creare/modificare tutti i modelli con relazioni, cast e trait di stancl/tenancy.

**Dipende da:** Task 3, Task 4

**Differenze da v1:** `HasUuids` invece di `HasUlids`, `Stancl\Tenancy\Database\Concerns\BelongsToTenant` invece del trait custom.

**Files:**

- [ ] **5.1** Modifica `app/Models/User.php`:
  - Aggiungi `use Illuminate\Database\Eloquent\Concerns\HasUuids;`
  - Aggiungi `use HasUuids` nel trait block
  - Aggiungi relazioni:
    ```php
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'owner_id');
    }

    public function currentTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'current_tenant_id');
    }
    ```
  - Aggiungi `'current_tenant_id'` al `$fillable`

- [ ] **5.2** Crea `app/Models/Student.php`:
  ```php
  <?php

  namespace App\Models;

  use App\Enums\StudentStatus;
  use Illuminate\Database\Eloquent\Concerns\HasUuids;
  use Illuminate\Database\Eloquent\Model;
  use Illuminate\Database\Eloquent\Relations\BelongsTo;
  use Illuminate\Database\Eloquent\Relations\HasMany;
  use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

  class Student extends Model
  {
      use HasUuids, BelongsToTenant;

      protected $fillable = [
          'first_name', 'last_name', 'email', 'phone',
          'date_of_birth', 'fiscal_code', 'address',
          'emergency_contact_name', 'emergency_contact_phone',
          'notes', 'status', 'enrolled_at',
      ];

      protected $casts = [
          'date_of_birth' => 'date',
          'enrolled_at' => 'date',
          'status' => StudentStatus::class,
      ];

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

      public function unpaidFees(): HasMany
      {
          return $this->monthlyFees()->whereNull('paid_at');
      }

      public function expiringDocuments(): HasMany
      {
          return $this->documents()
              ->whereNotNull('expires_at')
              ->where('expires_at', '<=', now()->addDays(30));
      }
  }
  ```

- [ ] **5.3** Crea `app/Models/EnrollmentFee.php`:
  ```php
  <?php

  namespace App\Models;

  use App\Enums\PaymentMethod;
  use Illuminate\Database\Eloquent\Concerns\HasUuids;
  use Illuminate\Database\Eloquent\Model;
  use Illuminate\Database\Eloquent\Relations\BelongsTo;
  use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

  class EnrollmentFee extends Model
  {
      use HasUuids, BelongsToTenant;

      protected $fillable = [
          'student_id', 'amount', 'paid_at',
          'payment_method', 'academic_year', 'notes',
      ];

      protected $casts = [
          'amount' => 'integer',
          'paid_at' => 'datetime',
          'payment_method' => PaymentMethod::class,
      ];

      public function student(): BelongsTo
      {
          return $this->belongsTo(Student::class);
      }
  }
  ```

- [ ] **5.4** Crea `app/Models/MonthlyFee.php`:
  ```php
  <?php

  namespace App\Models;

  use App\Enums\PaymentMethod;
  use Illuminate\Database\Eloquent\Concerns\HasUuids;
  use Illuminate\Database\Eloquent\Model;
  use Illuminate\Database\Eloquent\Relations\BelongsTo;
  use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

  class MonthlyFee extends Model
  {
      use HasUuids, BelongsToTenant;

      protected $fillable = [
          'student_id', 'amount', 'due_date',
          'paid_at', 'payment_method', 'period', 'notes',
      ];

      protected $casts = [
          'amount' => 'integer',
          'due_date' => 'date',
          'paid_at' => 'datetime',
          'payment_method' => PaymentMethod::class,
      ];

      public function student(): BelongsTo
      {
          return $this->belongsTo(Student::class);
      }
  }
  ```

- [ ] **5.5** Crea `app/Models/Document.php`:
  ```php
  <?php

  namespace App\Models;

  use App\Enums\DocumentStatus;
  use App\Enums\DocumentType;
  use Illuminate\Database\Eloquent\Concerns\HasUuids;
  use Illuminate\Database\Eloquent\Model;
  use Illuminate\Database\Eloquent\Relations\BelongsTo;
  use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

  class Document extends Model
  {
      use HasUuids, BelongsToTenant;

      protected $fillable = [
          'student_id', 'type', 'title', 'file_path',
          'delivered_at', 'expires_at', 'status', 'notes',
      ];

      protected $casts = [
          'delivered_at' => 'date',
          'expires_at' => 'date',
          'type' => DocumentType::class,
          'status' => DocumentStatus::class,
      ];

      public function student(): BelongsTo
      {
          return $this->belongsTo(Student::class);
      }
  }
  ```

- [ ] **5.6** Verifica con tinker — crea un utente, un tenant, inizializza tenancy, crea uno student:
  ```bash
  php artisan tinker
  ```
  ```php
  $u = App\Models\User::create(['name' => 'Test', 'email' => 'test@test.com', 'password' => bcrypt('password')]);
  $t = App\Models\Tenant::create(['name' => 'Palestra Test', 'slug' => 'palestra-test', 'owner_id' => $u->id]);
  tenancy()->initialize($t);
  $s = App\Models\Student::create(['first_name' => 'Mario', 'last_name' => 'Rossi', 'status' => 'active']);
  echo $s->tenant_id; // deve essere uguale a $t->id
  App\Models\Student::all(); // deve restituire solo gli student del tenant
  ```

- [ ] **5.7** Pulisci i dati di test:
  ```bash
  php artisan migrate:fresh
  ```

- [ ] **5.8** Commit:
  ```bash
  git add app/Models/
  git commit -m "feat: add all Eloquent models with stancl/tenancy BelongsToTenant and UUID"
  ```

---

## Task 6: Adattare Autenticazione (Redirect)

**Stato:** `[x]` — Commit: `f660ba1`

**Goal:** Dopo login/registrazione, redirect intelligente tramite route pivot `/dashboard`.

**Dipende da:** Task 5

**Approccio:** Non tocchiamo Fortify. La route `/dashboard` fa da router — controlla lo stato dell'utente e redirige al posto giusto.

**Files da modificare:**

- [ ] **6.1** Modifica `routes/web.php` — trasforma la route `/dashboard` da pagina Inertia a redirect intelligente:
  ```php
  // Sostituisci:
  // Route::inertia('dashboard', 'dashboard')->name('dashboard');
  // Con:
  Route::get('dashboard', function () {
      $user = auth()->user();

      if ($user->current_tenant_id && $user->currentTenant) {
          return redirect()->route('tenant.dashboard', $user->currentTenant->slug);
      }

      return redirect()->route('onboarding.create');
  })->name('dashboard');
  ```
  Fortify manda tutti a `/dashboard`, e `/dashboard` smista l'utente.

- [ ] **6.2** Verifica:
  ```bash
  npm run build
  php artisan route:list --name=dashboard
  ```

- [ ] **6.3** Commit:
  ```bash
  git add routes/web.php
  git commit -m "feat: smart dashboard redirect based on tenant state"
  ```

---

## Task 7: Onboarding Controller + Pagina

**Stato:** `[x]` — Commit: `3813e18`

**Goal:** Il trainer crea il suo primo tenant dopo la registrazione.

**Dipende da:** Task 6

**Files da creare:**
- `app/Http/Controllers/Central/OnboardingController.php`
- `resources/js/pages/central/onboarding/create.tsx`

- [ ] **7.1** Crea `app/Http/Controllers/Central/OnboardingController.php`:
  ```php
  <?php

  namespace App\Http\Controllers\Central;

  use App\Http\Controllers\Controller;
  use App\Models\Tenant;
  use Illuminate\Http\Request;
  use Illuminate\Support\Str;
  use Inertia\Inertia;

  class OnboardingController extends Controller
  {
      public function create()
      {
          // Se ha già un tenant, vai alla dashboard
          if (auth()->user()->current_tenant_id) {
              return redirect()->route('tenant.dashboard', auth()->user()->currentTenant->slug);
          }

          return Inertia::render('central/onboarding/create');
      }

      public function store(Request $request)
      {
          $validated = $request->validate([
              'name' => ['required', 'string', 'max:255'],
          ]);

          $slug = Str::slug($validated['name']);

          // Assicurati che lo slug sia unico
          $originalSlug = $slug;
          $counter = 1;
          while (Tenant::where('slug', $slug)->exists()) {
              $slug = $originalSlug . '-' . $counter++;
          }

          $tenant = Tenant::create([
              'name' => $validated['name'],
              'slug' => $slug,
              'owner_id' => auth()->id(),
          ]);

          auth()->user()->update(['current_tenant_id' => $tenant->id]);

          return redirect()->route('tenant.dashboard', $tenant->slug);
      }
  }
  ```

- [ ] **7.2** Aggiungi le route in `routes/web.php` dentro il gruppo `auth`:
  ```php
  use App\Http\Controllers\Central\OnboardingController;

  Route::middleware('auth')->group(function () {
      // ... route dashboard esistente ...
      Route::get('/onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
      Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
  });
  ```

- [ ] **7.3** Crea la pagina React. Guarda la struttura delle pagine esistenti dello starter kit per capire il pattern (import, layout, Head), poi crea `resources/js/pages/central/onboarding/create.tsx`:
  - Form con campo "Nome organizzazione"
  - Anteprima dello slug generato (derivato dal nome con `slugify`)
  - Submit con `useForm` di Inertia
  - UI con componenti shadcn/ui (Card, Input, Button, Label)

- [ ] **7.4** Verifica:
  ```bash
  npm run build
  php artisan route:list --name=onboarding
  ```

- [ ] **7.5** Commit:
  ```bash
  git add app/Http/Controllers/Central/ resources/js/pages/central/ routes/web.php
  git commit -m "feat: add onboarding flow (create tenant after registration)"
  ```

---

## Task 8: Middleware Tenant + Route

**Stato:** `[x]` — Commit: `fa47dd9`

**Goal:** Configurare le route tenant con stancl/tenancy `InitializeTenancyByPath` + middleware accesso.

**Dipende da:** Task 5

**Files da creare/modificare:**

- [ ] **8.1** Crea `app/Http/Middleware/EnsureTenantAccess.php`:
  ```php
  <?php

  namespace App\Http\Middleware;

  use Closure;
  use Illuminate\Http\Request;

  class EnsureTenantAccess
  {
      public function handle(Request $request, Closure $next)
      {
          $tenant = tenant();

          if (! $tenant || $tenant->owner_id !== $request->user()->id) {
              abort(403);
          }

          return $next($request);
      }
  }
  ```

- [ ] **8.2** Registra middleware alias in `bootstrap/app.php`:
  ```php
  ->withMiddleware(function (Middleware $middleware) {
      // ... middleware esistenti ...
      $middleware->alias([
          'tenant.access' => \App\Http\Middleware\EnsureTenantAccess::class,
      ]);
  })
  ```

- [ ] **8.3** Sovrascrivi `routes/tenant.php` generato da stancl con le route path-based:
  ```php
  <?php

  use App\Http\Controllers\Tenant\DashboardController;
  use Illuminate\Support\Facades\Route;
  use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

  Route::middleware(['web', 'auth', InitializeTenancyByPath::class, 'tenant.access'])
      ->prefix('app/{tenant}')
      ->group(function () {
          Route::get('/dashboard', [DashboardController::class, 'index'])
              ->name('tenant.dashboard');
      });
  ```

- [ ] **8.4** Crea `app/Http/Controllers/Tenant/DashboardController.php`:
  ```php
  <?php

  namespace App\Http\Controllers\Tenant;

  use App\Http\Controllers\Controller;
  use Inertia\Inertia;

  class DashboardController extends Controller
  {
      public function index()
      {
          return Inertia::render('tenant/dashboard/index');
      }
  }
  ```

- [ ] **8.5** Configura `HandleInertiaRequests` per condividere tenant data:
  - Modifica il metodo `share()` in `app/Http/Middleware/HandleInertiaRequests.php`
  - Aggiungi la condivisione del tenant corrente:
    ```php
    'tenant' => fn () => tenant() ? [
        'id' => tenant('id'),
        'name' => tenant('name'),
        'slug' => tenant('slug'),
    ] : null,
    ```

- [ ] **8.6** Verifica che le route tenant siano caricate. Controlla in `bootstrap/app.php` come sono registrate le route e assicurati che `routes/tenant.php` venga caricato. Se necessario, aggiungi:
  ```php
  ->withRouting(
      web: __DIR__.'/../routes/web.php',
      then: function () {
          require base_path('routes/tenant.php');
      },
  )
  ```

- [ ] **8.7** Verifica:
  ```bash
  php artisan route:list --path=app
  npm run build
  ```

- [ ] **8.8** Commit:
  ```bash
  git add app/Http/Middleware/ app/Http/Controllers/Tenant/ routes/tenant.php bootstrap/app.php app/Http/Middleware/HandleInertiaRequests.php
  git commit -m "feat: add tenant routes with stancl/tenancy path identification and access middleware"
  ```

---

## Task 9: Pagina Dashboard Tenant + Layout

**Stato:** `[x]` — Commit: `a949d65`

**Goal:** Creare la pagina dashboard e i layout React.

**Dipende da:** Task 8

**Files:**

- [ ] **9.1** Verifica i layout esistenti dello starter kit:
  ```bash
  ls resources/js/layouts/
  ```

- [ ] **9.2** Crea `resources/js/pages/tenant/dashboard/index.tsx`:
  - Pagina placeholder che mostra il nome del tenant
  - Messaggio "Dashboard in costruzione"
  - Riceve `tenant` dalle shared props di Inertia

- [ ] **9.3** Crea `resources/js/layouts/central-layout.tsx`:
  - Per pagine post-auth non-tenant (onboarding, billing futuro)
  - Header semplice con logo e user menu

- [ ] **9.4** Crea `resources/js/layouts/tenant-layout.tsx`:
  - Sidebar con navigazione: Dashboard, Allievi, Pagamenti, Documenti
  - Header con nome tenant e avatar/dropdown utente
  - Mobile-first: sidebar collassabile
  - Riceve `tenant` dalle shared props di Inertia

- [ ] **9.5** Applica il TenantLayout alla pagina Dashboard con persistent layout pattern

- [ ] **9.6** Applica il CentralLayout alla pagina Onboarding

- [ ] **9.7** Verifica:
  ```bash
  npm run build
  ```

- [ ] **9.8** Commit:
  ```bash
  git add resources/js/
  git commit -m "feat: add tenant dashboard, CentralLayout, and TenantLayout with sidebar"
  ```

---

## Task 10: Verifica End-to-End

**Stato:** `[x]` — Commit: `84e1825`

**Goal:** Testare il flusso completo.

**Dipende da:** Tutti i task precedenti

**Steps:**

- [ ] **10.1** Avvia i server:
  ```bash
  php artisan serve &
  npm run dev &
  ```

- [ ] **10.2** Testa il flusso:
  1. `http://127.0.0.1:8000/` → welcome page
  2. `/register` → registrazione con email/password
  3. Redirect a `/dashboard` → redirect a `/onboarding`
  4. Inserisci "Palestra Test" → redirect a `/app/palestra-test/dashboard`
  5. Dashboard mostra nome tenant
  6. Logout → `/login` → login con stesse credenziali
  7. Redirect a `/dashboard` → redirect a `/app/palestra-test/dashboard`

- [ ] **10.3** Verifica nel database:
  ```bash
  php artisan tinker --execute="
    \$u = App\Models\User::first();
    echo 'User: '.\$u->name.PHP_EOL;
    echo 'Tenant: '.\$u->currentTenant->name.PHP_EOL;
    echo 'Slug: '.\$u->currentTenant->slug.PHP_EOL;
  "
  ```

- [ ] **10.4** Fix eventuali problemi trovati durante il test.

- [ ] **10.5** Commit finale:
  ```bash
  git add -A
  git commit -m "feat: fondamenta v2 complete — stancl/tenancy, auth, onboarding, tenant middleware, layouts"
  ```

---

## Riepilogo Task e Dipendenze

```
Task 0:  Rollback e Preparazione        ─┐
Task 1:  Installare stancl/tenancy      ─┤─ (dipende da 0)
Task 2:  Migrazioni Database (UUID)     ─┤─ (dipende da 1)
Task 3:  Enums PHP                      ─┤─ (dipende da 0)
Task 4:  Modello Tenant (stancl)        ─┤─ (dipende da 1, 2)
Task 5:  Modelli Eloquent               ─┤─ (dipende da 3, 4)
Task 6:  Adattare Auth Redirect         ─┤─ (dipende da 5)
Task 7:  Onboarding Controller+Page     ─┤─ (dipende da 6)
Task 8:  Middleware + Route Tenant      ─┤─ (dipende da 5)
Task 9:  Dashboard + Layout React       ─┤─ (dipende da 7, 8)
Task 10: Verifica End-to-End            ─┘─ (dipende da tutti)
```

**Parallelizzabili:**
- Task 2 e Task 3 (dopo Task 1/0)
- Task 7 e Task 8 (dopo Task 5/6)
