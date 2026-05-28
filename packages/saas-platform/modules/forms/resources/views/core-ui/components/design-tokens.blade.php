{{--
  Core-UI Design Tokens — Phase 10.0
  Canonical tokens are now defined in core-utils/resources/css/design-tokens.css.
  This component emits ONLY module-specific overrides not covered by the canonical CSS file.
  Inject once per layout: <x-core-ui::design-tokens />
--}}
<style>
:root {
  /* ─── Forms-module autosave animation duration ────── */
  --forms-anim-autosave: 1500ms;

  /* ─── Forms icon sizes (aliases to canonical) ─────── */
  --forms-icon-xs: var(--ui-icon-xs);
  --forms-icon-sm: var(--ui-icon-sm);
  --forms-icon-md: var(--ui-icon-md);
  --forms-icon-lg: var(--ui-icon-lg);
  --forms-icon-xl: var(--ui-icon-xl);

  /* ─── Forms weight aliases ────────────────────────── */
  --forms-weight-normal:   var(--ui-weight-normal);
  --forms-weight-medium:   var(--ui-weight-medium);
  --forms-weight-semibold: var(--ui-weight-semibold);
  --forms-weight-bold:     var(--ui-weight-bold);

  /* ─── RTL aliases ─────────────────────────────────── */
  --forms-rtl-text-align: var(--ui-text-align);
  --forms-rtl-dir:        var(--ui-dir);
  --forms-rtl-float:      var(--ui-float);
}
</style>
