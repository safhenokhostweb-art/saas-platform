{{-- Phase 0.6 (ADR-065) — the Canvas icon picker's DOM-free model
     (window.CanvasIcons) loaded with the theme runtime's icon catalog, plus
     the picker's own scoped styles (.tbip-*) so it looks the same in both
     hosts without depending on either host's compiled Tailwind. Include
     once per editor page, before any theme-runtime::canvas.icon-picker.
     Edit ../../js/canvas-icons.js and ../../icons/catalog.json, never a copy. --}}
<style>
.tbip { position: relative; }
.tbip-row { display: flex; align-items: center; gap: .375rem; }
.tbip-trigger { flex: 1; min-width: 0; display: flex; align-items: center; gap: .5rem; padding: .375rem .5rem; border: 1px solid #e2e8f0; border-radius: .625rem; background: #fff; color: #334155; font: inherit; font-size: .75rem; text-align: start; cursor: pointer; transition: border-color .15s, box-shadow .15s; }
.tbip-trigger:hover { border-color: #a5b4fc; }
.tbip-trigger:focus-visible, .tbip-option:focus-visible, .tbip-cat:focus-visible, .tbip-clear:focus-visible { outline: none; box-shadow: 0 0 0 2px #a5b4fc; }
.tbip-current { flex: none; width: 2rem; height: 2rem; display: inline-flex; align-items: center; justify-content: center; border-radius: .5rem; background: #eef2ff; color: #4f46e5; font-size: 1.125rem; line-height: 1; }
.tbip-current:empty::before { content: ''; width: .875rem; height: .875rem; border: 1.5px dashed #a5b4fc; border-radius: .25rem; }
.tbip-current .tb-icon-svg { width: 1.125rem; height: 1.125rem; }
.tbip-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tbip-caret { flex: none; width: .875rem; height: .875rem; color: #94a3b8; transition: transform .15s; }
.tbip-trigger[aria-expanded="true"] .tbip-caret { transform: rotate(180deg); }
.tbip-clear { flex: none; width: 1.75rem; height: 1.75rem; display: inline-flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0; border-radius: .5rem; background: #fff; color: #94a3b8; font-size: 1rem; line-height: 1; cursor: pointer; }
.tbip-clear:hover { color: #e11d48; border-color: #fecdd3; }
.tbip-panel { margin-top: .375rem; padding: .625rem; border: 1px solid #e2e8f0; border-radius: .75rem; background: #fff; box-shadow: 0 10px 30px -12px rgba(15, 23, 42, .25); }
.tbip-search { width: 100%; box-sizing: border-box; padding: .4rem .625rem; border: 1px solid #e2e8f0; border-radius: .5rem; background: #f8fafc; color: #0f172a; font: inherit; font-size: .75rem; }
.tbip-search:focus { outline: none; border-color: #818cf8; background: #fff; box-shadow: 0 0 0 2px #e0e7ff; }
.tbip-cats { display: flex; gap: .25rem; margin: .5rem 0; overflow-x: auto; scrollbar-width: thin; padding-bottom: .125rem; }
.tbip-cat { flex: none; padding: .2rem .55rem; border: 1px solid #e2e8f0; border-radius: 999px; background: #fff; color: #475569; font: inherit; font-size: .6875rem; white-space: nowrap; cursor: pointer; }
.tbip-cat[aria-pressed="true"] { background: #4f46e5; border-color: #4f46e5; color: #fff; }
.tbip-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(2.25rem, 1fr)); gap: .25rem; max-height: 13.5rem; overflow-y: auto; scrollbar-width: thin; }
.tbip-option { aspect-ratio: 1; display: flex; align-items: center; justify-content: center; border: 1px solid transparent; border-radius: .5rem; background: #f8fafc; color: #334155; cursor: pointer; transition: background .12s, color .12s; }
.tbip-option:hover { background: #eef2ff; color: #4338ca; }
.tbip-option[aria-selected="true"] { background: #4f46e5; color: #fff; }
.tbip-option .tb-icon-svg { width: 1.125rem; height: 1.125rem; }
.tbip-empty { margin: .5rem 0; text-align: center; font-size: .6875rem; color: #94a3b8; }
.tbip-custom { display: flex; align-items: center; gap: .375rem; margin-top: .5rem; padding-top: .5rem; border-top: 1px solid #f1f5f9; font-size: .6875rem; color: #64748b; }
.tbip-custom input { width: 4.5rem; padding: .25rem .4rem; border: 1px solid #e2e8f0; border-radius: .375rem; font: inherit; font-size: .8125rem; text-align: center; }
.tbip-legacy { font-size: 1.125rem; line-height: 1; }
</style>
<script>
{!! file_get_contents(\SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider::canvasIconsPath()) !!}
window.CanvasIcons.load({!! json_encode(\SaaSPlatform\ThemeRuntime\Support\IconLibrary::catalog(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!});
</script>
