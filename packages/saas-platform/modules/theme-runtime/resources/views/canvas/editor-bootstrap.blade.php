{{-- Phase 6.1-B — the ONE include every host Canvas editor page needs.
     Hosts previously had to remember a fragile include ORDER:

         theme-runtime::canvas.runtime        (protocol bridge, required first)
         theme-runtime::canvas.identity       (identity contract, required second)
         <host's own structural services>     (hard-requires the identity contract)
         <host's own interaction runtime>     (before the structural services on
                                               the platform's live preview)

     canvas-live-preview.blade.php shipped without the identity include once
     and threw 'CanvasIdentity contract is required' at runtime. This partial
     makes the correct order un-forgettable: hosts include this one view and
     cannot get the sequence wrong. The interaction runtime is loaded here
     too (before the structural services) so the whole bridge is one line;
     @includeIf keeps hosts without interaction widgets working unchanged.

     Contract (do not reorder without bumping both):
       1. runtime  — version-gated ThemeCanvasRuntime protocol bridge.
       2. identity — version-gated CanvasIdentity contract; nothing below
                     it may run without window.CanvasIdentity.version === 1.
       3. host structural services partial (structural services hard-depend
          on the identity contract — fail-fast instead of a null deref).
       4. interaction runtime (interaction widgets render as sanitized
          data-tb-interaction intention attributes; this is the listener).

     $canvasStructuralServices: Blade view of the host's structural-services
     partial. Defaults to the platform's own partial; customer-site passes
     its own view name. Required on every editor page: identity + structure
     are a pair, and silently dropping the services half would let an editor
     type a tree the services were built to reject. Pass [] only for pure
     preview surfaces that render, never author.
--}}

@include('theme-runtime::canvas.runtime')
@include('theme-runtime::canvas.identity')

<script>
(function () {
    'use strict';

    // Fail-fast version gate — a missing or mismatched contract must never
    // be silently ignored (silent drift is how "works on my machine" editor
    // bugs ship): platform and customer MUST run the same contract.
    var v = window.CanvasIdentity && window.CanvasIdentity.version;
    if (v !== 1) {
        throw 'CanvasIdentity contract is required (found ' +
            (v === undefined ? 'no version' : 'version ' + v) +
            ' — refresh the theme-runtime package)';
    }
    if (!window.ThemeCanvasRuntime || window.ThemeCanvasRuntime.version !== 1) {
        throw 'ThemeCanvasRuntime contract is required before structural services';
    }
})();
</script>

@foreach($canvasStructuralServices ?? ['admin.theme-builder.partials.canvas-structural-services'] as $__canvasServicePartial)
@include($__canvasServicePartial)
@endforeach

@includeIf('theme-runtime::interaction.runtime')
