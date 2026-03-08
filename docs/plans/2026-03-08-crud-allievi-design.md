# Design — CRUD Allievi (Anagrafica)

Data: 2026-03-08

## Scope

CRUD base per la gestione anagrafica degli allievi. Solo dati anagrafici, niente tab correlate (pagamenti, documenti) in questa fase.

## Backend

### Controller
`App\Http\Controllers\Tenant\StudentController` — 7 azioni REST standard (index, create, store, show, edit, update, destroy).

### Routes
In `routes/tenant.php`, dentro il gruppo tenant esistente:
```php
Route::resource('students', StudentController::class);
```
URL: `/app/{tenant}/students`, `/app/{tenant}/students/create`, `/app/{tenant}/students/{student}`, etc.

### Form Requests
- `StoreStudentRequest` — validazione campi anagrafici. Email unica per tenant (non globalmente).
- `UpdateStudentRequest` — stessa validazione, ignora l'allievo corrente per unicità email.

### Policy
`StudentPolicy` — per ora verifica solo che l'utente sia owner del tenant (già coperto dal middleware, ma la policy serve per estendibilità futura con ruoli).

### Soft Delete
- Aggiungere trait `SoftDeletes` al model `Student`
- Migrazione per colonna `deleted_at`
- Il `destroy` fa soft delete
- La lista mostra solo allievi non cancellati

### Paginazione e filtri (server-side)
- 15 risultati per pagina
- Ricerca testuale su first_name, last_name, email
- Filtro per stato (active, inactive, suspended)
- Ordinamento per colonna (default: last_name ASC)

## Frontend

### Pagine (`resources/js/pages/tenant/students/`)
- `index.tsx` — Tabella con ricerca, filtro stato, ordinamento per colonna, paginazione. Bottone "Nuovo allievo"
- `create.tsx` — Form con campi anagrafici, bottone salva
- `edit.tsx` — Stesso form pre-compilato, bottone aggiorna
- `show.tsx` — Vista read-only dei dati, bottoni modifica/elimina

### Componenti condivisi
- `student-form.tsx` — Form riusato tra create e edit

### UI
- Tutti componenti **shadcn/ui** (Table, Input, Select, Button, Badge, Card, AlertDialog, etc.)
- Layout: TenantLayout (persistente)

### Navigazione
- Link "Allievi" in sidebar e bottom nav già punta a students (da attivare con la route corretta)

### Dialog conferma
- Per soft delete: `AlertDialog` shadcn con "Sei sicuro di voler archiviare questo allievo?"

## Decisioni
- **Soft delete** invece di hard delete — il trainer non vuole perdere dati per errore
- **Pagine dedicate** per create/edit — più spazio per i campi anagrafici
- **Pagina show** inclusa anche se per ora mostra solo anagrafica — pronta per le tab future
- **Email unica per tenant** — lo stesso allievo può esistere in tenant diversi
- **Paginazione server-side** — scalabile con molti allievi
