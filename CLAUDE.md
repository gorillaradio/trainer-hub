# TrainerHub — Convenzioni Progetto

## Stack

- Backend: Laravel 12 con PHP 8.3
- Frontend: React 19 + Inertia.js 2.x + TypeScript (via Laravel React Starter Kit)
- UI: shadcn/ui + Tailwind CSS 4
- Multi-tenancy: single database mode, colonna `tenant_id`, trait BelongsToTenant custom
- Billing: Laravel Cashier (Stripe) — Sprint 2
- Database: MySQL 8 (localhost, root, no password, db: trainerhub)
- IDs: ULID ovunque (trait `HasUlids`)
- Importi monetari: integer in centesimi (5000 = €50.00)

## Architettura Multi-Tenant

- Single database con colonna discriminante `tenant_id`
- Tutti i modelli tenant-scoped usano il trait `BelongsToTenant` (in `app/Models/Concerns/`)
- Il trait applica un global scope automatico e assegna `tenant_id` al creating
- Il tenant corrente è risolto via middleware e bindato come `app('current_tenant')`
- Le route tenant sono path-based: `/app/{tenant:slug}/...`
- MAI accedere a dati tenant senza passare dal global scope

## Struttura Directory

- `app/Http/Controllers/Central/` — auth, billing, onboarding
- `app/Http/Controllers/Tenant/` — tutto ciò che vive dentro un tenant
- `app/Models/Concerns/` — trait condivisi (BelongsToTenant, ecc.)
- `app/Services/` — business logic, mai nei controller
- `app/Enums/` — PHP 8.1 backed enums per status e tipi
- `resources/js/pages/` — pagine Inertia (struttura dettata dallo starter kit)
- `resources/js/components/` — componenti riutilizzabili
- `resources/js/layouts/` — layout condivisi

## Convenzioni Laravel

- Controller thin: validazione in FormRequest, logica in Service
- Un controller per risorsa, metodi RESTful standard
- Policy su ogni modello tenant-scoped
- Route model binding con scope tenant
- Enums per tutti i campi a valori fissi (status, type, payment_method)
- Migrazioni: una per tabella, nomi descrittivi

## Convenzioni Frontend

- TypeScript per tutti i file React (.tsx)
- Componenti shadcn/ui come base, personalizzati solo se necessario
- Inertia `useForm` per tutti i form
- Props tipizzate con interface TypeScript
- Hook `useTenantRoute()` per generare URL tenant-aware
- Layout assegnato con persistent layout pattern di Inertia
- Mobile-first: il trainer usa spesso il telefono

## Convenzioni Naming

- Tabelle: snake_case plurale (students, monthly_fees)
- Modelli: PascalCase singolare (Student, MonthlyFee)
- Colonne: snake_case (first_name, paid_at)
- Route names: dot notation (tenant.students.index)
- Componenti React: PascalCase (StudentList.tsx)
- Enums: PascalCase con valori snake_case

## Sicurezza

- Ogni modello tenant-scoped DEVE usare BelongsToTenant
- Ogni controller tenant DEVE avere Policy authorization
- File upload: path sempre prefissato con `tenants/{tenant_id}/`
- Mai costruire file path da input utente
- Middleware stack tenant: identify.tenant → tenant.access → subscribed

## Testing

- Feature test per ogni endpoint tenant con verifica isolamento
- Ogni test tenant deve verificare che tenant A non veda dati di tenant B
- Usa `RefreshDatabase` trait
- Factory per ogni modello

## Skills Claude Code

- Usa la skill `shadcn` per aggiungere, cercare e gestire componenti shadcn/ui

## Lingua

- Codice, commenti, nomi variabili: inglese
- UI labels e messaggi utente: italiano
- Documentazione: italiano
