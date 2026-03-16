---
name: frontend-review
description: Checklist obbligatoria per validare che il codice frontend segua le convenzioni del progetto: shadcn-first, Inertia-first, Tailwind-first. Da invocare PRIMA di scrivere codice UI e DOPO ogni blocco di lavoro frontend significativo.
user-invocable: true
---

# Frontend Review — Checklist Convenzioni TrainerHub

## Quando usare questa skill

- **PRIMA** di scrivere qualsiasi codice UI/frontend
- **DOPO** ogni blocco significativo di lavoro UI (nuova pagina, nuovo componente, modifica form)
- Quando il `code-reviewer` agent deve validare lavoro frontend

## Pre-check: prima di scrivere codice

### 1. shadcn-first
- [ ] Ho verificato con la skill `shadcn` (o `npx shadcn@latest search`) se esiste un componente per quello che mi serve
- [ ] Ho consultato `npx shadcn@latest docs <componente>` per i pattern d'uso corretti
- [ ] NON sto creando un componente custom quando shadcn ne ha uno ufficiale

### 2. Inertia-first
- [ ] Uso `useForm` per i form (non `useState` + `fetch`/`axios`)
- [ ] Uso `<Link>` per la navigazione (non `<a>`)
- [ ] Uso `router` per navigazione programmatica (non `window.location`)
- [ ] Uso il persistent layout pattern di Inertia
- [ ] Uso `usePage()` per accedere alle shared props

### 3. Tailwind-first
- [ ] Tutto lo styling è via classi Tailwind
- [ ] Nessun CSS inline (`style={{}}`)
- [ ] Nessun file CSS custom
- [ ] Uso `gap-*` e non `space-y-*` / `space-x-*`
- [ ] Uso `size-*` quando larghezza e altezza sono uguali
- [ ] Uso colori semantici (`bg-primary`, `text-muted-foreground`) e non raw (`bg-blue-500`)

## Post-check: dopo aver scritto codice

### 4. Niente HTML semantico puro
Questi tag NON devono comparire nel codice React:
- `<dl>`, `<dt>`, `<dd>`
- `<section>`, `<article>`, `<aside>`
- `<nav>`, `<header>`, `<footer>`, `<main>`
- `<figure>`, `<figcaption>`
- `<hr>` → usa `<Separator />`

Usare `<div>` e `<p>` con classi Tailwind, oppure componenti shadcn.

### 5. Componenti shadcn obbligatori
| Bisogno | Componente shadcn |
|---------|-------------------|
| Raggruppamento | `Card` / `CardHeader` / `CardContent` / `CardFooter` |
| Tabelle | `Table` / `TableHeader` / `TableRow` / `TableCell` |
| Status/tag | `Badge` |
| Azioni | `Button` |
| Form fields | `Input` + `Label` |
| Selezione | `Select` / `Combobox` |
| Overlay/modale | `Dialog` / `Sheet` / `AlertDialog` |
| Feedback | `sonner` (toast) / `Alert` |
| Loading | `Skeleton` |
| Separatori | `Separator` |
| Vuoto | `Empty` (se disponibile) |

### 6. Icone
- Solo da `lucide-react`
- Mai SVG inline o icon font
- Dentro `Button`: usare `data-icon`

### 7. Pattern errori form
```tsx
{errors.field && <p className="text-sm text-destructive">{errors.field}</p>}
```

### 8. TypeScript
- Ogni componente ha props tipizzate
- I tipi sono in `resources/js/types/`
- Mai `any`

### 9. Convenzioni progetto
- File pagine: PascalCase singolare (`Tenant/Student/Index.tsx`)
- Componenti: PascalCase (`StudentForm.tsx`)
- Labels UI: italiano
- Codice/variabili: inglese
- Mobile-first: il trainer usa spesso il telefono

## Come usare nel code-reviewer agent

Quando lanci il `superpowers:code-reviewer` per lavoro frontend, passa questa checklist come criterio di review. Ogni violazione deve essere segnalata e corretta.
