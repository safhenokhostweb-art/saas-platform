<?php

namespace SaaSPlatform\ThemeRuntime\Contracts;

/**
 * Phase 2h (ADR-029) — the extension point for `module:*` block types.
 * BlockRenderer::renderBlock() looks for a callable at
 * `$context['module_renderer']` (set via setContext()) and defers to it for
 * any type prefixed `module:`; this contract documents that callable's
 * signature. A host is equally free to override renderBlock() itself for a
 * richer dispatch (see customer-site's ThemeCanvasRenderer, which resolves
 * per-module renderers — including Motion — through its own registration
 * mechanism rather than a single callable).
 *
 * The shared runtime never inspects a module's own authoring/source data —
 * it only ever receives the already-rendered HTML string a host's module
 * renderer returns.
 */
interface ModuleRendererContract
{
    /**
     * @param string $type  The full `module:...` type string.
     * @param array  $props The block's authored props.
     * @param string $id    The block's stable id (for DOM targeting).
     * @return string       Rendered HTML for this module block.
     */
    public function __invoke(string $type, array $props, string $id): string;
}
