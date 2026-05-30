# Forms Builder Runtime Debug Report — Phase 10.2.7

**Date:** 2026-05-28  
**Phase:** 10.2.7 — Builder Runtime Perfection & State Synchronization  
**Status:** ALL BUGS FIXED ✓

---

## 1. Crash on Open — `Undefined constant "historyIndex"`

### Root Cause (EXACT)

**File:** `packages/saas-platform/modules/forms/resources/views/admin/forms/builder.blade.php`

**Lines 123–126 (before fix):**
```blade
<x-core-ui::button variant="ghost" size="sm" :icon="true" @click="undo"
    :disabled="historyIndex <= 0"
    ...>
<x-core-ui::button variant="ghost" size="sm" :icon="true" @click="redo"
    :disabled="historyIndex >= history.length - 1"
    ...>
```

**Line 91 (before fix):**
```blade
<x-core-ui::button
    :class="form.is_active ? 'bg-emerald-100...' : 'bg-slate-100...'"
    ...>
```

### Why This Crashes

In Laravel Blade, the `:attr="expr"` syntax on a **Blade component** (`<x-component-name>`) is PHP-evaluated at render time. The expression is passed to the component's `@props` array as a PHP value.

`button.blade.php` declares `'disabled' => false` in `@props()`. When Blade compiles `:disabled="historyIndex <= 0"`, it generates PHP like:

```php
$__component->withProps(['disabled' => historyIndex <= 0, ...]);
```

PHP evaluates `historyIndex` as an undefined constant. In **PHP 8.0+**, this throws:
```
Fatal error: Uncaught Error: Undefined constant "historyIndex"
```

This crashes the entire page. The builder never renders.

### Why It Did Not Previously Crash `:class`

`:class` on a Blade component is not in `@props()` — it goes through `$attributes` which handles it differently. However, it still causes PHP 8 fatal errors when the expression contains undefined constants (like `form.is_active`).

### Fix Applied

Changed from Blade PHP-evaluated `:attr` to Alpine-native `x-bind:attr` which **passes through `$attributes` without PHP evaluation**:

```blade
{{-- BEFORE (crashes PHP 8): --}}
:disabled="historyIndex <= 0"

{{-- AFTER (Alpine handles reactively): --}}
x-bind:disabled="historyIndex <= 0"
x-bind:class="{'opacity-50 cursor-not-allowed pointer-events-none': historyIndex <= 0}"
```

Alpine's `x-bind:attr` on a Blade component:
1. Is **not** intercepted by `@props()` (attribute name is `x-bind:disabled`, not `disabled`)
2. Passes through `{{ $attributes->class([$cls]) }}` unchanged
3. Appears on the rendered `<button>` element
4. Alpine picks it up and handles reactivity in the browser

**Files Changed:**
- `builder.blade.php` lines 91, 123–126

---

## 2. Drag & Drop Instability

### Root Cause 1: No AbortController in `persistSort()`

**Before fix:**
```js
async persistSort() {
  const order = this.fields.map(f => f.id);
  await fetch(route, { method: 'POST', body: JSON.stringify({ order }) });
  // No error handling, no cancellation
}
```

**Problem:** Rapid drags fire multiple concurrent `persistSort()` calls. If request N completes after request N+1, the server stores stale order from N — overwriting the correct order from N+1.

**Fix:**
```js
async persistSort() {
  if (this._persistController) { this._persistController.abort(); }
  this._persistController = new AbortController();
  const order = this.fields.map(f => f.id);
  try {
    await fetch(route, { signal: this._persistController.signal, ... });
    this._persistController = null;
  } catch (e) {
    if (e.name !== 'AbortError') { this.showToast('خطا در ذخیره ترتیب', 'error'); }
  }
}
```

Added `_persistController: null` to the data initializer.

### Root Cause 2: Sortable DOM References Stale After Drag

**Problem:** After a drag, `updateFieldOrderFromDom()` updates `this.fields`. Alpine's reactivity system schedules a DOM reconciliation. The SortableJS instance still holds references to the pre-reconciliation DOM elements. Subsequent drags may fire on stale nodes.

**Fix:** In `onEnd`, call `$nextTick(() => this.initSortable())` after `updateFieldOrderFromDom()`. `initSortable()` destroys and recreates the Sortable instance on the freshly-reconciled DOM.

```js
onEnd: (evt) => {
  if (evt.oldIndex === undefined || evt.newIndex === undefined) return;
  this.updateFieldOrderFromDom();
  this.persistSort();
  this.pushHistory();
  this.$nextTick(() => this.initSortable()); // ← ADDED
},
```

---

## 3. Field Property State Regression

### Root Cause: `saveCurrentField()` Replaced Entire Field Object

**Before fix:**
```js
const idx = this.fields.findIndex(f => f.id === field.id);
if (idx >= 0) this.fields[idx] = json.field; // ← DANGEROUS
```

**Failure scenario:**
1. User checks `is_required` → `true`
2. `saveCurrentField()` fires → sends `is_required: true`
3. Before response arrives, user unchecks → `is_required: false`  
4. Server responds with `is_required: true` (from step 2's request)
5. `this.fields[idx] = json.field` OVERWRITES local state back to `true` ← WRONG

The user sees the toggle snap back to its old state after save. They cannot trust the toggle.

**Fix:**
```js
const idx = this.fields.findIndex(f => f.id === field.id);
if (idx >= 0 && json.field) {
  // Only sync server-generated fields; preserve all user-editable properties
  if (json.field.sort_order !== undefined) this.fields[idx].sort_order = json.field.sort_order;
  if (json.field.updated_at !== undefined) this.fields[idx].updated_at = json.field.updated_at;
}
```

Local state (what the user typed/toggled) remains the source of truth. Server response only updates metadata.

---

## 4. Alpine Lifecycle Trace (Verified Correct)

```
page load
  → window.__formBuilderInitialData injected by Blade
  → Alpine processes x-data="formBuilder(window.__formBuilderInitialData)"
  → Alpine.data('formBuilder', factory) runs in alpine:init
  → init() called
    → $nextTick → initSortable()
    → pushHistory() (initial snapshot)
    → checkDraftRecovery() (shows banner if localStorage draft found)
    → keydown listener registered (ONCE)
    → beforeunload listener registered (ONCE)

field add
  → addField() → POST → fields.push(json.field) → selectField()
  → $nextTick → initSortable() (re-attach to new DOM element)
  → pushHistory()

drag
  → SortableJS moves DOM elements
  → onEnd fires
  → updateFieldOrderFromDom() → this.fields = [reordered...]
  → persistSort() (with AbortController)
  → pushHistory()
  → $nextTick → initSortable() (re-attach after Alpine x-for reconciliation)

field edit
  → x-model mutates selectedField.propName (reference into fields[])
  → @change → debouncedFieldSave() → clearTimeout → new timer (800ms)
  → saveCurrentField() → AbortController → PUT → sync only sort_order/updated_at
  → saveStatus = 'saved'

undo
  → historyIndex-- → this.fields = JSON.parse(JSON.stringify(history[historyIndex]))
  → Alpine re-renders x-for with restored state

crash recovery
  → every pushHistory() → _crashSaveTimer (500ms) → saveDraftToStorage()
  → on next open → checkDraftRecovery() → hasDraft banner
  → user clicks "بازیابی پیش‌نویس" → restoreDraft() → fields restored
```

---

## 5. SortableJS Lifecycle Trace (Verified Correct)

```
initSortable():
  1. Find #fields-canvas DOM node
  2. Guard: typeof Sortable === 'undefined' → return (CDN fail)
  3. Guard: if (this.sortableInstance) → this.sortableInstance.destroy()
  4. new Sortable(canvas, { animation, handle, ghostClass, dragClass, filter, onEnd })
  5. sortableInstance assigned

Called at:
  - init() → $nextTick (initial setup)
  - addField() → $nextTick (after DOM updates with new field)
  - duplicateField() → $nextTick (after DOM updates)
  - onEnd() → $nextTick (after Alpine x-for reconciliation post-drag) [PHASE 10.2.7 FIX]
```

---

## 6. Modal Stack Lifecycle Trace (Verified Correct)

```
showTemplateModal:        opened by clicking "از قالب شروع کنید"
                          closed by: click backdrop, Escape, @keydown.escape.window
showDeleteConfirm:        opened by requestDeleteField(id)
                          closed by: cancel button, Escape, backdrop
showTemplateApplyConfirm: opened by requestApplyTemplate(template)
                          closed by: cancel button, Escape, backdrop
showKeyboardHelp:         opened by "?" button / "?" keypress
                          closed by: button, Escape, backdrop

Global Escape handler (keydown):
  showKeyboardHelp = false
  showTemplateModal = false
  showTemplateApplyConfirm = false
  pendingTemplateData = null
  (showDeleteConfirm via @keydown.escape.window on modal element)
```

---

## 7. Known Architecture Exceptions

### Alpine Templates (x-for) Cannot Use Blade Components

Inside `<template x-for="field in fields">`, the DOM is rendered by Alpine's JavaScript runtime, not by PHP/Blade. Blade component directives (`<x-core-ui::button>`) are server-rendered and cannot appear inside client-rendered Alpine templates.

**This is a documented architectural exception in Phase 10.2 and remains valid in Phase 10.2.7.**

All controls inside `x-for` templates are raw HTML elements (`<button>`, `<input>`, etc.) processed by Alpine. All controls OUTSIDE `x-for` templates use `x-core-ui::*` components.

---

## 8. Remaining Risks

| Risk | Severity | Status |
|------|----------|--------|
| CDN SortableJS failure | Medium | Mitigated: `typeof Sortable === 'undefined'` guard |
| Network partition during autosave | Low | Mitigated: `saveStatus = 'unsaved'` persists; retry on next edit |
| localStorage quota exceeded (draft save) | Very Low | Mitigated: try/catch in all localStorage calls |
| PHP 8.0 Alpine/Blade collision | CRITICAL | FIXED: all `:attr` on Blade components → `x-bind:attr` |
| Concurrent field saves | Medium | FIXED: AbortController + optimistic merge strategy |
| Stale Sortable after drag | Medium | FIXED: `$nextTick → initSortable()` in onEnd |
