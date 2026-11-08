{{-- ADR-082 — the Inspector's DOM-free control model (window.CanvasControls):
     tabs, conditions, per-device values, Normal/Hover/Active states and the
     range / spacing / typography / shadow value shapes, plus the styles of
     the shared controls (theme-runtime::canvas.control-*). Shared by both
     hosts' Canvas editors and the Node tests. Edit ../../js/canvas-controls.js,
     never a copy. The styles are self-contained (.tbc-*) because the hosts'
     Tailwind builds do not scan this package. --}}
<style>
.tbc { display: block; }
.tbc-row { display: flex; align-items: center; gap: .375rem; }
.tbc-grow { flex: 1 1 auto; min-width: 0; }
.tbc-input, .tbc-select { width: 100%; min-width: 0; padding: .375rem .5rem; border: 1px solid #e2e8f0; border-radius: .5rem; background: #fff; color: #1e293b; font: inherit; font-size: .75rem; line-height: 1.25rem; }
.tbc-input:focus, .tbc-select:focus { outline: none; border-color: #818cf8; box-shadow: 0 0 0 2px #c7d2fe; }
.tbc-input--num { width: 4.25rem; flex: none; text-align: center; font-variant-numeric: tabular-nums; }
.tbc-input.is-inherited, .tbc-select.is-inherited { color: #94a3b8; background: #f8fafc; }
.tbc-unit { flex: none; width: auto; padding-inline: .35rem; }
.tbc-range { flex: 1 1 auto; min-width: 0; accent-color: #4f46e5; }
.tbc-range:focus { outline: none; }
.tbc-range:focus-visible { outline: 2px solid #a5b4fc; outline-offset: 2px; border-radius: 999px; }
.tbc-hint { margin-top: .25rem; font-size: .6875rem; color: #94a3b8; }
.tbc-sub { display: block; margin-bottom: .25rem; font-size: .6875rem; color: #64748b; }
.tbc-btn { display: inline-flex; align-items: center; justify-content: center; gap: .25rem; padding: .25rem .5rem; border: 1px solid #e2e8f0; border-radius: .5rem; background: #fff; color: #475569; font: inherit; font-size: .6875rem; cursor: pointer; transition: background-color .15s, border-color .15s, color .15s; }
.tbc-btn:hover { background: #f8fafc; color: #1e293b; }
.tbc-btn:focus-visible, .tbc-dev:focus-visible, .tbc-state:focus-visible { outline: none; box-shadow: 0 0 0 2px #a5b4fc; }
.tbc-btn[aria-pressed="true"] { border-color: #818cf8; background: #eef2ff; color: #4338ca; font-weight: 600; }
.tbc-reset { border: 0; background: transparent; color: #f43f5e; font: inherit; font-size: .6875rem; cursor: pointer; padding: 0 .125rem; }
.tbc-reset:hover { text-decoration: underline; }
/* Device switch beside a responsive field's label */
.tbc-devices { display: inline-flex; gap: 1px; padding: 1px; border-radius: .375rem; background: #f1f5f9; vertical-align: middle; margin-inline-start: .375rem; }
.tbc-dev { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 1.375rem; height: 1.25rem; border: 0; border-radius: .3rem; background: transparent; color: #94a3b8; cursor: pointer; }
.tbc-dev svg { width: .75rem; height: .75rem; }
.tbc-dev[aria-pressed="true"] { background: #fff; color: #4f46e5; box-shadow: 0 1px 2px rgb(15 23 42 / 12%); }
.tbc-dev-dot { position: absolute; inset-block-start: 1px; inset-inline-end: 1px; width: .3rem; height: .3rem; border-radius: 999px; background: #f59e0b; }
/* Normal / Hover / Active */
.tbc-states { display: flex; gap: .125rem; margin-bottom: .5rem; padding: .1875rem; border-radius: .5rem; background: #f1f5f9; }
.tbc-state { flex: 1 1 0; padding: .3rem .25rem; border: 0; border-radius: .375rem; background: transparent; color: #64748b; font: inherit; font-size: .6875rem; cursor: pointer; }
.tbc-state[aria-selected="true"] { background: #fff; color: #4338ca; font-weight: 700; box-shadow: 0 1px 2px rgb(15 23 42 / 12%); }
/* Spacing */
.tbc-sides { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)) auto; gap: .25rem; align-items: end; }
.tbc-side { display: flex; flex-direction: column; gap: .125rem; min-width: 0; }
.tbc-side span { font-size: .625rem; color: #94a3b8; text-align: center; }
.tbc-side .tbc-input { padding-inline: .25rem; text-align: center; }
.tbc-link { width: 1.75rem; height: 1.875rem; padding: 0; }
.tbc-link svg { width: .875rem; height: .875rem; }
/* Typography / shadow panels */
.tbc-panel { margin-top: .375rem; padding: .625rem; border: 1px solid #e2e8f0; border-radius: .625rem; background: #f8fafc; display: grid; gap: .5rem; }
.tbc-pair { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
.tbc-summary { flex: 1 1 auto; min-width: 0; justify-content: space-between; padding: .4rem .6rem; font-size: .75rem; color: #334155; }
.tbc-presets { display: flex; flex-wrap: wrap; gap: .25rem; }
.tbc-swatch { flex: none; width: 2.25rem; height: 1.875rem; border-radius: .5rem; background: #fff; border: 1px solid #e2e8f0; }
.tbc-color { flex: none; width: 2.25rem; height: 1.875rem; padding: .125rem; border: 1px solid #e2e8f0; border-radius: .5rem; background: #fff; cursor: pointer; }
.tbc-check { display: inline-flex; align-items: center; gap: .375rem; font-size: .6875rem; color: #475569; }
.tbc-notice { margin: .75rem 1rem; padding: .625rem .75rem; border: 1px solid #fde68a; border-radius: .625rem; background: #fffbeb; color: #92400e; font-size: .6875rem; line-height: 1.6; }
</style>
<script>
{!! file_get_contents(\SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider::canvasControlsPath()) !!}
</script>
