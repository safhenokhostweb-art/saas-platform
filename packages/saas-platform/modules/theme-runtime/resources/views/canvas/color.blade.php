{{-- Phase 0.5 (ADR-064) — the Canvas colour control's DOM-free value model
     (window.CanvasColor), shared by both hosts' Canvas editors and the Node
     tests. Edit ../../js/canvas-color.js, never a copy. --}}
<script>
{!! file_get_contents(\SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider::canvasColorPath()) !!}
</script>
