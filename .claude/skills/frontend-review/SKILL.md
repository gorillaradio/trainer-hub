---
name: frontend-review
description: Checklist obbligatoria per validare che il codice frontend segua le convenzioni del progetto: shadcn-first, Inertia-first, Tailwind-first, mobile-first. Da invocare PRIMA di scrivere codice UI e DOPO ogni blocco di lavoro frontend significativo.
user-invocable: true
---

# Frontend Review — Checklist Convenzioni TrainerHub

## Quando usare questa skill

- **PRIMA** di scrivere qualsiasi codice UI/frontend
- **DOPO** ogni blocco significativo di lavoro UI (nuova pagina, nuovo componente, modifica form)
- Quando il `code-reviewer` agent deve validare lavoro frontend

## Checklist

### 1. shadcn-first
- [ ] Ho verificato con la skill `shadcn` (o `npx shadcn@latest search`) se esiste un componente per quello che mi serve
- [ ] NON sto creando un componente custom quando shadcn ne ha uno ufficiale
- [ ] Icone solo da `lucide-react`, mai SVG inline o icon font

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
- [ ] Uso colori semantici (`bg-primary`, `text-muted-foreground`) e non raw (`bg-blue-500`)

### 4. Mobile-first
- [ ] Il layout funziona su schermi piccoli come caso base
- [ ] I breakpoint responsive vanno da mobile → desktop (`sm:`, `md:`, `lg:`), non viceversa

### 5. TypeScript
- [ ] Ogni componente ha props tipizzate
- [ ] Mai `any`

### 6. Convenzioni progetto
- [ ] File pagine: PascalCase singolare (`Tenant/Student/Index.tsx`)
- [ ] Componenti: PascalCase (`StudentForm.tsx`)
- [ ] Labels UI: italiano
- [ ] Codice/variabili: inglese

## Come usare nel code-reviewer agent

Quando lanci il `superpowers:code-reviewer` per lavoro frontend, passa questa checklist come criterio di review. Ogni violazione deve essere segnalata e corretta.
