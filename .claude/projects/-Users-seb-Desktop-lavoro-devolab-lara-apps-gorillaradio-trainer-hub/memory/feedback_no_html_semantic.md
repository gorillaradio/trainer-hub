---
name: No semantic HTML tags in React app
description: Don't use dl/dt/dd or other semantic HTML tags - use div/p with Tailwind classes in this React+shadcn app
type: feedback
---

Don't use semantic HTML tags like `<dl>`, `<dt>`, `<dd>` in this React app. Use `<div>` and `<p>` with Tailwind classes instead.

**Why:** This is a React + shadcn/ui + Tailwind app, not a vanilla HTML site. Semantic HTML tags feel out of place and are not the convention in this codebase.

**How to apply:** When building display/detail views, use `<div>` and `<p>` elements styled with Tailwind. Prefer React component patterns (like the `Field` component) over HTML semantic structures.
