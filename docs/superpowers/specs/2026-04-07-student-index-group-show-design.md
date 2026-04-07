# Student Index Ristrutturazione + Group Show

> **Data:** 2026-04-07
> **Stato:** DA APPROVARE

## Contesto

La Student Index è la pagina più usata dal trainer, che lavora da telefono in palestra. Oggi per registrare un pagamento serve: Index → Show → tab Pagamenti → dialog. Troppe azioni. Inoltre i gruppi non hanno una pagina Show per gestire i membri, e l'override tariffa non è editabile da UI.

Questo spec copre:
1. Ristrutturazione Student Index (header, colonne, filtri, quick-pay)
2. Nuova Group Show page con ricerca studenti
3. Override tariffa in Student Edit/Create
4. Rimozione concetto "gruppo primario" dalla UI

---

## 1. Student Index — Header

Già definito in `docs/plans/2026-04-07-student-index-header-redesign.md`. Riepilogo:

- **Riga 1:** "Allievi" (text-xl) + "+ Nuovo allievo" (Button sm) — sempre inline
- **Riga 2:** Input ricerca (flex-1) + pulsante "Filtri" (icona `SlidersHorizontal`) — sempre inline
- Da 4 livelli verticali a 2

## 2. Student Index — Filtri (Drawer dal basso)

Il pulsante "Filtri" apre un **Drawer** (shadcn, basato su Vaul) dal basso:

- **Select stato:** Tutti / Attivi (default) / Sospesi / Inattivi
- **Switch "Mostra pagamenti":** attiva/disattiva colonna pagamento

Alla chiusura del drawer i filtri si applicano come parametri URL (`?status=active&payments=1`), stesso pattern dei filtri esistenti via `router.get()`.

**Filtro default cambia:** da "Tutti" a "Attivi".

## 3. Student Index — Tabella

### Colonne (toggle pagamenti spento)

| Nome | Cognome | (azione) |
|------|---------|----------|

### Colonne (toggle pagamenti acceso)

| Nome | Cognome | Pag. | (azione) |
|------|---------|------|----------|

### Contenuto righe

- **Nome** — testo semplice
- **Cognome** — link a Student/Show
- **Pag.** (solo con toggle attivo):
  - Pallino verde = in pari (0 mesi scoperti)
  - Badge rosso con numero = mesi arretrati (es. "3")
  - Pallino grigio = nessuna tariffa configurata (effectiveRate null)
- **Azione:**
  - Studenti attivi → pulsante "€ Paga"
  - Studenti sospesi → badge "Sospeso" (variant destructive)
  - Studenti inattivi → badge "Inattivo" (variant secondary)

### Regole per stato non-attivo

- Filtro su "Sospesi" o "Inattivi": colonna Pag. nascosta (anche se toggle acceso), pulsante Paga nascosto, badge stato al suo posto
- Filtro "Tutti": mix — attivi con semaforo/paga, sospesi/inattivi con badge stato

### Colonne rimosse rispetto a oggi

- Email (era nascosta su mobile, ora rimossa del tutto)
- Telefono (idem)
- Colonna stato separata (accorpata con pagamento/azione)

## 4. Student Index — Quick-pay

Il pulsante "€ Paga" apre il dialog `RegisterMonthlyDialog` già esistente.

**Caricamento dati: lazy (on-demand)**

Quando il trainer preme "€ Paga":
1. Chiamata ad un endpoint dedicato: `GET /app/{tenant}/students/{student}/payment-data`
2. L'endpoint ritorna: `effectiveRate`, `balance`, `uncoveredPeriods`, `uncoveredCount`, `latestEnrollment`, `enrollmentExpired`
3. Spinner sul pulsante durante il caricamento
4. Dialog si apre con i dati ricevuti

Questo evita di appesantire la index con dati che servono solo al momento del click.

**Endpoint backend:**
- Route: `GET students/{student}/payment-data`
- Controller: `StudentPaymentController@paymentData` (o nuovo metodo nel controller esistente)
- Usa gli stessi service: `FeeCalculationService`, `MonthlyFeeService`, `EnrollmentFeeService`
- Ritorna JSON (non Inertia response)

## 5. Student Index — Dati pagamento per il toggle

Quando il toggle "Mostra pagamenti" è attivo, il backend deve fornire `uncovered_count` per ogni studente.

**Approccio:** metodo batch nel `MonthlyFeeService` — `getUncoveredCountsBatch(Collection $students): array` — una query aggregata che calcola i mesi scoperti per tutti gli studenti in blocco. Ritorna `[student_id => count]`.

Il controller aggiunge `uncovered_count` e `has_rate` (boolean, se effectiveRate !== null) ai dati di ogni studente solo quando `request->input('payments')` è truthy.

## 6. Group Show — Nuova pagina

### Route

`GET /app/{tenant}/groups/{group}` → `GroupController@show`

La card nella Group Index ora punta alla Show (non più alla Edit).

### Layout

- **Card info gruppo:**
  - Nome con pallino colore
  - Descrizione
  - Tariffa mensile (formattata in €)
  - Link/pulsante "Modifica" → Group Edit

- **Card membri:**
  - Campo ricerca in cima per aggiungere studenti
  - Lista membri: Nome Cognome (link a Student/Show) + pulsante X per rimuovere
  - Empty state se nessun membro

### Ricerca studenti — endpoint

`GET /app/{tenant}/students/search?q=nome&exclude_group={groupId}`

- Cerca tra studenti attivi del tenant
- Filtra per nome/cognome (LIKE)
- Esclude studenti già nel gruppo
- Ritorna JSON: `[{id, first_name, last_name}]`
- Limit: 10 risultati

### Aggiunta/rimozione membri

Usa gli endpoint esistenti:
- `POST /students/{student}/groups` (attach) — body: `{group_id}`
- `DELETE /students/{student}/groups/{group}` (detach)

Dopo ogni azione, reload Inertia della pagina per aggiornare la lista.

## 7. Group Edit — Semplificazione

- Rimuovere la sezione "Allievi nel gruppo" dalla Edit
- Resta solo: form dati gruppo (nome, descrizione, colore, tariffa) + pulsante elimina
- Nessuna altra modifica

## 8. Override tariffa in Student Edit/Create

### Frontend

Campo nel form studente (`resources/js/components/student-form.tsx` o equivalente):
- **Label:** "Tariffa personalizzata (€/mese)"
- **Tipo:** Input number, step 0.01, min 0
- **Hint:** "Se impostata, sovrascrive la tariffa del gruppo"
- **Valore:** nullable — campo vuoto = nessun override, usa tariffa minima gruppi

### Backend

- Aggiungere `monthly_fee_override` a `StoreStudentRequest` e `UpdateStudentRequest`: `nullable|numeric|min:0`
- Conversione euro→centesimi in `prepareForValidation()` (stesso pattern di `RegisterMonthlyPaymentRequest`)
- Il campo è già fillable nel model e il `FeeCalculationService` lo priorizza

## 9. Rimozione concetto "gruppo primario"

### Frontend

- **StudentGroupsCard:** rimuovere icona corona e toggle primary
- La lista gruppi resta con aggiunta/rimozione

### Backend

- **`FeeCalculationService.getEffectiveRate()`:** rimuovere logica "primary group". Nuova logica: `override > min(gruppi.monthly_fee_amount)`. Se nessun gruppo e nessun override → null.
- **`StudentGroupController`:** rimuovere metodi `setPrimary()` e `clearPrimary()`
- **Route:** rimuovere `PUT students/{student}/groups/{group}/primary` e `DELETE students/{student}/groups/primary`
- **DB:** il flag `is_primary` sul pivot resta (non serve migration per rimuoverlo), semplicemente non viene più usato

---

## File coinvolti

| File | Azione |
|------|--------|
| `resources/js/components/page-header.tsx` | Titolo+azioni sempre inline (da piano header) |
| `resources/js/pages/Tenant/Student/Index.tsx` | Ristrutturazione completa: colonne, filtri drawer, quick-pay |
| `app/Http/Controllers/Tenant/StudentController.php` | Index: filtro default attivi, dati pagamento condizionali |
| `app/Http/Controllers/Tenant/StudentPaymentController.php` | Nuovo metodo `paymentData()` |
| `app/Services/MonthlyFeeService.php` | Nuovo metodo `getUncoveredCountsBatch()` |
| `app/Services/FeeCalculationService.php` | Rimuovere logica primary, semplificare a override > min |
| `resources/js/pages/Tenant/Group/Show.tsx` | **Nuovo file** — pagina Show gruppo |
| `resources/js/pages/Tenant/Group/Index.tsx` | Card punta a Show invece che Edit |
| `resources/js/pages/Tenant/Group/Edit.tsx` | Rimuovere sezione allievi |
| `app/Http/Controllers/Tenant/GroupController.php` | Aggiungere metodo `show()` |
| `app/Http/Controllers/Tenant/StudentController.php` | Nuovo metodo `search()` per endpoint ricerca |
| `app/Http/Controllers/Tenant/StudentGroupController.php` | Rimuovere `setPrimary()` e `clearPrimary()` |
| `resources/js/components/student-groups-card.tsx` | Rimuovere UI corona/primary |
| `resources/js/components/student-form.tsx` (o equivalente) | Aggiungere campo override tariffa |
| `app/Http/Requests/Tenant/StoreStudentRequest.php` | Aggiungere validazione `monthly_fee_override` |
| `app/Http/Requests/Tenant/UpdateStudentRequest.php` | Idem |
| `routes/tenant.php` | Nuove route (group show, student search, payment-data), rimuovere route primary |

## Verifica

### Test automatici

- **Student Index:** test endpoint con filtro default attivi, con e senza `payments=1`, verifica dati pagamento presenti solo con toggle
- **Quick-pay endpoint:** test `GET students/{id}/payment-data` ritorna dati corretti
- **Student search:** test `GET students/search?q=...&exclude_group=...` filtra e esclude correttamente
- **Group Show:** test render con membri, test attach/detach
- **Override tariffa:** test create/update studente con e senza override
- **FeeCalculationService:** test nuova logica senza primary (override > min gruppi)
- **Isolamento tenant:** ogni test verifica che tenant A non veda dati di tenant B

### Verifica manuale

- Mobile (393px): header 2 righe, drawer filtri, tabella leggibile, quick-pay funzionante
- Tema chiaro e scuro: nessun colore hardcoded
- Flusso completo: index → paga → dialog → conferma → semaforo si aggiorna
- Group Show: cerca studente → aggiungi → appare in lista → rimuovi → sparisce
