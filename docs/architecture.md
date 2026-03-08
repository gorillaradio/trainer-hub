# TrainerHub — Architettura SaaS Multi-Tenant

## 1. Visione Architetturale

Una piattaforma SaaS dove ogni professionista (trainer, istruttore di atletica, personal trainer) ottiene il proprio registro digitale. L'architettura separa nettamente il **dominio centrale** (piattaforma, billing, onboarding) dal **dominio tenant** (gestione allievi, pagamenti allievi, documenti), così da poter evolvere ciascun pezzo indipendentemente.

---

## 2. Stack Tecnico

| Layer | Tecnologia |
|-------|-----------|
| Backend | Laravel 12 |
| Frontend | React 19 + Inertia.js 2.x |
| UI Kit | shadcn/ui + Tailwind CSS |
| Multi-tenancy | `stancl/tenancy` (Tenancy for Laravel) |
| Abbonamenti piattaforma | Laravel Cashier (Stripe) |
| Pagamenti futuri allievi | Stripe Connect (futuro) |
| Database | MySQL 8 / PostgreSQL 16 |
| File Storage | S3-compatible (DigitalOcean Spaces / AWS S3) |
| Queue | Redis + Laravel Horizon |
| Cache | Redis |

---

## 3. Strategia Multi-Tenancy

### 3.1 Approccio: Single Database con Colonna Discriminante + stancl/tenancy

Usiamo **stancl/tenancy** in modalità **single database** con colonna `tenant_id` su tutte le tabelle tenant-scoped. Questo è il giusto compromesso per questa fase:

- **Perché non multi-DB subito**: complessità operativa alta per il valore attuale, migrazioni da gestire N volte, backup complessi.
- **Perché non puro "fatto a mano"**: stancl/tenancy ti dà tenant identification, automatic scoping, route resolution, e soprattutto il path verso subdomini/custom domain senza riscrivere.
- **Path di migrazione**: se un tenant diventa molto grande, stancl supporta anche la migrazione a DB dedicato per singolo tenant.

### 3.2 Identificazione Tenant

**Fase 1 (ora)**: Path-based
```
tuaapp.com/app/{tenant}/dashboard
tuaapp.com/app/{tenant}/students
```

**Fase 2 (futura)**: Subdomain-based
```
nomepalestra.tuaapp.com/dashboard
```

**Fase 3 (futura)**: Custom domain
```
gestione.nomepalestraxy.it/dashboard
```

Con stancl/tenancy, il passaggio tra queste fasi richiede solo cambiare il `TenantIdentificationMiddleware` — il resto del codice non cambia.

### 3.3 Configurazione stancl/tenancy

```php
// config/tenancy.php (concettuale)
return [
    'tenant_model' => App\Models\Tenant::class,
    
    // Single DB mode
    'database' => [
        'manager' => 'single', // non 'multi'
    ],
    
    // Identification strategies (attivabili progressivamente)
    'identification' => [
        'path' => true,      // Fase 1
        'subdomain' => false, // Fase 2
        'domain' => false,    // Fase 3
    ],
];
```

---

## 4. Schema Database

### 4.1 Tabelle Centrali (non tenant-scoped)

Queste tabelle vivono nel database principale e NON hanno `tenant_id`.

```
tenants
├── id (string)             -- stancl/tenancy usa string ID (UUID generato)
├── name                    -- "Palestra Rossi ASD"
├── slug                    -- "palestra-rossi" (per URL path-based)
├── domain                  -- null (futuro: custom domain)
├── owner_id (FK → users)
├── settings (JSON)         -- configurazioni specifiche del tenant
├── stripe_account_id       -- null (futuro: Stripe Connect)
├── status                  -- active, suspended, trial
├── trial_ends_at
├── timestamps

users
├── id (UUID)
├── name
├── email (unique)
├── password
├── email_verified_at
├── current_tenant_id (FK → tenants, nullable)
├── timestamps
// Cashier aggiunge: stripe_id, pm_type, pm_last_four, trial_ends_at

subscriptions (gestita da Cashier)
├── id
├── user_id (FK → users)  -- il trainer paga, non il tenant
├── type
├── stripe_id
├── stripe_status
├── stripe_price
├── quantity
├── trial_ends_at
├── ends_at
├── timestamps

subscription_items (gestita da Cashier)
├── ...standard Cashier columns
```

### 4.2 Tabelle Tenant-Scoped

Tutte queste tabelle hanno `tenant_id` e sono automaticamente filtrate da stancl/tenancy.

```
students
├── id (UUID)
├── tenant_id (FK → tenants)
├── first_name
├── last_name
├── email (nullable)           -- futuro: per account allievo
├── phone (nullable)
├── date_of_birth (nullable)
├── fiscal_code (nullable)     -- codice fiscale, utile in Italia
├── address (nullable)
├── emergency_contact_name
├── emergency_contact_phone
├── notes (text, nullable)
├── status                     -- active, inactive, suspended
├── enrolled_at
├── user_id (nullable, FK → users) -- futuro: link ad account allievo
├── timestamps
├── INDEX(tenant_id, status)
├── INDEX(tenant_id, last_name, first_name)

enrollment_fees
├── id (UUID)
├── tenant_id
├── student_id (FK → students)
├── amount (integer, centesimi)    -- 5000 = €50.00
├── paid_at (nullable)
├── payment_method               -- cash, transfer, card, online (futuro)
├── academic_year                -- "2025/2026"
├── notes (nullable)
├── timestamps
├── UNIQUE(tenant_id, student_id, academic_year)

monthly_fees
├── id (UUID)
├── tenant_id
├── student_id (FK → students)
├── amount (integer, centesimi)
├── due_date (date)              -- primo del mese
├── paid_at (nullable)
├── payment_method
├── period                       -- "2025-09" (anno-mese)
├── notes (nullable)
├── timestamps
├── UNIQUE(tenant_id, student_id, period)
├── INDEX(tenant_id, due_date, paid_at)

documents
├── id (UUID)
├── tenant_id
├── student_id (FK → students)
├── type                         -- medical_certificate, identity_doc, privacy_consent, other
├── title
├── file_path                    -- path su S3, prefissato con tenant_id
├── delivered_at (nullable)      -- data consegna
├── expires_at (nullable)        -- scadenza (es. certificato medico)
├── status                       -- pending, delivered, expired, expiring_soon
├── notes (nullable)
├── timestamps
├── INDEX(tenant_id, student_id, type)
├── INDEX(tenant_id, expires_at)
```

### 4.3 Diagramma Relazioni

```
users ──1:N──> tenants (un user può possedere più palestre)
users ──1:1──> subscriptions (Cashier, billing sul user)

tenants ──1:N──> students
students ──1:N──> enrollment_fees
students ──1:N──> monthly_fees
students ──1:N──> documents

-- Futuro:
students ──0:1──> users (account allievo)
tenants ──> stripe_account_id (Stripe Connect)
```

---

## 5. Struttura Applicativa Laravel

### 5.1 Directory Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Central/              -- Controller area pubblica/auth
│   │   │   ├── AuthController.php
│   │   │   ├── RegisterController.php
│   │   │   ├── BillingController.php
│   │   │   └── OnboardingController.php
│   │   └── Tenant/               -- Controller area tenant
│   │       ├── DashboardController.php
│   │       ├── StudentController.php
│   │       ├── EnrollmentFeeController.php
│   │       ├── MonthlyFeeController.php
│   │       └── DocumentController.php
│   ├── Middleware/
│   │   ├── IdentifyTenant.php
│   │   ├── EnsureValidSubscription.php
│   │   └── EnsureTenantAccess.php
│   └── Requests/
│       ├── StoreStudentRequest.php
│       ├── UpdateStudentRequest.php
│       └── ...
├── Models/
│   ├── Tenant.php                -- Central model
│   ├── User.php                  -- Central model
│   ├── Student.php               -- Tenant-scoped
│   ├── EnrollmentFee.php         -- Tenant-scoped
│   ├── MonthlyFee.php            -- Tenant-scoped
│   └── Document.php              -- Tenant-scoped
├── Services/
│   ├── TenantService.php
│   ├── StudentService.php
│   ├── PaymentTrackingService.php
│   └── DocumentService.php
├── Policies/
│   ├── StudentPolicy.php
│   └── ...
└── Enums/
    ├── StudentStatus.php
    ├── PaymentMethod.php
    ├── DocumentType.php
    └── DocumentStatus.php
```

### 5.2 Routing

```php
// routes/web.php — Area Centrale
Route::middleware('guest')->group(function () {
    Route::get('/', fn () => Inertia::render('Welcome'));
    Route::get('/register', [RegisterController::class, 'create']);
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/login', [AuthController::class, 'create']);
    Route::post('/login', [AuthController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'create']);
    Route::post('/onboarding', [OnboardingController::class, 'store']);
    Route::get('/billing', [BillingController::class, 'index']);
    Route::get('/billing/portal', [BillingController::class, 'portal']);
    Route::get('/select-tenant', [TenantSelectController::class, 'index']);
});

// routes/tenant.php — Area Tenant (path-based)
Route::middleware(['auth', 'identify.tenant', 'tenant.access', 'subscribed'])
    ->prefix('app/{tenant:slug}')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('tenant.dashboard');
        
        Route::resource('students', StudentController::class);
        
        Route::prefix('students/{student}')->group(function () {
            Route::resource('enrollment-fees', EnrollmentFeeController::class)
                ->only(['store', 'update']);
            Route::resource('monthly-fees', MonthlyFeeController::class);
            Route::resource('documents', DocumentController::class);
        });
    });
```

### 5.3 Modelli con Tenant Scoping

```php
// app/Models/Concerns/BelongsToTenant.php
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::creating(function ($model) {
            if (! $model->tenant_id && app()->bound('current_tenant')) {
                $model->tenant_id = app('current_tenant')->id;
            }
        });

        // Global scope: filtra automaticamente per tenant
        static::addGlobalScope('tenant', function ($query) {
            if (app()->bound('current_tenant')) {
                $query->where('tenant_id', app('current_tenant')->id);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

// app/Models/Student.php
class Student extends Model
{
    use BelongsToTenant, HasUuids;

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

    // Scorciatoia: quota iscrizione anno corrente
    public function currentEnrollmentFee(): HasOne
    {
        return $this->hasOne(EnrollmentFee::class)
            ->where('academic_year', AcademicYear::current());
    }

    // Rette non pagate
    public function unpaidFees(): HasMany
    {
        return $this->monthlyFees()->whereNull('paid_at');
    }

    // Documenti in scadenza
    public function expiringDocuments(): HasMany
    {
        return $this->documents()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30));
    }
}
```

### 5.4 Middleware

```php
// app/Http/Middleware/IdentifyTenant.php
class IdentifyTenant
{
    public function handle(Request $request, Closure $next)
    {
        $slug = $request->route('tenant');
        
        $tenant = Tenant::where('slug', $slug)->first();
        
        if (! $tenant) {
            abort(404, 'Organizzazione non trovata');
        }
        
        app()->instance('current_tenant', $tenant);
        
        // Condividi con Inertia per il frontend
        Inertia::share('tenant', fn () => [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
        ]);
        
        return $next($request);
    }
}

// app/Http/Middleware/EnsureValidSubscription.php
class EnsureValidSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if (! $user->subscribed('default') && ! $user->onTrial()) {
            return redirect()->route('billing.index')
                ->with('warning', 'Abbonamento richiesto per accedere.');
        }
        
        return $next($request);
    }
}

// app/Http/Middleware/EnsureTenantAccess.php
class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = app('current_tenant');
        $user = $request->user();
        
        // Per ora: solo l'owner ha accesso
        // Futuro: team members, ruoli
        if ($tenant->owner_id !== $user->id) {
            abort(403);
        }
        
        return $next($request);
    }
}
```

---

## 6. Frontend Architecture (React + Inertia)

### 6.1 Layout Structure

```
resources/js/
├── app.jsx                          -- Inertia entry point
├── Layouts/
│   ├── CentralLayout.jsx           -- Layout area pubblica (auth, billing)
│   ├── TenantLayout.jsx            -- Layout area tenant (sidebar, nav)
│   └── AuthLayout.jsx              -- Layout pagine auth
├── Pages/
│   ├── Central/
│   │   ├── Welcome.jsx
│   │   ├── Auth/
│   │   │   ├── Login.jsx
│   │   │   └── Register.jsx
│   │   ├── Billing/
│   │   │   └── Index.jsx
│   │   └── Onboarding/
│   │       └── Create.jsx
│   └── Tenant/
│       ├── Dashboard/
│       │   └── Index.jsx
│       ├── Students/
│       │   ├── Index.jsx            -- Lista allievi
│       │   ├── Create.jsx           -- Nuovo allievo
│       │   ├── Show.jsx             -- Dettaglio con tab
│       │   └── Edit.jsx
│       ├── Fees/
│       │   └── Overview.jsx         -- Vista pagamenti
│       └── Documents/
│           └── Index.jsx            -- Gestione documenti
├── Components/
│   ├── ui/                          -- shadcn/ui components
│   ├── DataTable.jsx               -- Tabella riutilizzabile
│   ├── StatusBadge.jsx
│   ├── PaymentStatusCard.jsx
│   ├── DocumentExpiryAlert.jsx
│   └── TenantSwitcher.jsx
├── Hooks/
│   ├── useTenantRoute.js           -- Helper per generare route tenant-aware
│   └── usePermissions.js           -- Futuro: feature gates
└── Lib/
    └── utils.js                     -- Utility condivise
```

### 6.2 Helper Routing Tenant-Aware

```jsx
// resources/js/Hooks/useTenantRoute.js
import { usePage } from '@inertiajs/react';

export function useTenantRoute() {
    const { tenant } = usePage().props;
    
    return (routeName, params = {}) => {
        return route(routeName, { tenant: tenant.slug, ...params });
    };
}

// Uso nei componenti:
const tenantRoute = useTenantRoute();
// tenantRoute('students.index') → /app/palestra-rossi/students
```

### 6.3 Shared Props (via HandleInertiaRequests)

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return [
        'auth' => [
            'user' => $request->user() ? [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'subscribed' => $request->user()->subscribed('default'),
            ] : null,
        ],
        'tenant' => fn () => app()->bound('current_tenant') ? [
            'id' => app('current_tenant')->id,
            'name' => app('current_tenant')->name,
            'slug' => app('current_tenant')->slug,
            'settings' => app('current_tenant')->settings,
        ] : null,
        'flash' => [
            'success' => $request->session()->get('success'),
            'warning' => $request->session()->get('warning'),
            'error' => $request->session()->get('error'),
        ],
    ];
}
```

---

## 7. Billing con Laravel Cashier

### 7.1 Flusso Abbonamento Trainer

```
1. Trainer si registra (users table)
2. Onboarding: crea il suo tenant (tenants table)
3. Sceglie un piano → Cashier crea subscription su Stripe
4. Redirect a Stripe Checkout / Payment Element
5. Webhook conferma → subscription attiva
6. Accesso all'area tenant sbloccato
```

### 7.2 Piani Suggeriti (Stripe Products)

```
Piano Base:     €19/mese  — fino a 30 allievi
Piano Pro:      €39/mese  — fino a 100 allievi, documenti illimitati
Piano Premium:  €79/mese  — allievi illimitati, white-label (futuro)
```

### 7.3 Feature Gating (predisposto)

```php
// app/Services/FeatureGateService.php
class FeatureGateService
{
    private array $planLimits = [
        'base' => [
            'max_students' => 30,
            'documents' => true,
            'online_payments' => false,
            'custom_domain' => false,
        ],
        'pro' => [
            'max_students' => 100,
            'documents' => true,
            'online_payments' => true,
            'custom_domain' => false,
        ],
        'premium' => [
            'max_students' => PHP_INT_MAX,
            'documents' => true,
            'online_payments' => true,
            'custom_domain' => true,
        ],
    ];

    public function canAddStudent(Tenant $tenant): bool
    {
        $plan = $this->getCurrentPlan($tenant);
        $currentCount = $tenant->students()->active()->count();
        return $currentCount < $this->planLimits[$plan]['max_students'];
    }

    public function hasFeature(Tenant $tenant, string $feature): bool
    {
        $plan = $this->getCurrentPlan($tenant);
        return $this->planLimits[$plan][$feature] ?? false;
    }
}
```

---

## 8. File Storage Strategy

### 8.1 Path Convention per Tenant Isolation

```
s3-bucket/
├── tenants/
│   ├── {tenant_uuid}/
│   │   ├── documents/
│   │   │   ├── {student_uuid}/
│   │   │   │   ├── medical_cert_2025.pdf
│   │   │   │   └── privacy_consent.pdf
│   │   │   └── ...
│   │   └── exports/            -- futuro: export dati
│   │       └── ...
│   └── ...
```

### 8.2 File Upload Sicuro

```php
// Ogni upload è prefissato con il tenant_id corrente
// MAI fidarsi di path forniti dal client

public function storeDocument(StoreDocumentRequest $request, Student $student)
{
    $tenant = app('current_tenant');
    
    $path = $request->file('document')->store(
        "tenants/{$tenant->id}/documents/{$student->id}",
        's3'
    );
    
    return $student->documents()->create([
        'type' => $request->type,
        'title' => $request->title,
        'file_path' => $path,
        'delivered_at' => $request->delivered_at,
        'expires_at' => $request->expires_at,
    ]);
}
```

---

## 9. Predisposizioni per il Futuro

### 9.1 Account Allievi (Multi-Auth Tenant-Aware)

La tabella `students` ha già `user_id` nullable e `email`. Quando attivi gli account allievi:

1. L'allievo riceve un invito via email
2. Si registra nella tabella `users` con un flag `is_student = true` (o ruolo)
3. Il suo `students.user_id` viene collegato
4. Un guard separato o un middleware determina il contesto (trainer vs allievo)

**Nessun campo da aggiungere**, solo logica applicativa.

### 9.2 Stripe Connect

La colonna `tenants.stripe_account_id` è già prevista. Il flusso sarà:

1. Trainer completa l'onboarding Stripe Connect (Express account)
2. `stripe_account_id` viene salvato
3. I pagamenti degli allievi usano `Stripe::setApiKey()` con l'account connesso
4. La piattaforma prende una `application_fee` o gestisce tutto con l'abbonamento

### 9.3 Custom Domain

Con stancl/tenancy, aggiungere custom domain richiede:

1. Tabella `domains` già supportata dal package
2. Certificati SSL via Let's Encrypt / Cloudflare
3. DNS CNAME dall'utente verso il tuo server
4. Middleware di identificazione aggiornato

### 9.4 Notifiche e Automazioni (predisposte)

```php
// Già predisposto: scheduled command per scadenze
// app/Console/Commands/CheckDocumentExpiry.php
// app/Console/Commands/GenerateMonthlyFees.php
// app/Console/Commands/SendPaymentReminders.php

// Questi comandi iterano sui tenant attivi e inviano notifiche
```

---

## 10. Sicurezza Multi-Tenant — Checklist

- [ ] **Global Scope su tutti i modelli tenant**: il trait `BelongsToTenant` impedisce leak tra tenant
- [ ] **Route model binding scoped**: `Student::where('tenant_id', ...)->findOrFail($id)`
- [ ] **Policy su ogni risorsa**: anche con global scope, la policy è il secondo livello di difesa
- [ ] **File path prefissati**: mai costruire path da input utente
- [ ] **Subscription check**: middleware `EnsureValidSubscription` su tutte le route tenant
- [ ] **Rate limiting per tenant**: evita abuse
- [ ] **Audit log** (futuro): chi ha fatto cosa, quando

---

## 11. Comandi di Setup Iniziale

```bash
# Crea il progetto
laravel new trainerhub
cd trainerhub

# Dipendenze backend
composer require laravel/cashier
composer require stancl/tenancy
composer require inertiajs/inertia-laravel

# Dipendenze frontend
npm install @inertiajs/react react react-dom
npm install -D @vitejs/plugin-react
npm install tailwindcss @tailwindcss/forms
npx shadcn@latest init

# Pubblica configurazioni
php artisan tenancy:install
php artisan cashier:install
php artisan vendor:publish --tag=tenancy-config

# Crea struttura
php artisan make:model Tenant -m
php artisan make:model Student -m
php artisan make:model EnrollmentFee -m
php artisan make:model MonthlyFee -m
php artisan make:model Document -m

# Migrations, seeders, etc.
php artisan migrate
```

---

## 12. Ordine di Implementazione Suggerito

### Sprint 1 — Fondamenta (1 settimana)
1. Setup progetto Laravel + Inertia + React + Tailwind + shadcn
2. Modello User + autenticazione (Laravel Breeze/Fortify con Inertia)
3. Modello Tenant + migrazione
4. Onboarding flow: registrazione → creazione tenant
5. Middleware tenant identification (path-based)
6. Layout base CentralLayout + TenantLayout

### Sprint 2 — Billing (3-4 giorni)
1. Laravel Cashier setup
2. Pagina piani + checkout Stripe
3. Webhook handler
4. Middleware `EnsureValidSubscription`
5. Billing portal (gestione abbonamento)

### Sprint 3 — Gestione Allievi (1 settimana)
1. CRUD Student completo
2. Lista allievi con DataTable (filtri, ricerca)
3. Dettaglio allievo con tab (anagrafica, pagamenti, documenti)
4. Validazione e Policy

### Sprint 4 — Pagamenti Allievi (4-5 giorni)
1. Quota iscrizione (enrollment fee) per anno accademico
2. Rette mensili: generazione automatica + tracciamento manuale
3. Dashboard pagamenti: chi ha pagato, chi no, totali
4. Scheduled command per generare rette del mese

### Sprint 5 — Documenti (3-4 giorni)
1. Upload documenti per allievo
2. Tracking consegna + scadenza
3. Alert documenti in scadenza (dashboard + notifiche)
4. Scheduled command per check scadenze

### Sprint 6 — Polish e Deploy (1 settimana)
1. Dashboard riassuntiva (KPI, alert, azioni rapide)
2. Responsive design (il trainer usa spesso il telefono)
3. Testing (Feature tests per tenant isolation)
4. Deploy su DigitalOcean (Forge o manuale)
5. DNS, SSL, monitoring base
