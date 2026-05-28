# Canonical Frontend Rules

**Phase:** 10.2 — THE LAW  
**These rules are enforced by CI and cannot be bypassed.**

---

## THE 10 CANONICAL RULES

### Rule 1: One Component System
**ALL modules MUST render from ONE canonical component system.**

```bash
# ✓ The canonical system
x-core-ui::button
x-core-ui::input
x-core-ui::modal
x-core-ui::data-table
...

# ❌ Never create module-specific component systems
x-forms::my-button
x-tickets::my-table
```

### Rule 2: No customer-site Frontend Duplication
**customer-site MUST NEVER maintain separate frontend implementations of modules.**

The only `resources/views` content allowed in `customer-site` is:
- Tenant-facing public form views (if different from admin)
- Layouts that extend the canonical admin layout
- Pages that use only `x-core-ui::*` components

### Rule 3: Authorized Divergence Only
**UI differences are ONLY allowed through: Gates, Policies, Permissions, Feature flags, Runtime capability checks.**

```blade
{{-- ✓ Authorized divergence --}}
@can('view-advanced-analytics')
  <x-core-ui::stats-strip :advanced="true" />
@endcan

{{-- ❌ Unauthorized divergence --}}
{{-- admin shows x-core-ui::stats-strip --}}
{{-- customer-site copies paste creates custom-stats.blade.php --}}
```

### Rule 4: Design Token Propagation
**ANY design token change MUST auto-propagate to all modules, all tenants, both projects.**

- Define tokens in `design-tokens.css` only
- Use `var(--ui-*)` everywhere
- Never hardcode values in components

### Rule 5: No Raw HTML Controls
**ALL raw `<button>`, `<input>`, `<select>`, `<textarea>` MUST be replaced with `x-core-ui::*`.**

Exception: Controls inside Alpine.js `<template x-for>` blocks (JavaScript-rendered, documented with a governance comment).

### Rule 6: No Hardcoded Values
**ALL hardcoded hex colors, inline spacing, inline shadows, z-index, transitions, animation values MUST use `var(--ui-*)`.**

```blade
{{-- ❌ Forbidden --}}
<div style="color: #4F46E5; margin-top: 16px; z-index: 50">

{{-- ✓ Required --}}
<div class="text-[var(--ui-indigo)] mt-[var(--ui-space-4)] z-[var(--ui-z-dropdown)]">
```

### Rule 7: Automatic Module Inheritance
**ALL future modules automatically inherit the design system.**

No frontend work is required for a new module to get the full enterprise UI. It happens at boot time.

### Rule 8: No Separate customer-site Frontend Development
**ALL frontend development happens through core-ui, canonical tokens, and shared Blade primitives.**

If you're writing frontend code for customer-site specifically — you're doing it wrong.

### Rule 9: Professional Enterprise Code
- No temporary code
- No shortcuts
- No duplicated markup
- No inline hacks
- No "TODO: replace later" placeholders

### Rule 10: CI Is the Gatekeeper
**Violations CANNOT be merged. CI enforces rules 1–9.**

---

## Quick Reference: What to Use

| Need | Use |
|------|-----|
| Button | `<x-core-ui::button variant="primary">` |
| Link button | `<x-core-ui::button variant="outline" href="...">` |
| Icon button | `<x-core-ui::button :icon="true" aria-label="...">` |
| Text input | `<x-core-ui::input label="..." name="...">` |
| Dropdown | `<x-core-ui::select label="..." name="...">` |
| Dialog/modal | `<x-core-ui::modal name="..." title="...">` |
| Status badge | `<x-core-ui::badge variant="success">` |
| Alert | `<x-core-ui::alert variant="info">` |
| Card | `<x-core-ui::card>` |
| Data table | `<x-core-ui::data-table>` |
| Page header | `<x-core-ui::page-header title="...">` |
| Skeleton loader | `<x-core-ui::skeleton>` |
| Empty state | `<x-core-ui::empty-state title="...">` |
