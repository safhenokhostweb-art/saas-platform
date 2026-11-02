{{-- ADR-076 — the Canvas page-tab strip: styles and behaviour (window.TbTabStrip)
     for both editors. Include once per editor page; it mounts every
     [data-tb-tabstrip] on the page by itself.

     Host markup:
       <div class="tbts" data-tb-tabstrip>
           @include('theme-runtime::canvas.tab-strip-nav', ['dir' => 'start'])
           <div class="tbts-viewport" data-tbts-viewport role="group" aria-label="…">
               …one element per tab, data-canvas-page-tab="<key>", the active one aria-current="page"…
           </div>
           @include('theme-runtime::canvas.tab-strip-nav', ['dir' => 'end'])
       </div>
     The chevrons are mouse affordances (keyboard focus already scrolls a
     tab into view), hence tabindex="-1".
     Edit ../../js/canvas-tab-strip.js, never a copy. --}}
<style>
/* Optional host wrapper: fixed controls + the strip, sharing one toolbar row. */
.tbts-group { display: flex; align-items: center; gap: .375rem; flex: 1 1 22rem; min-width: 0; }
.tbts { position: relative; display: flex; align-items: center; flex: 1 1 auto; min-width: 0; }
.tbts-viewport { display: flex; align-items: center; gap: .375rem; flex: 1 1 auto; min-width: 0; overflow-x: auto; overflow-y: hidden; overscroll-behavior-x: contain; scrollbar-width: none; padding-block: 2px; }
.tbts-viewport::-webkit-scrollbar { display: none; }
.tbts-viewport > * { flex: none; }
.tbts-nav { position: absolute; inset-block: 0; z-index: 2; display: inline-flex; align-items: center; width: 2.25rem; padding: 0; border: 0; color: #475569; cursor: pointer; }
.tbts-nav[hidden] { display: none; }
.tbts-nav > span { display: inline-flex; align-items: center; justify-content: center; width: 1.5rem; height: 1.5rem; border: 1px solid #e2e8f0; border-radius: 999px; background: #fff; box-shadow: 0 1px 3px rgba(15, 23, 42, .12); transition: color .15s, border-color .15s; }
.tbts-nav:hover > span, .tbts-nav:focus-visible > span { color: #4f46e5; border-color: #a5b4fc; }
.tbts-nav:focus-visible { outline: none; }
.tbts-nav svg { width: .875rem; height: .875rem; }
/* RTL (both editors): the start edge is on the right. */
.tbts-nav--start { inset-inline-start: 0; justify-content: flex-start; background: linear-gradient(to left, #fff 45%, rgba(255, 255, 255, 0)); }
.tbts-nav--end { inset-inline-end: 0; justify-content: flex-end; background: linear-gradient(to right, #fff 45%, rgba(255, 255, 255, 0)); }
.tbts-nav--end svg { transform: scaleX(-1); }
[dir="ltr"] .tbts-nav--start { background: linear-gradient(to right, #fff 45%, rgba(255, 255, 255, 0)); }
[dir="ltr"] .tbts-nav--end { background: linear-gradient(to left, #fff 45%, rgba(255, 255, 255, 0)); }
[dir="ltr"] .tbts-nav--start svg { transform: scaleX(-1); }
[dir="ltr"] .tbts-nav--end svg { transform: none; }
@media (prefers-reduced-motion: reduce) { .tbts-nav > span { transition: none; } }
</style>
<script>
{!! file_get_contents(\SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider::canvasTabStripPath()) !!}
</script>
