# Prompt di Kickoff — Sprint 1: Fondamenta

Copia e incolla questo prompt nella prima sessione di Claude Code, dopo aver creato la cartella del progetto e posizionato i file `CLAUDE.md` e `docs/architecture.md` nella root.

---

## Prompt

```
Leggi CLAUDE.md e docs/architecture.md prima di fare qualsiasi cosa.

Devi fare il setup iniziale del progetto TrainerHub — una piattaforma SaaS multi-tenant per trainer e istruttori sportivi. Questo è lo Sprint 1: Fondamenta.

Segui l'architettura documentata in docs/architecture.md e le convenzioni in CLAUDE.md.

### Step 1 — Scaffolding con Laravel React Starter Kit

Crea il progetto usando lo starter kit React di Laravel 12:
- `composer create-project laravel/laravel trainer-hub`
- Poi installa lo starter kit React: `php artisan install:starter-kit react`
- Lo starter kit include già: Inertia.js 2.x, React 19, TypeScript, Tailwind CSS 4, Vite, e auth completa (login, register, password reset, email verification)
- Dopo lo starter kit, inizializza shadcn/ui: `npx shadcn@latest init -d`
- Aggiungi i componenti shadcn necessari: button, input, label, card, separator, avatar, dropdown-menu, sheet, navigation-menu, badge

Il database è MySQL 8 su localhost, user root, nessuna password. Crea il database `trainerhub` e configura il `.env` di conseguenza. NON usare SQLite.

### Step 2 — Database e Modelli

Crea le migrazioni per tutte le tabelle documentate in architecture.md sezione 4:
- tenants (tabella centrale)
- users (estendi la migrazione default con current_tenant_id, cambia id in ULID)
- students (tenant-scoped)
- enrollment_fees (tenant-scoped)
- monthly_fees (tenant-scoped)
- documents (tenant-scoped)

Tutte le tabelle usano ULID come primary key.

Crea i modelli corrispondenti con:
- HasUlids trait su tutti
- BelongsToTenant trait (da creare in app/Models/Concerns/) sui modelli tenant-scoped
- Relazioni come documentate
- Cast per date e enums
- Crea gli Enums in app/Enums/: StudentStatus, PaymentMethod, DocumentType, DocumentStatus

### Step 3 — Adattare l'Autenticazione

Lo starter kit fornisce già auth funzionante. Adattala per il nostro flusso:
- Il modello User deve usare HasUlids e implementare MustVerifyEmail
- Dopo la registrazione, redirect a /onboarding (non alla dashboard default)
- Dopo il login, redirect intelligente: se l'utente ha un tenant va a /app/{slug}/dashboard, se no va a /onboarding
- Le pagine auth già esistono grazie allo starter kit — riorganizzale in Pages/Central/Auth/ se necessario, oppure lasciale dove lo starter kit le ha messe
- Assicurati che i componenti usino shadcn/ui

### Step 4 — Tenant e Onboarding

Dopo la registrazione, il trainer deve creare il suo primo tenant:
- Crea OnboardingController in app/Http/Controllers/Central/
- Pagina di onboarding: nome organizzazione → genera slug automatico
- Crea il record tenant con owner_id = user corrente
- Imposta current_tenant_id sull'utente
- Redirect a /app/{slug}/dashboard

### Step 5 — Middleware Tenant

Implementa il middleware stack documentato in architecture.md sezione 5.4:
- IdentifyTenant: risolve tenant da slug nel path, binda come app('current_tenant'), condivide con Inertia
- EnsureTenantAccess: verifica che l'utente sia owner del tenant
- (EnsureValidSubscription lo aggiungiamo nello Sprint 2)
- Registra i middleware alias in bootstrap/app.php e applicali alle route tenant
- Configura HandleInertiaRequests per condividere auth, tenant e flash messages

### Step 6 — Layout e Routing

Crea/adatta i layout React:
- AuthLayout: centrato, minimal, per login/register (lo starter kit potrebbe già averlo)
- CentralLayout: per billing e selezione tenant
- TenantLayout: sidebar con navigazione (Dashboard, Allievi, Pagamenti, Documenti), header con nome tenant e user menu

Crea le route tenant come documentate in architecture.md sezione 5.2:
- File routes/tenant.php separato con prefix 'app/{tenant:slug}'
- Middleware: auth, identify.tenant, tenant.access
- La dashboard tenant per ora mostra una pagina placeholder con il nome del tenant

### Step 7 — Verifica

Alla fine, verifica che funzioni il flusso completo:
1. Visita / → pagina welcome
2. /register → registrazione
3. Redirect a /onboarding → crea tenant "Palestra Test"
4. Redirect a /app/palestra-test/dashboard → vede la dashboard
5. Logout → login → redirect corretto alla dashboard del tenant

Committa con messaggi descrittivi ad ogni step completato.
```
