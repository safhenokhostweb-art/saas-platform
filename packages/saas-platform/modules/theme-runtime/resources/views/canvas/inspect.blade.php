{{-- Inspect Mode (WU-1): window.TbInspect for both editors and the widget
     catalog. Include once in every document that renders widgets (the
     Canvas preview iframe, the catalog page) and once in the parent editor,
     which then calls TbInspect.attachHost({ frame, describe, onChange }).
     No styles here: the overlay builds its own shadow root.
     Edit ../../js/canvas-inspect.js, never a copy. --}}
<script>
{!! file_get_contents(\SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider::canvasInspectPath()) !!}
</script>
