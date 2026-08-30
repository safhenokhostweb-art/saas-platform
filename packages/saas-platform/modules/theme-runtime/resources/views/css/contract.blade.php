{{-- Phase 2h (ADR-029) — the shared Theme runtime CSS contract, included
     verbatim by both host layouts. Do not copy this file's rules
     elsewhere; edit the source at ../../css/theme-runtime-contract.css.
     Path resolved via the service provider, not __DIR__ here — Blade
     compiles this file into storage/framework/views/, where __DIR__ would
     resolve to the wrong (cache) directory. --}}
{!! file_get_contents(\SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider::cssContractPath()) !!}
