# TrainerHub Fondamenta — Piano di Implementazione

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Setup completo di TrainerHub — scaffolding Laravel+React, database multi-tenant, auth adattata, onboarding tenant, middleware e layout.

**Architecture:** Single database multi-tenant con colonna `tenant_id` e trait `BelongsToTenant` custom. Path-based tenant routing (`/app/{slug}/...`). Laravel 12 + React 19 via starter kit, Inertia.js 2.x, shadcn/ui.

**Tech Stack:** PHP 8.3, Laravel 12, React 19, TypeScript, Inertia.js 2.x, Tailwind CSS 4, shadcn/ui, MySQL 8, Vite

**Riferimenti:**
- Convenzioni: `CLAUDE.md`
- Architettura: `docs/architecture.md`
- Prompt originale: `docs/prompt-sprint-1.md`

---

## Come usare questo piano

- Ogni task è indipendente e può essere ripreso in una sessione diversa
- I checkbox `[ ]` → `[x]` tracciano lo stato di completamento
- Ogni task ha un **commit** finale — se il commit è fatto, il task è completato
- All'inizio di ogni sessione, leggi questo file per sapere dove sei rimasto

---

## Task 1: Scaffolding Laravel

**Stato:** `[x]` COMPLETATO

**Goal:** Creare il progetto Laravel con lo starter kit React e inizializzare git.

**Note:** Usato `laravel new trainer-hub --react` (il flag `--react` seleziona lo starter kit direttamente). Lo starter kit include già shadcn/ui inizializzato con tutti i componenti necessari.

**Commit:** `3db3ebb` — chore: scaffold Laravel 12 with React starter kit

---

## Task 2: shadcn/ui Setup

**Stato:** `[x]` COMPLETATO (incluso nello starter kit)

**Goal:** Inizializzare shadcn/ui e aggiungere tutti i componenti necessari.

**Note:** Lo starter kit React di Laravel 12 include già shadcn/ui inizializzato con tutti i componenti richiesti: button, input, label, card, separator, avatar, dropdown-menu, sheet, navigation-menu, badge + molti altri (dialog, sidebar, tooltip, skeleton, etc.). Nessuna azione necessaria.

---

## Task 3: Database MySQL + Configurazione .env

**Stato:** `[x]` COMPLETATO

**Goal:** Creare il database MySQL e configurare la connessione.

**Note:** Database `trainerhub` creato su MySQL 8 (root, no password). `.env` e `.env.example` aggiornati. Migrazioni di base (users, cache, jobs, two_factor) eseguite su MySQL.

**Commit:** `40551ef` — chore: configure MySQL database connection

---

## Task 4: Migrazioni Database

**Stato:** `[x]` COMPLETATO

**Goal:** Creare tutte le migrazioni per le 6 tabelle con ULID come primary key.

**Dipende da:** Task 3

**Note:** Modificata direttamente la migrazione users originale (`id()` → `ulid('id')->primary()`, sessions FK → `foreignUlid`). Creata migrazione separata `000004` per `current_tenant_id` (necessaria per ordine FK: users → tenants → users.current_tenant_id). Tutte le tabelle tenant-scoped con `foreignUlid('tenant_id')->constrained()->cascadeOnDelete()`. Verificato con `migrate:fresh` — 10 migrazioni, 0 errori.

**Files creati/modificati:**
- `database/migrations/0001_01_01_000000_create_users_table.php` — modificata (ULID + foreignUlid sessions)
- `database/migrations/0001_01_01_000003_create_tenants_table.php`
- `database/migrations/0001_01_01_000004_add_current_tenant_id_to_users_table.php`
- `database/migrations/0001_01_01_000005_create_students_table.php`
- `database/migrations/0001_01_01_000006_create_enrollment_fees_table.php`
- `database/migrations/0001_01_01_000007_create_monthly_fees_table.php`
- `database/migrations/0001_01_01_000008_create_documents_table.php`

- [x] **4.1–4.7** Tutte le migrazioni create e verificate
- [ ] **4.8** Commit (da fare insieme agli altri task del checkpoint)

---

## Task 5: Enums PHP

**Stato:** `[x]` COMPLETATO

**Goal:** Creare i backed enums per status e tipi.

**Dipende da:** Task 4

**Files creati:**
- `app/Enums/StudentStatus.php` — active, inactive, suspended
- `app/Enums/PaymentMethod.php` — cash, transfer, card, online
- `app/Enums/DocumentType.php` — medical_certificate, identity_doc, privacy_consent, other
- `app/Enums/DocumentStatus.php` — pending, delivered, expired, expiring_soon

- [x] **5.1** Tutti i 4 enum creati come string backed enums
- [ ] **5.2** Commit (da fare insieme agli altri task del checkpoint)

---

## Task 6: Trait BelongsToTenant

**Stato:** `[x]` COMPLETATO

**Goal:** Creare il trait custom per il tenant scoping automatico.

**Dipende da:** Task 4

**Note:** Trait implementato come da architecture.md sezione 5.3. Verificato: `tenant_id` auto-assegnato al creating, global scope filtra correttamente.

**File creato:** `app/Models/Concerns/BelongsToTenant.php`

- [x] **6.1** Trait creato con bootBelongsToTenant() e tenant() relation
- [ ] **6.2** Commit (da fare insieme agli altri task del checkpoint)

---

## Task 7: Modelli Eloquent

**Stato:** `[x]` COMPLETATO

**Goal:** Creare/modificare tutti i modelli con relazioni, cast e trait.

**Dipende da:** Task 5, Task 6

**Note:** Tutti i modelli creati/modificati. Verificato con tinker: creazione completa di User → Tenant → Student → EnrollmentFee/MonthlyFee/Document. ULID IDs funzionano, BelongsToTenant auto-assegna tenant_id, tutti i cast enum funzionano, tutte le relazioni verificate. Omesso `currentEnrollmentFee()` da Student (richiede `AcademicYear::current()` non ancora implementato).

**Files:**
- Modificato: `app/Models/User.php` — HasUlids, MustVerifyEmail, tenants(), currentTenant()
- Creato: `app/Models/Tenant.php` — HasUlids, owner(), students()
- Creato: `app/Models/Student.php` — HasUlids, BelongsToTenant, tutte le relazioni
- Creato: `app/Models/EnrollmentFee.php` — HasUlids, BelongsToTenant, cast centesimi
- Creato: `app/Models/MonthlyFee.php` — HasUlids, BelongsToTenant, cast
- Creato: `app/Models/Document.php` — HasUlids, BelongsToTenant, cast

- [x] **7.1–7.7** Tutti i modelli creati e verificati con tinker
- [ ] **7.8** Commit (da fare ora)

---

## Task 8: Adattare Autenticazione (Redirect)

**Stato:** `[ ]`

**Goal:** Dopo registrazione → /onboarding. Dopo login → redirect intelligente (tenant dashboard o onboarding).

**Dipende da:** Task 7

**Files da modificare:**
- Individua i file di auth dello starter kit (probabilmente in `app/Http/Controllers/Auth/`)
- Modifica il redirect post-registrazione
- Modifica il redirect post-login

**Steps:**

- [ ] **8.1** Individua i file di auth dello starter kit:
  ```bash
  find app/Http/Controllers/Auth -type f -name "*.php"
  # e/o
  grep -r "redirect" app/Http/Controllers/Auth/
  ```

- [ ] **8.2** Modifica il redirect post-registrazione:
  - Dopo la registrazione, redirect a `/onboarding` invece della dashboard default

- [ ] **8.3** Modifica il redirect post-login:
  - Se l'utente ha `current_tenant_id` → redirect a `/app/{slug}/dashboard`
  - Se non ha tenant → redirect a `/onboarding`

- [ ] **8.4** Verifica: il build non deve rompersi:
  ```bash
  npm run build
  php artisan route:list
  ```

- [ ] **8.5** Commit:
  ```bash
  git add app/Http/Controllers/Auth/
  git commit -m "feat: adapt auth redirects for onboarding and tenant dashboard"
  ```

---

## Task 9: Onboarding Controller + Pagina

**Stato:** `[ ]`

**Goal:** Il trainer crea il suo primo tenant dopo la registrazione.

**Dipende da:** Task 8

**Files da creare:**
- `app/Http/Controllers/Central/OnboardingController.php`
- `resources/js/pages/Central/Onboarding/Create.tsx` (o dove lo starter kit mette le pagine)

**Steps:**

- [ ] **9.1** Crea `OnboardingController`:
  ```php
  // create(): renderizza la pagina Inertia di onboarding
  // store(): valida nome org, genera slug (Str::slug), crea Tenant,
  //          imposta current_tenant_id su user, redirect a /app/{slug}/dashboard
  ```

- [ ] **9.2** Aggiungi le route in `routes/web.php`:
  ```php
  Route::middleware('auth')->group(function () {
      Route::get('/onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
      Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
  });
  ```

- [ ] **9.3** Crea la pagina React `Create.tsx`:
  - Form con campo "Nome organizzazione"
  - Anteprima dello slug generato (opzionale, nice to have)
  - Submit con `useForm` di Inertia
  - UI con componenti shadcn/ui (Card, Input, Button, Label)

- [ ] **9.4** Verifica:
  ```bash
  npm run build
  php artisan route:list --name=onboarding
  ```

- [ ] **9.5** Commit:
  ```bash
  git add app/Http/Controllers/Central/ resources/js/ routes/
  git commit -m "feat: add onboarding flow (create tenant after registration)"
  ```

---

## Task 10: Middleware Tenant

**Stato:** `[ ]`

**Goal:** Implementare identification e access check del tenant.

**Dipende da:** Task 7

**Files da creare:**
- `app/Http/Middleware/IdentifyTenant.php`
- `app/Http/Middleware/EnsureTenantAccess.php`

**Files da modificare:**
- `bootstrap/app.php` — registra alias middleware

**Riferimento:** `docs/architecture.md` sezione 5.4

**Steps:**

- [ ] **10.1** Crea `IdentifyTenant.php`:
  ```php
  // Risolve tenant da $request->route('tenant') (slug)
  // 404 se non trovato
  // Binda come app()->instance('current_tenant', $tenant)
  // Condividi con Inertia
  ```
  Codice di riferimento in `docs/architecture.md` sezione 5.4.

- [ ] **10.2** Crea `EnsureTenantAccess.php`:
  ```php
  // Verifica che $tenant->owner_id === $user->id
  // 403 se no
  ```

- [ ] **10.3** Registra middleware alias in `bootstrap/app.php`:
  ```php
  ->withMiddleware(function (Middleware $middleware) {
      $middleware->alias([
          'identify.tenant' => \App\Http\Middleware\IdentifyTenant::class,
          'tenant.access' => \App\Http\Middleware\EnsureTenantAccess::class,
      ]);
  })
  ```

- [ ] **10.4** Configura `HandleInertiaRequests` per condividere auth, tenant e flash:
  ```php
  // Modifica il metodo share() come da architecture.md sezione 6.3
  // auth.user, tenant (se bound), flash messages
  ```

- [ ] **10.5** Verifica:
  ```bash
  php artisan route:list
  ```

- [ ] **10.6** Commit:
  ```bash
  git add app/Http/Middleware/ bootstrap/app.php
  git commit -m "feat: add tenant identification and access middleware"
  ```

---

## Task 11: Route Tenant + Dashboard Placeholder

**Stato:** `[ ]`

**Goal:** File route tenant separato, dashboard controller, pagina placeholder.

**Dipende da:** Task 10

**Files da creare:**
- `routes/tenant.php`
- `app/Http/Controllers/Tenant/DashboardController.php`
- `resources/js/pages/Tenant/Dashboard/Index.tsx`

**Steps:**

- [ ] **11.1** Crea `routes/tenant.php`:
  ```php
  Route::middleware(['auth', 'identify.tenant', 'tenant.access'])
      ->prefix('app/{tenant:slug}')
      ->group(function () {
          Route::get('/dashboard', [DashboardController::class, 'index'])
              ->name('tenant.dashboard');
      });
  ```
  Nota: NO middleware `subscribed` (lo aggiungiamo dopo).

- [ ] **11.2** Registra il file route in `bootstrap/app.php`:
  ```php
  ->withRouting(
      web: __DIR__.'/../routes/web.php',
      // aggiungi:
      then: function () {
          Route::middleware('web')->group(base_path('routes/tenant.php'));
      },
  )
  ```

- [ ] **11.3** Crea `DashboardController.php`:
  ```php
  // index(): Inertia::render('Tenant/Dashboard/Index')
  ```

- [ ] **11.4** Crea `Index.tsx` — pagina placeholder:
  - Mostra nome del tenant
  - Messaggio "Dashboard in costruzione"
  - Usa il TenantLayout

- [ ] **11.5** Verifica:
  ```bash
  php artisan route:list --path=app
  npm run build
  ```

- [ ] **11.6** Commit:
  ```bash
  git add routes/tenant.php app/Http/Controllers/Tenant/ resources/js/
  git commit -m "feat: add tenant routes and dashboard placeholder"
  ```

---

## Task 12: Layout React

**Stato:** `[ ]`

**Goal:** Creare/adattare i 3 layout: AuthLayout, CentralLayout, TenantLayout.

**Dipende da:** Task 2, Task 11

**Files:**
- Lo starter kit crea già layout in `resources/js/layouts/` — adattali
- Assicurati che esistano: AuthLayout, CentralLayout, TenantLayout
- `TenantLayout`: sidebar con nav (Dashboard, Allievi, Pagamenti, Documenti), header con nome tenant e user menu

**Steps:**

- [ ] **12.1** Verifica i layout esistenti dello starter kit:
  ```bash
  ls resources/js/layouts/
  ```

- [ ] **12.2** Adatta/crea `AuthLayout`:
  - Centrato, minimal, per login/register
  - Lo starter kit probabilmente lo ha già — verifica e adatta con shadcn/ui se necessario

- [ ] **12.3** Crea `CentralLayout`:
  - Per pagine post-auth non-tenant (onboarding, billing futuro, selezione tenant)
  - Header semplice con logo e user menu

- [ ] **12.4** Crea `TenantLayout`:
  - Sidebar con navigazione: Dashboard, Allievi, Pagamenti, Documenti
  - Header con nome tenant e avatar/dropdown utente
  - Mobile-first: sidebar collassabile (usa componente Sheet di shadcn)
  - Riceve `tenant` dalle shared props di Inertia

- [ ] **12.5** Applica il TenantLayout alla pagina Dashboard:
  - Usa il persistent layout pattern di Inertia

- [ ] **12.6** Applica il CentralLayout alla pagina Onboarding:
  - Usa il persistent layout pattern di Inertia

- [ ] **12.7** Verifica:
  ```bash
  npm run build
  ```

- [ ] **12.8** Commit:
  ```bash
  git add resources/js/layouts/ resources/js/pages/
  git commit -m "feat: add AuthLayout, CentralLayout, and TenantLayout with sidebar navigation"
  ```

---

## Task 13: Verifica End-to-End

**Stato:** `[ ]`

**Goal:** Testare il flusso completo manualmente.

**Dipende da:** Tutti i task precedenti

**Steps:**

- [ ] **13.1** Avvia il server:
  ```bash
  php artisan serve &
  npm run dev &
  ```

- [ ] **13.2** Testa il flusso:
  1. `http://127.0.0.1:8000/` → welcome page
  2. `/register` → registrazione con email/password
  3. Redirect a `/onboarding` → inserisci "Palestra Test"
  4. Redirect a `/app/palestra-test/dashboard` → vede la dashboard con nome tenant
  5. Logout
  6. `/login` → login con le stesse credenziali
  7. Redirect a `/app/palestra-test/dashboard` → corretto

- [ ] **13.3** Verifica nel database:
  ```bash
  php artisan tinker --execute="
    \$u = App\Models\User::first();
    echo 'User: '.\$u->name.PHP_EOL;
    echo 'Tenant: '.\$u->currentTenant->name.PHP_EOL;
    echo 'Slug: '.\$u->currentTenant->slug.PHP_EOL;
  "
  ```

- [ ] **13.4** Fix eventuali problemi trovati durante il test.

- [ ] **13.5** Commit finale:
  ```bash
  git add -A
  git commit -m "feat: fondamenta complete — auth, onboarding, tenant middleware, layouts"
  ```

---

## Riepilogo Task e Dipendenze

```
Task 1:  Scaffolding Laravel          ─┐
Task 2:  shadcn/ui Setup              ─┤─ (dipende da 1)
Task 3:  Database MySQL               ─┤─ (dipende da 1)
Task 4:  Migrazioni                   ─┤─ (dipende da 3)
Task 5:  Enums PHP                    ─┤─ (dipende da 4)
Task 6:  Trait BelongsToTenant        ─┤─ (dipende da 4)
Task 7:  Modelli Eloquent             ─┤─ (dipende da 5, 6)
Task 8:  Adattare Auth Redirect       ─┤─ (dipende da 7)
Task 9:  Onboarding Controller+Page   ─┤─ (dipende da 8)
Task 10: Middleware Tenant            ─┤─ (dipende da 7)
Task 11: Route Tenant + Dashboard     ─┤─ (dipende da 10)
Task 12: Layout React                 ─┤─ (dipende da 2, 11)
Task 13: Verifica End-to-End          ─┘─ (dipende da tutti)
```

**Parallelizzabili:**
- Task 2 e Task 3 (dopo Task 1)
- Task 5 e Task 6 (dopo Task 4)
- Task 9 e Task 10 (dopo Task 7/8 — parzialmente)
