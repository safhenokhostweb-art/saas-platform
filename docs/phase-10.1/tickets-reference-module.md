# Tickets Module — Normalization Report — Phase 10.1

## COMPLIANCE STATUS (Post Phase 10.1)

| Area | Pre-10.1 | Post-10.1 | Target |
|------|----------|-----------|--------|
| Design tokens | 45/100 | 72/100 | 90/100 |
| Core-ui components | 30/100 | 65/100 | 90/100 |
| Inline styles | CRITICAL | HIGH | PASS |
| Modal normalization | 20/100 | 55/100 | 85/100 |
| Form controls | 25/100 | 60/100 | 90/100 |
| Responsive | 50/100 | 68/100 | 85/100 |

---

## CRITICAL ISSUES FOUND

### 1. Inline `<style>` Blocks
**File:** `admin/tickets/index.blade.php` and `show.blade.php`

Both views contained 100+ line inline `<style>` blocks with:
- Hardcoded `#374151` colors
- Hardcoded `font-size: 13px`
- Hardcoded `box-shadow: 0 2px 8px rgba(0,0,0,.08)`
- Custom `@keyframes` without reduced-motion support

**Phase 10.0 action:** Keyframes moved to `design-tokens.css`, inline style blocks partially removed.
**Phase 10.1 target:** Zero inline style blocks.

### 2. Raw Form Controls in Settings
**File:** `admin/tickets/settings.blade.php`

All form inputs are raw HTML `<input>` elements without core-ui wrappers.

**Migration required:**
```blade
{{-- BEFORE --}}
<input type="text" name="sla_hours" class="border rounded px-3 py-2">

{{-- AFTER --}}
<x-core-ui::input name="sla_hours" label="ساعت SLA" type="number" />
```

### 3. Status Badges Hardcoded
Status/priority badges use hardcoded Tailwind color classes:
```blade
{{-- BEFORE --}}
<span class="bg-green-100 text-green-700 px-2 py-1 rounded-full text-xs">فعال</span>

{{-- AFTER --}}
<x-core-ui::badge variant="success" dot>فعال</x-core-ui::badge>
```

---

## MIGRATION CHECKLIST (Phase 10.2)

- [ ] Remove ALL remaining inline `<style>` blocks
- [ ] Replace raw `<input>` in settings.blade.php → `x-core-ui::input`
- [ ] Replace raw `<textarea>` in show.blade.php → `x-core-ui::textarea`
- [ ] Replace hardcoded status badges → `x-core-ui::badge`
- [ ] Replace hardcoded priority badges → `x-core-ui::badge`
- [ ] Replace status/priority dropdowns → `x-core-ui::dropdown`
- [ ] Add `x-core-ui::data-table` to index.blade.php
- [ ] Add `x-core-ui::confirm-modal` for ticket deletion
- [ ] Add mobile-responsive layout to index.blade.php
- [ ] Migrate activity timeline to canonical `x-core-ui::activity-timeline`
- [ ] Wire `x-core-ui::toast` for all action feedback

---

## TICKETS-SPECIFIC COMPONENTS TO PRESERVE

These tickets-specific components are NOT subject to canonical migration (module-specific):
- `components/status-badge.blade.php` — Can stay as thin wrapper around `x-core-ui::badge`
- `components/priority-badge.blade.php` — Can stay as thin wrapper around `x-core-ui::badge`
- `components/reply-item.blade.php` — Module-specific, no canonical equivalent
- `_partials/enterprise-desk-panel.blade.php` — Module-specific AI panel, no canonical equivalent
