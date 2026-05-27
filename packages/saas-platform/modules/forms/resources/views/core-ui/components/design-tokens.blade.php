{{--
  Core-UI Design Tokens — Phase 9.2
  Inject this component once per layout: <x-core-ui::design-tokens />
  Defines CSS custom properties for the entire Forms module UI.
  All token values are canonical — do NOT override per-view.
--}}
<style>
:root {
  /* ─── Spacing ─────────────────────────────────────── */
  --forms-space-xs:  0.25rem;
  --forms-space-sm:  0.5rem;
  --forms-space-md:  1rem;
  --forms-space-lg:  1.5rem;
  --forms-space-xl:  2rem;
  --forms-space-2xl: 3rem;

  /* ─── Border Radius ───────────────────────────────── */
  --forms-radius-sm:   0.5rem;
  --forms-radius-md:   0.75rem;
  --forms-radius-lg:   1rem;
  --forms-radius-xl:   1.25rem;
  --forms-radius-2xl:  1.5rem;
  --forms-radius-full: 9999px;

  /* ─── Shadows ─────────────────────────────────────── */
  --forms-shadow-sm:     0 1px 3px 0 rgb(0 0 0 / 0.07);
  --forms-shadow-md:     0 4px 12px -2px rgb(0 0 0 / 0.10);
  --forms-shadow-lg:     0 10px 30px -5px rgb(0 0 0 / 0.13);
  --forms-shadow-focus:  0 0 0 3px rgb(99 102 241 / 0.25);
  --forms-shadow-indigo: 0 4px 12px -2px rgb(79 70 229 / 0.25);

  /* ─── Animation Durations ─────────────────────────── */
  --forms-anim-fast:    100ms;
  --forms-anim-normal:  150ms;
  --forms-anim-slow:    300ms;
  --forms-anim-autosave: 1500ms;

  /* ─── Icon Sizes ──────────────────────────────────── */
  --forms-icon-xs: 0.875rem;
  --forms-icon-sm: 1rem;
  --forms-icon-md: 1.25rem;
  --forms-icon-lg: 1.5rem;
  --forms-icon-xl: 2rem;

  /* ─── Brand Colors ────────────────────────────────── */
  --forms-primary:       #4f46e5;
  --forms-primary-hover: #4338ca;
  --forms-primary-light: #eef2ff;
  --forms-primary-ring:  rgb(99 102 241 / 0.25);

  /* ─── Status Colors ───────────────────────────────── */
  --forms-status-active-bg:     #d1fae5;
  --forms-status-active-text:   #065f46;
  --forms-status-active-border: #6ee7b7;

  --forms-status-inactive-bg:     #f1f5f9;
  --forms-status-inactive-text:   #475569;
  --forms-status-inactive-border: #cbd5e1;

  --forms-status-draft-bg:     #fef3c7;
  --forms-status-draft-text:   #92400e;
  --forms-status-draft-border: #fcd34d;

  --forms-status-error-bg:     #fee2e2;
  --forms-status-error-text:   #991b1b;
  --forms-status-error-border: #fca5a5;

  --forms-status-info-bg:     #eff6ff;
  --forms-status-info-text:   #1e40af;
  --forms-status-info-border: #93c5fd;

  /* ─── Typography ──────────────────────────────────── */
  --forms-font-persian: 'Vazirmatn', 'Tahoma', sans-serif;
  --forms-font-mono:    'Courier New', monospace;
  --forms-size-xs:      0.75rem;
  --forms-size-sm:      0.875rem;
  --forms-size-base:    1rem;
  --forms-size-lg:      1.125rem;
  --forms-size-xl:      1.25rem;
  --forms-weight-normal:   400;
  --forms-weight-medium:   500;
  --forms-weight-semibold: 600;
  --forms-weight-bold:     700;

  /* ─── Z-Index Scale ───────────────────────────────── */
  --forms-z-dropdown: 100;
  --forms-z-modal:    200;
  --forms-z-toast:    300;
  --forms-z-tooltip:  400;
}
</style>
