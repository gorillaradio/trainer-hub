# TrainerHub

Piattaforma SaaS multi-tenant per trainer e istruttori sportivi. Gestione allievi, pagamenti, documenti e scadenze in un unico posto.

## Stack

- **Backend:** Laravel 12 / PHP 8.3
- **Frontend:** React 19 + Inertia.js 2.x + TypeScript
- **UI:** shadcn/ui + Tailwind CSS 4
- **Database:** MySQL 8
- **Multi-tenancy:** Single database con colonna `tenant_id` e trait `BelongsToTenant`

## Setup locale

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev
php artisan serve
```

## Licenza

Proprietario. Tutti i diritti riservati.
