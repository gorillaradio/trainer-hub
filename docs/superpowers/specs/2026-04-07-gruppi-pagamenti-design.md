# Gruppi e Pagamenti — Design Spec

## Panoramica

Sistema di gestione gruppi, tariffe, pagamenti mensilità e iscrizioni per TrainerHub. Il trainer registra manualmente i pagamenti in contanti ricevuti dagli studenti. Il sistema traccia le transazioni, la copertura mensile, il debito/credito, e le iscrizioni con scadenza.

---

## Schema dati

### Tabella `groups`

| Colonna | Tipo | Note |
|---------|------|------|
| id | UUID | PK |
| tenant_id | string | FK, BelongsToTenant |
| name | string | es. "Under 14", "Agonisti" |
| description | text, nullable | |
| color | string | es. "#FF5733" — per distinguere nella UI |
| monthly_fee_amount | integer | centesimi, es. 4000 = €40 |
| created_at, updated_at | timestamps | |

### Tabella `group_student` (pivot)

| Colonna | Tipo | Note |
|---------|------|------|
| id | UUID | PK |
| tenant_id | string | BelongsToTenant |
| group_id | UUID | FK |
| student_id | UUID | FK |
| is_primary | boolean | default false. Se true, la tariffa di questo gruppo ha priorità |
| created_at, updated_at | timestamps | |
| UNIQUE | (tenant_id, group_id, student_id) | |

### Colonne aggiunte su `students`

| Colonna | Tipo | Note |
|---------|------|------|
| monthly_fee_override | integer, nullable | centesimi — sovrascrive la tariffa gruppo |
| current_cycle_started_at | date, nullable | ancora del ciclo di pagamento corrente |
| past_cycles | JSON, nullable | array di cicli archiviati |

Formato `past_cycles`:
```json
[
  {
    "started_at": "2025-09-16",
    "ended_at": "2026-02-01",
    "reason": "suspended"
  }
]
```

### Tabella `payments` (nuova — transazione reale)

| Colonna | Tipo | Note |
|---------|------|------|
| id | UUID | PK |
| tenant_id | string | BelongsToTenant |
| student_id | UUID | FK |
| amount | integer | centesimi — quanto il trainer ha effettivamente ricevuto |
| payment_method | enum | Per ora solo 'cash' |
| paid_at | datetime | quando è stato pagato |
| notes | text, nullable | |
| created_at, updated_at | timestamps | |

### Tabella `monthly_fees` (ristrutturata — copertura mese)

| Colonna | Tipo | Note |
|---------|------|------|
| id | UUID | PK |
| tenant_id | string | BelongsToTenant |
| student_id | UUID | FK |
| payment_id | UUID | FK → payments (non nullable — ogni record ha sempre un pagamento) |
| period | string | "2026-04" — mese coperto |
| expected_amount | integer | centesimi — tariffa al momento della registrazione |
| due_date | date | data attesa di pagamento (basata sull'ancora) |
| notes | text, nullable | |
| created_at, updated_at | timestamps | |
| UNIQUE | (tenant_id, student_id, period) | |

Campi rimossi rispetto alla versione attuale: `amount` (ora su payments), `paid_at` (derivato da payment.paid_at), `payment_method` (ora su payments).

**Record solo per mesi pagati.** I mesi scoperti vengono calcolati a runtime dal service: confronto tra ancora del ciclo, data odierna e periodi con record esistente. Quando il trainer registra un pagamento, il sistema crea i record per i mesi scoperti più vecchi (FIFO).

### Tabella `enrollment_fees` (ristrutturata)

| Colonna | Tipo | Note |
|---------|------|------|
| id | UUID | PK |
| tenant_id | string | BelongsToTenant |
| student_id | UUID | FK |
| payment_id | UUID | FK → payments (non nullable) |
| expected_amount | integer | centesimi |
| starts_at | date | inizio copertura |
| expires_at | date | fine copertura |
| notes | text, nullable | |
| created_at, updated_at | timestamps | |

Campi rimossi: `academic_year`, `amount`, `paid_at`, `payment_method`.

### Setting tenant

Nuova chiave nel JSON `settings` del tenant:
- `enrollment_duration_months`: integer, default 12

---

## Logica tariffa studente

Priorità per determinare la tariffa mensile:

1. `monthly_fee_override` su student → usa quello
2. Gruppo con `is_primary = true` → tariffa di quel gruppo
3. Più gruppi senza primary → `MIN(monthly_fee_amount)` tra i gruppi
4. Nessun gruppo e nessun override → `null` (dialog manuale obbligatorio)

---

## Business logic (Service layer)

### `FeeCalculationService`

**`getEffectiveRate(student): int|null`**
Calcola la tariffa mensile secondo la priorità sopra.

**`getBalance(student): int`**
Saldo dello studente: `SUM(payments.amount) - SUM(monthly_fees.expected_amount) - SUM(enrollment_fees.expected_amount)` dove le fee sono collegate a un payment (pagate). Negativo = debito, positivo = credito.

### `MonthlyFeeService`

**`getUncoveredPeriods(student): array`**
Calcola i mesi scoperti a runtime: genera la lista dei periodi attesi tra `current_cycle_started_at` e oggi, sottrae i periodi già coperti (con record in `monthly_fees`). Ritorna array ordinato cronologicamente (FIFO).

**`registerPayment(student, months, customAmount?): Payment`**

1. Calcola `effectiveRate` da `FeeCalculationService` oppure usa `customAmount` se nessuna tariffa calcolabile
2. Calcola `balance` corrente
3. Determina quali mesi coprire: prende i primi N mesi da `getUncoveredPeriods()` (FIFO — si pagano sempre i mesi arretrati prima)
4. Importo proposto = `(effectiveRate x months) - balance` (debito aggiunge, credito sottrae)
5. Crea 1 record `payments` con l'importo effettivamente ricevuto
6. Crea N record `monthly_fees` (uno per mese coperto), ciascuno con `expected_amount` = tariffa, `payment_id` collegato
7. Se `current_cycle_started_at` è null → settalo a oggi (primo pagamento del ciclo)

**Ancora e due_date:**
- Primo pagamento del ciclo: `current_cycle_started_at` = oggi
- `due_date` mese N = `current_cycle_started_at` + N mesi (clamped all'ultimo giorno del mese: 31 gen → 28 feb)
- Pagamento in ritardo: l'ancora NON si sposta

### `EnrollmentFeeService`

**`registerEnrollment(student, amount, startsAt?): Payment`**

1. Se esiste iscrizione attiva non scaduta → `starts_at` = vecchia `expires_at` (rinnovo anticipato, estende)
2. Altrimenti → `starts_at` = oggi
3. `expires_at` = `starts_at` + `enrollment_duration_months` da settings tenant (default 12)
4. Crea 1 `payments` + 1 `enrollment_fees` collegato

**`isEnrollmentExpired(student): bool`**
Controlla se l'ultima iscrizione ha `expires_at` < oggi.

**`getLatestEnrollment(student): EnrollmentFee|null`**
Ritorna l'iscrizione più recente per mostrare stato nella UI.

### `StudentSuspensionService` (estensione)

**Alla sospensione:**
1. Archivia ciclo corrente in `past_cycles` JSON: `{ started_at, ended_at: oggi, reason: "suspended" }`
2. Resetta `current_cycle_started_at` a null

**Alla riattivazione:**
- `current_cycle_started_at` resta null → verrà settato al primo pagamento successivo

---

## Edge case — Decisioni

| # | Caso | Decisione |
|---|------|-----------|
| 1 | Primo pagamento | `current_cycle_started_at` = oggi |
| 2 | Pagamento in ritardo | Ancora fissa, non si sposta |
| 3 | Mesi saltati senza sospensione | Il trainer decide — può sospendere quando vuole |
| 4 | Sospensione e riattivazione | Ciclo archiviato in `past_cycles`, nuovo ciclo al primo pagamento |
| 5 | Nessun gruppo né override | Dialog manuale obbligatorio per inserire importo |
| 6 | Rimosso da gruppo | Tariffa cambia subito; pagamenti registrati immutabili |
| 7 | Ancora al 31 del mese | Clamped all'ultimo giorno del mese (31 gen → 28 feb) |
| 8 | Importo diverso dalla tariffa | Dialog con importo pre-compilato e modificabile; differenza genera debito/credito |
| 9 | Pagamento multi-mese | 1 payment + N monthly_fees collegate |
| 10 | Iscrizione scaduta + mensilità | Avviso nella UI, ma pagamento mensilità consentito |
| 11 | Rinnovo anticipato | Estende dalla vecchia scadenza |
| 12 | Iscrizione scade durante sospensione | Tracciate indipendentemente; al ritorno paga entrambi |
| 13 | Cambio durata default iscrizione | Solo nuove iscrizioni usano il nuovo valore |

---

## UI e flussi

### CRUD Gruppi

Pagine `Tenant/Group/`:
- **Index** — lista gruppi con colore (badge), nome, tariffa formattata, conteggio studenti. Ordinabile per nome
- **Create** — form: nome (required), descrizione (opzionale), color picker, tariffa (input €, salvato in centesimi)
- **Edit** — stesso form + lista studenti assegnati con possibilità di aggiungere/rimuovere

Voce "Gruppi" nel menu laterale tenant, tra "Allievi" e le future voci.

### Student/Show — Tab "Dati personali" — Card "Gruppi e tariffa"

Nella pagina dettaglio studente, all'interno del tab Dati personali esistente:
- Card con lista gruppi assegnati (badge colore + nome)
- Pulsante "Aggiungi a gruppo" → select dei gruppi disponibili
- Pulsante rimuovi su ogni gruppo
- Radio/toggle per segnare un gruppo come "principale"
- Override tariffa (campo editabile, nullable)
- Tariffa risultante calcolata in tempo reale

### Student/Show — Nuovo tab "Pagamenti"

**Riepilogo in alto:**
- Tariffa corrente: "€50/mese (da Agonisti)" oppure "€40/mese (override)" oppure "Nessuna tariffa"
- Saldo: "In pari" / "Debito: €5" / "Credito: €10"
- Iscrizione: "Scade il 15/09/2026" oppure "Scaduta il ..." (in rosso)

**Pulsante "Registra mensilità":**
- Click → dialog
- Dialog: importo pre-compilato (tariffa + debito), mesi (default 1, modificabile), metodo (cash), note
- Se nessuna tariffa → campo importo vuoto e required
- Se iscrizione scaduta → banner avviso nel dialog, non blocca
- Importo modificabile dal trainer

**Pulsante "Registra iscrizione":**
- Dialog: importo, data inizio (calcolata), data fine (calcolata), modificabili
- Se rinnovo → mostra "Rinnovo da [vecchia scadenza]"

**Storico pagamenti:**
- Lista cronologica dei payments dello studente (più recente in alto)
- Ogni riga: data, importo, tipo (mensilità/iscrizione), dettaglio (mesi coperti o periodo iscrizione), note

---

## Scope

### In scope
1. CRUD Gruppi (model, migration, controller, policy, form requests, pagine)
2. Pivot `group_student` con `is_primary`
3. Colonne `monthly_fee_override`, `current_cycle_started_at`, `past_cycles` su students
4. Tabella `payments`
5. Ristrutturazione `monthly_fees` (copertura mese, collegata a payment)
6. Ristrutturazione `enrollment_fees` (starts_at/expires_at, collegata a payment)
7. `FeeCalculationService`, `MonthlyFeeService`, `EnrollmentFeeService`
8. Estensione suspension/reactivation (archiviazione cicli)
9. Tab "Pagamenti" nella Student/Show
10. Card "Gruppi e tariffa" nel tab Dati personali della Student/Show
11. Setting tenant `enrollment_duration_months`
12. Enum `PaymentMethod` semplificato (solo Cash)
13. Test: isolamento tenant, autorizzazione, validazione, business rules, tutti i 13 edge case

### Fuori scope
- Stripe Connect / pagamenti online
- Gruppi automatici (regole età/genere)
- Dashboard con statistiche pagamenti
- Notifiche (pagamenti in ritardo, iscrizioni in scadenza)
- Export/report pagamenti
- Metodi di pagamento diversi da Cash
