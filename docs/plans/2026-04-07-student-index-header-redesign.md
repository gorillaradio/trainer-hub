# Student/Index Header Redesign — Variant 1 (Inline Compact)

> **Stato: DA FARE**

**Goal:** Ridisegnare la parte alta della pagina Student/Index per sfruttare meglio lo spazio verticale. Layout attuale: titolo su una riga, pulsante "Nuovo allievo" su un'altra, ricerca su un'altra ancora, filtro su un'altra. Nuovo layout: titolo + pulsante sulla stessa riga, ricerca + filtro sulla stessa riga. Da 4 livelli verticali a 2.

**Design di riferimento:** `design/basic-design.pen` → nodo "Variant 1 - Inline Compact" (ID: `7vRIo`)

**Principi:**
- Zero colori hardcoded: usare SOLO classi semantiche Tailwind (`bg-primary`, `text-muted-foreground`, `border-input`, ecc.)
- Zero `:dark` hardcoded: il tema scuro è gestito dalle CSS variables in `app.css` tramite la classe `.dark`
- Componenti shadcn/ui come base: `Button`, `Input`, `Select`
- Mobile-first: il layout a 2 righe vale anche su mobile, dato che i due elementi per riga occupano bene lo spazio

---

## Struttura attuale

```
PageHeader
├── Riga 1: <h1>Allievi</h1>
├── Riga 2: <Button>+ Nuovo allievo</Button>        ← actions (va a destra su sm:)
└── Children:
    └── Riga 3-4: <div flex-col sm:flex-row>
        ├── <Input placeholder="Cerca..." />
        └── <Select>Tutti gli stati</Select>
```

Il componente `PageHeader` (`resources/js/components/page-header.tsx`) gestisce:
- Titolo a sinistra, azioni a destra (su `sm:` inline, su mobile stacked)
- Children sotto il titolo con `mt-3`
- Sticky opzionale con `sticky top-0 z-10`
- Border-bottom, padding, background

## Struttura target (Variant 1)

```
PageHeader (modificato)
├── Riga 1: <h1>Allievi</h1> ←sinistra    <Button size="sm">+ Nuovo allievo</Button> ←destra
└── Riga 2: <Input "Cerca allievo..." /> ←fill    <FilterButton /> ←shrink
```

---

## Task 1: Aggiornare il componente PageHeader

**File:** `resources/js/components/page-header.tsx`

Il componente PageHeader attuale ha una struttura rigida che mette titolo e azioni in un contenitore flex-col che diventa flex-row su `sm:`. Per la Variant 1 vogliamo che titolo e azioni siano SEMPRE sulla stessa riga (anche su mobile).

### Modifiche:

Il wrapper interno di titolo + azioni deve diventare:

```tsx
<div className="flex items-center justify-between gap-3">
    <div className="min-w-0">{title}</div>
    {actions && <div className="shrink-0 flex items-center gap-2">{actions}</div>}
</div>
```

Differenze dal corrente:
- Rimosso `flex-col` → sempre `flex-row` (items inline)
- Rimosso `sm:flex-row sm:items-center sm:justify-between` → semplificato
- Actions: rimosso `w-full justify-between sm:w-auto sm:justify-end` → sostituito con `shrink-0` per non comprimere il pulsante

**Attenzione:** Verificare che nessun'altra pagina usi PageHeader con azioni che necessitano del layout stacked. Cercare tutti gli usi di `<PageHeader` nel progetto. Se altre pagine necessitano del vecchio layout, rendere il comportamento configurabile con una prop (es. `inline?: boolean`) oppure cambiare solo la pagina Student/Index senza toccare PageHeader.

### Verifica impatto

```bash
grep -rn "PageHeader" resources/js/pages/ --include="*.tsx"
```

Se solo Student/Index usa PageHeader con actions, la modifica è sicura. Altrimenti valutare la prop `inline`.

---

## Task 2: Aggiornare Student/Index — Header

**File:** `resources/js/pages/Tenant/Student/Index.tsx`

### 2.1 Titolo: ridurre dimensione

Il titolo è attualmente `text-2xl font-semibold`. Per coesistere con il pulsante sulla stessa riga su mobile:

```tsx
title={<h1 className="text-xl font-semibold">Allievi</h1>}
```

### 2.2 Pulsante: usare size small

Il pulsante "Nuovo allievo" deve essere compatto per stare sulla stessa riga del titolo su schermi piccoli:

```tsx
actions={
    <Button size="sm" asChild>
        <Link href={`${prefix}/students/create`}>
            <Plus data-icon="inline-start" />
            Nuovo allievo
        </Link>
    </Button>
}
```

### 2.3 Children: ricerca e filtro sulla stessa riga

Attualmente i children del PageHeader sono wrappati in un `div flex-col sm:flex-row`. Nella Variant 1 devono essere SEMPRE su una riga:

```tsx
<div className="flex items-center gap-2">
    <Input
        placeholder="Cerca allievo..."
        defaultValue={filters.search}
        onChange={(e) => handleSearch(e.target.value)}
        className="min-w-0 flex-1"
    />
    <Select
        value={filters.status || 'all'}
        onValueChange={handleStatusFilter}
    >
        <SelectTrigger className="w-auto shrink-0">
            <SelectValue placeholder="Filtri" />
        </SelectTrigger>
        <SelectContent>
            <SelectGroup>
                <SelectItem value="all">Tutti gli stati</SelectItem>
                {statuses.map((s) => (
                    <SelectItem key={s.value} value={s.value}>
                        {s.label}
                    </SelectItem>
                ))}
            </SelectGroup>
        </SelectContent>
    </Select>
</div>
```

Differenze chiave:
- `flex-col sm:flex-row` → `flex` (sempre riga)
- Input: `sm:max-w-sm` → `min-w-0 flex-1` (occupa tutto lo spazio disponibile)
- Select: `sm:w-48` → `w-auto shrink-0` (larghezza naturale, non si comprime)
- Placeholder Select: da "Tutti gli stati" a "Filtri" per risparmiare spazio orizzontale nel trigger. Il testo completo "Tutti gli stati" resta come prima opzione nel dropdown.

---

## Task 3: Verifiche

### 3.1 Verifica visiva

- [ ] Mobile (393px): titolo e pulsante sulla stessa riga, ricerca e filtro sulla stessa riga
- [ ] Tablet (768px): stesso layout, più spazio
- [ ] Desktop (1280px): stesso layout, centrato o max-width
- [ ] Tema chiaro: colori corretti, nessun hardcoded
- [ ] Tema scuro: colori corretti, nessun hardcoded

### 3.2 Verifica funzionale

- [ ] Ricerca funziona (digitare e verificare URL update)
- [ ] Filtro stato funziona (selezionare e verificare)
- [ ] Pulsante "Nuovo allievo" naviga a /students/create
- [ ] Sticky header funziona durante scroll

### 3.3 Classi Tailwind permesse

Usare SOLO classi semantiche:

| Scopo | Classe corretta | NON usare |
|-------|----------------|-----------|
| Sfondo pagina | `bg-background` | `bg-gray-900`, `bg-[#1a1a1a]` |
| Testo principale | `text-foreground` | `text-white`, `text-gray-100` |
| Testo secondario | `text-muted-foreground` | `text-gray-500`, `text-[#666]` |
| Bordi | `border-border` o `border-input` | `border-gray-700` |
| Pulsante primario | `Button` (default variant) | `bg-green-500` |
| Input sfondo | `Input` component (usa var interne) | `bg-gray-800` |

---

## Riepilogo modifiche file

| File | Azione |
|------|--------|
| `resources/js/components/page-header.tsx` | Rendere titolo+azioni sempre inline |
| `resources/js/pages/Tenant/Student/Index.tsx` | Titolo più piccolo, bottone `sm`, search+filter sempre inline |

**Nessun nuovo file.** Nessuna nuova dipendenza. Nessuna modifica backend.
