# Skill: frontend-review

## Cosa fa

Checklist obbligatoria che valida che il codice frontend rispetti le convenzioni del progetto: **shadcn-first**, **Inertia-first**, **Tailwind-first**. Controlla che non vengano usati tag HTML semantici puri, componenti custom quando shadcn ne ha uno ufficiale, o pattern non-Inertia.

## Quando si attiva

- **Automaticamente**: Claude la invoca prima di scrivere codice UI e dopo ogni blocco di lavoro frontend significativo (come indicato in CLAUDE.md).
- **Manualmente**: puoi invocarla con `/frontend-review` in qualsiasi momento per far ricontrollare il codice appena scritto.
- **Via code-reviewer**: il code-reviewer agent la usa come criterio di validazione per il lavoro frontend.

## Cosa controlla

| Area | Regola |
|------|--------|
| Componenti | Usare sempre shadcn/ui prima di creare componenti custom |
| Navigazione | `<Link>` e `router` di Inertia, mai `<a>` o `window.location` |
| Form | `useForm` di Inertia, mai `useState` + `fetch`/`axios` |
| Styling | Solo classi Tailwind, mai CSS inline o file CSS custom |
| HTML | No tag semantici puri (`<dl>`, `<section>`, `<nav>`, ecc.) — solo `<div>`/`<p>` + Tailwind o componenti shadcn |
| Icone | Solo `lucide-react`, mai SVG inline |
| TypeScript | Props tipizzate, tipi in `resources/js/types/`, mai `any` |
| Labels | UI in italiano, codice in inglese |

## Dove si trova

```
.claude/skills/frontend-review/SKILL.md
```

## Perché esiste

Creata dopo che Claude ha introdotto tag HTML semantici (`<dl>`, `<dt>`, `<dd>`) in una pagina React, violando la filosofia component-based del progetto. La skill previene che errori simili si ripetano imponendo un controllo sistematico prima e dopo ogni modifica UI.
