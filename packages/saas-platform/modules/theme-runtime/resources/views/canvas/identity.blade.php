{{-- Shared, DOM-free Canvas identity/traversal contract. The source remains a
     plain distributable JavaScript asset so both hosts and Node tests consume
     the same implementation. --}}
<script>
{!! file_get_contents(\SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider::canvasIdentityContractPath()) !!}
</script>
