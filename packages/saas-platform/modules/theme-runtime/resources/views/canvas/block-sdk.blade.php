{{-- Phase 9 / Step 1 — the standardized Block SDK. Same distributable-JS
     seam as canvas/identity.blade.php: the source stays a plain JavaScript
     asset so both Blade hosts and the Node tests consume one implementation,
     and this view is a literal file_get_contents of it so the packaged file
     and the include can never drift apart.

     Contract: the SDK is a consumer of ThemeCanvasRuntime (protocol version
     1), never a second bridge. It must be included AFTER
     theme-runtime::canvas.runtime and BEFORE anything that defines a block —
     create() throws when the runtime contract is missing or mismatched,
     exactly like the editor bootstrap's fail-fast version gate. --}}
<script>
{!! file_get_contents(\SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider::blockSdkContractPath()) !!}
</script>
