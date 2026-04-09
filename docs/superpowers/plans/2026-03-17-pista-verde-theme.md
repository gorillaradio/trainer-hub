# "Pista Verde" Color Theme Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the default neutral gray shadcn/ui theme with the "Pista Verde" palette — a green + track-terracotta color scheme designed for a personal track & field coach app, in both light and dark modes.

**Architecture:** The project uses Tailwind CSS v4 with CSS-first configuration. All theme colors are defined as CSS custom properties in OKLCH color space inside `resources/css/app.css`, split between `:root` (light) and `.dark` (dark). The shadcn/ui `@theme` block maps these to Tailwind utility classes. We only need to update the CSS variable values — all components already reference them via semantic tokens (`bg-primary`, `text-foreground`, etc.). We also need to update the inline anti-flash styles in `app.blade.php`.

**Tech Stack:** Tailwind CSS 4, OKLCH color space, shadcn/ui CSS variables, Laravel Blade (SSR anti-flash)

---

## Color Mapping Reference

### Design Tokens → shadcn CSS Variables

| shadcn variable | Role | Light hex | Dark hex |
|---|---|---|---|
| `--background` | Page background | `#F4F7F5` | `#0A1510` |
| `--foreground` | Primary text | `#111B15` | `#EEF3F0` |
| `--card` | Card surfaces | `#FFFFFF` | `#142019` |
| `--card-foreground` | Card text | `#111B15` | `#EEF3F0` |
| `--popover` | Popover/dropdown bg | `#FFFFFF` | `#142019` |
| `--popover-foreground` | Popover text | `#111B15` | `#EEF3F0` |
| `--primary` | Primary action (green) | `#1B7A3D` | `#34A853` |
| `--primary-foreground` | Text on primary | `#FFFFFF` | `#0A1510` |
| `--secondary` | Secondary surfaces | `#EDF2EE` | `#1C2A22` |
| `--secondary-foreground` | Text on secondary | `#111B15` | `#EEF3F0` |
| `--muted` | Muted backgrounds | `#EDF2EE` | `#1C2A22` |
| `--muted-foreground` | Muted text | `#7A9588` | `#5C7A68` |
| `--accent` | Accent/hover bg | `#E2F0E7` | `#1A3A24` |
| `--accent-foreground` | Text on accent | `#1B7A3D` | `#34A853` |
| `--destructive` | Track terracotta | `#C45332` | `#D4603E` |
| `--destructive-foreground` | Destructive text | `#C45332` | `#D4603E` |
| `--border` | Borders | `#D4DDD8` | `#2A3D31` |
| `--input` | Input borders | `#D4DDD8` | `#2A3D31` |
| `--ring` | Focus rings | `#1B7A3D` | `#34A853` |
| `--sidebar` | Sidebar bg | `#F4F7F5` | `#142019` |
| `--sidebar-foreground` | Sidebar text | `#111B15` | `#EEF3F0` |
| `--sidebar-primary` | Sidebar primary | `#1B7A3D` | `#34A853` |
| `--sidebar-primary-foreground` | Sidebar primary text | `#FFFFFF` | `#0A1510` |
| `--sidebar-accent` | Sidebar hover | `#E2F0E7` | `#1A3A24` |
| `--sidebar-accent-foreground` | Sidebar hover text | `#1B7A3D` | `#34A853` |
| `--sidebar-border` | Sidebar borders | `#D4DDD8` | `#2A3D31` |
| `--sidebar-ring` | Sidebar focus | `#1B7A3D` | `#34A853` |

### OKLCH Conversions

```
/* Light theme */
#F4F7F5 → oklch(0.973 0.004 158.314)
#FFFFFF → oklch(1.000 0 0)
#EDF2EE → oklch(0.956 0.007 152.483)
#D4DDD8 → oklch(0.889 0.012 162.388)
#1B7A3D → oklch(0.511 0.127 150.427)
#C45332 → oklch(0.583 0.153 36.864)
#E2F0E7 → oklch(0.942 0.019 158.101)
#111B15 → oklch(0.210 0.019 158.038)
#7A9588 → oklch(0.645 0.036 164.611)

/* Dark theme */
#0A1510 → oklch(0.183 0.019 164.288)
#142019 → oklch(0.230 0.022 158.594)
#1C2A22 → oklch(0.269 0.024 159.255)
#2A3D31 → oklch(0.340 0.032 156.578)
#34A853 → oklch(0.648 0.160 148.517)
#D4603E → oklch(0.627 0.155 37.025)
#1A3A24 → oklch(0.317 0.055 152.284)
#EEF3F0 → oklch(0.960 0.007 160.780)
#5C7A68 → oklch(0.551 0.044 158.285)
```

---

## Files to Modify

| File | What changes |
|---|---|
| `resources/css/app.css` | All `:root` and `.dark` CSS variable values |
| `resources/views/app.blade.php` | Inline anti-flash background-color values (lines 25, 29) |
| `resources/js/app.tsx` | Inertia progress bar color (line 27) |
| `resources/js/components/appearance-tabs.tsx` | `neutral-*` → semantic tokens |
| `resources/js/components/user-info.tsx` | `neutral-*` → semantic tokens |
| `resources/js/components/nav-footer.tsx` | `neutral-*` → semantic tokens |
| `resources/js/components/app-header.tsx` | `neutral-*`, `text-black/white` → semantic tokens |
| `resources/js/layouts/auth/auth-split-layout.tsx` | `bg-zinc-900`, `text-black` → semantic tokens |

**Files NOT needing changes:**
- All shadcn/ui components — they already use semantic CSS variables
- `resources/js/lib/student-status.ts` — uses semantic badge variants (`default`, `secondary`, `destructive`)
- `resources/js/components/ui/badge.tsx` — references CSS variables
- `resources/js/pages/Welcome.tsx` — contains hardcoded colors but this is Laravel's default welcome page; it will be replaced/removed before launch

**Known hardcoded `neutral-*` colors (follow-up):**
These files use hardcoded Tailwind `neutral-*`/`zinc-*` classes that stay gray instead of adopting the green tint. Convert them to semantic tokens (`bg-muted`, `text-muted-foreground`, etc.) in a follow-up pass:
- `resources/js/components/appearance-tabs.tsx` — `bg-neutral-100`, `bg-neutral-800`, etc.
- `resources/js/components/user-info.tsx` — `bg-neutral-200`, `bg-neutral-700`
- `resources/js/components/nav-footer.tsx` — `text-neutral-600`, `text-neutral-800`
- `resources/js/components/app-header.tsx` — `text-neutral-900`, `bg-neutral-800`
- `resources/js/layouts/auth/auth-split-layout.tsx` — `bg-zinc-900`

**Note on progress bar:** The Inertia progress bar doesn't support CSS variable references, so `#1B7A3D` is used as a static value. It won't adapt in dark mode (shows the light green) but this is acceptable for a thin progress strip.

---

## Task 1: Update Light Theme CSS Variables

**Files:**
- Modify: `resources/css/app.css:64-98`

- [ ] **Step 1: Replace `:root` block with Pista Verde light values**

Replace lines 64–98 in `resources/css/app.css` with:

```css
:root {
    --background: oklch(0.973 0.004 158.314);
    --foreground: oklch(0.210 0.019 158.038);
    --card: oklch(1.000 0 0);
    --card-foreground: oklch(0.210 0.019 158.038);
    --popover: oklch(1.000 0 0);
    --popover-foreground: oklch(0.210 0.019 158.038);
    --primary: oklch(0.511 0.127 150.427);
    --primary-foreground: oklch(1.000 0 0);
    --secondary: oklch(0.956 0.007 152.483);
    --secondary-foreground: oklch(0.210 0.019 158.038);
    --muted: oklch(0.956 0.007 152.483);
    --muted-foreground: oklch(0.645 0.036 164.611);
    --accent: oklch(0.942 0.019 158.101);
    --accent-foreground: oklch(0.511 0.127 150.427);
    --destructive: oklch(0.583 0.153 36.864);
    --destructive-foreground: oklch(0.583 0.153 36.864);
    --border: oklch(0.889 0.012 162.388);
    --input: oklch(0.889 0.012 162.388);
    --ring: oklch(0.511 0.127 150.427);
    --chart-1: oklch(0.511 0.127 150.427);
    --chart-2: oklch(0.583 0.153 36.864);
    --chart-3: oklch(0.475 0.037 160.102);
    --chart-4: oklch(0.942 0.019 158.101);
    --chart-5: oklch(0.645 0.036 164.611);
    --radius: 0.625rem;
    --sidebar: oklch(0.973 0.004 158.314);
    --sidebar-foreground: oklch(0.210 0.019 158.038);
    --sidebar-primary: oklch(0.511 0.127 150.427);
    --sidebar-primary-foreground: oklch(1.000 0 0);
    --sidebar-accent: oklch(0.942 0.019 158.101);
    --sidebar-accent-foreground: oklch(0.511 0.127 150.427);
    --sidebar-border: oklch(0.889 0.012 162.388);
    --sidebar-ring: oklch(0.511 0.127 150.427);
}
```

- [ ] **Step 2: Visual check — open app in light mode**

Run: `open http://localhost:8000` (or dev server URL)
Expected: Background should be a very subtle green-white tint, buttons should be green, destructive badges terracotta.

- [ ] **Step 3: Commit**

```bash
git add resources/css/app.css
git commit -m "feat(theme): replace light theme with Pista Verde palette"
```

---

## Task 2: Update Dark Theme CSS Variables

**Files:**
- Modify: `resources/css/app.css:100-133`

- [ ] **Step 1: Replace `.dark` block with Pista Verde dark values**

Replace lines 100–133 in `resources/css/app.css` with:

```css
.dark {
    --background: oklch(0.183 0.019 164.288);
    --foreground: oklch(0.960 0.007 160.780);
    --card: oklch(0.230 0.022 158.594);
    --card-foreground: oklch(0.960 0.007 160.780);
    --popover: oklch(0.230 0.022 158.594);
    --popover-foreground: oklch(0.960 0.007 160.780);
    --primary: oklch(0.648 0.160 148.517);
    --primary-foreground: oklch(0.183 0.019 164.288);
    --secondary: oklch(0.269 0.024 159.255);
    --secondary-foreground: oklch(0.960 0.007 160.780);
    --muted: oklch(0.269 0.024 159.255);
    --muted-foreground: oklch(0.551 0.044 158.285);
    --accent: oklch(0.317 0.055 152.284);
    --accent-foreground: oklch(0.648 0.160 148.517);
    --destructive: oklch(0.627 0.155 37.025);
    --destructive-foreground: oklch(0.627 0.155 37.025);
    --border: oklch(0.340 0.032 156.578);
    --input: oklch(0.340 0.032 156.578);
    --ring: oklch(0.648 0.160 148.517);
    --chart-1: oklch(0.648 0.160 148.517);
    --chart-2: oklch(0.627 0.155 37.025);
    --chart-3: oklch(0.700 0.036 162.885);
    --chart-4: oklch(0.317 0.055 152.284);
    --chart-5: oklch(0.551 0.044 158.285);
    --sidebar: oklch(0.230 0.022 158.594);
    --sidebar-foreground: oklch(0.960 0.007 160.780);
    --sidebar-primary: oklch(0.648 0.160 148.517);
    --sidebar-primary-foreground: oklch(0.183 0.019 164.288);
    --sidebar-accent: oklch(0.317 0.055 152.284);
    --sidebar-accent-foreground: oklch(0.648 0.160 148.517);
    --sidebar-border: oklch(0.340 0.032 156.578);
    --sidebar-ring: oklch(0.648 0.160 148.517);
}
```

- [ ] **Step 2: Visual check — toggle to dark mode**

Expected: Deep green-tinted dark background, green primary buttons, terracotta destructive, green-tinted card surfaces.

- [ ] **Step 3: Commit**

```bash
git add resources/css/app.css
git commit -m "feat(theme): replace dark theme with Pista Verde palette"
```

---

## Task 3: Update Anti-Flash Inline Styles

**Files:**
- Modify: `resources/views/app.blade.php:24-30`

- [ ] **Step 1: Update the inline `<style>` block to match new background colors**

Replace the inline style block (lines 23–31) with:

```html
<style>
    html {
        background-color: oklch(0.973 0.004 158.314);
    }

    html.dark {
        background-color: oklch(0.183 0.019 164.288);
    }
</style>
```

This prevents a flash of the old white/black background before CSS loads.

- [ ] **Step 2: Verify — hard refresh the page in both themes**

Expected: No white/black flash on initial page load in either theme.

- [ ] **Step 3: Commit**

```bash
git add resources/views/app.blade.php
git commit -m "feat(theme): update anti-flash inline styles with Pista Verde bg"
```

---

## Task 4: Update Inertia Progress Bar Color

**Files:**
- Modify: `resources/js/app.tsx:27`

- [ ] **Step 1: Change the progress bar color to primary green**

Find the line with `color: '#4B5563'` and replace with:

```typescript
color: '#1B7A3D',
```

This sets the Inertia navigation progress bar to the primary green.

- [ ] **Step 2: Verify — navigate between pages and check the progress bar color**

Expected: Green progress bar appears at the top during page transitions.

- [ ] **Step 3: Commit**

```bash
git add resources/js/app.tsx
git commit -m "feat(theme): update Inertia progress bar to Pista Verde green"
```

---

## Task 5: Convert Hardcoded Color Classes to Semantic Tokens

**Files:**
- Modify: `resources/js/components/appearance-tabs.tsx`
- Modify: `resources/js/components/user-info.tsx`
- Modify: `resources/js/components/nav-footer.tsx`
- Modify: `resources/js/components/app-header.tsx`
- Modify: `resources/js/layouts/auth/auth-split-layout.tsx`

These files use hardcoded `neutral-*`/`zinc-*`/`text-black`/`text-white` classes that bypass the CSS variable theme system. Replace them with semantic shadcn tokens so they adopt the Pista Verde green tint.

- [ ] **Step 1: Update `appearance-tabs.tsx`**

Replace the hardcoded color classes:
- `bg-neutral-100` → `bg-muted`
- `dark:bg-neutral-800` → `dark:bg-muted`
- `bg-white` (active tab) → keep as-is (it's `--card` equivalent in this context, but `bg-white` is standard for the selected segment)
- `dark:bg-neutral-700` → `dark:bg-accent`
- `dark:text-neutral-100` → `dark:text-accent-foreground`
- `text-neutral-500` → `text-muted-foreground`
- `hover:bg-neutral-200/60` → `hover:bg-accent/60`
- `hover:text-black` → `hover:text-foreground`
- `dark:text-neutral-400` → `dark:text-muted-foreground`
- `dark:hover:bg-neutral-700/60` → `dark:hover:bg-accent/60`

- [ ] **Step 2: Update `user-info.tsx`**

In the `AvatarFallback` className:
- `bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white`
→ `bg-muted text-foreground dark:bg-accent dark:text-accent-foreground`

- [ ] **Step 3: Update `nav-footer.tsx`**

In the `SidebarMenuButton` className:
- `text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100`
→ `text-muted-foreground hover:text-foreground dark:text-muted-foreground dark:hover:text-foreground`

Since `text-muted-foreground` already adapts via the dark CSS variable, this simplifies to:
`text-muted-foreground hover:text-foreground`

- [ ] **Step 4: Update `app-header.tsx`**

Multiple locations:
- `activeItemStyles` constant: `text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100` → `text-foreground dark:bg-accent dark:text-accent-foreground`
- Logo icon in mobile sheet: `text-black dark:text-white` → `text-foreground`
- Active underline: `bg-black dark:bg-white` → `bg-foreground dark:bg-foreground` (or just `bg-foreground`)
- Avatar fallback: `bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white` → `bg-muted text-foreground dark:bg-accent dark:text-accent-foreground`
- Breadcrumbs wrapper: `text-neutral-500` → `text-muted-foreground`

- [ ] **Step 5: Update `auth-split-layout.tsx`**

- Hero panel: `bg-zinc-900` → `bg-primary` (shows the green on the auth split panel)
- Logo (mobile): `text-black` → `text-foreground`

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/appearance-tabs.tsx resources/js/components/user-info.tsx resources/js/components/nav-footer.tsx resources/js/components/app-header.tsx resources/js/layouts/auth/auth-split-layout.tsx
git commit -m "refactor(theme): replace hardcoded neutral colors with semantic tokens"
```

---

## Task 6: Smoke Test All Themes

- [ ] **Step 1: Test light theme end-to-end**

Navigate through these pages and verify colors are correct:
1. Login page — green primary button, green-tinted background
2. Students Index — green "Attivo" badges, terracotta "Sospeso" badges
3. Student Create form — green submit button, correct input borders
4. Settings page — correct sidebar colors (desktop), correct bottom nav (mobile)

- [ ] **Step 2: Test dark theme end-to-end**

Toggle to dark mode and verify the same pages:
1. Deep green-tinted backgrounds (not pure black)
2. Green primary elements on dark surfaces
3. Terracotta destructive elements
4. Correct contrast ratios for text readability

- [ ] **Step 3: Test system preference**

1. Set appearance to "system"
2. Toggle OS dark mode
3. Verify theme switches correctly without flash

- [ ] **Step 4: Final commit (if any adjustments were needed)**

```bash
git add -A
git commit -m "fix(theme): adjustments from smoke testing"
```
