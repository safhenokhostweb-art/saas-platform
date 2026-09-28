<?php
namespace App\Services\ThemeCanvas;

use SaaSPlatform\CoreUtils\SEO\MetaData;
use App\Services\Seo\SeoService;

/**
 * Smart Public Rendering — the ONE canonical mode contract for building a
 * runtime document from Canvas-authored content.
 *
 * A Canvas theme page is consumed from two fundamentally different surfaces:
 *
 *   - MODE_EDITOR (default): the saas-platform Builder/preview. The host
 *     layout attaches the FULL editor bridge (theme-runtime::canvas.
 *     editor-bootstrap: protocol runtime + identity + structural services +
 *     interaction runtime) and the renderer emits editor-authoring
 *     attributes (data-tb-editable, canvas selection overlays).
 *
 *   - MODE_PUBLIC: a public, indexable customer page. The host layout may
 *     attach ONLY the assets requiredAssets() names — canvas.identity (the
 *     DOM-free traversal contract the JS runtime hard-requires) and
 *     interaction.runtime (the delegated interaction dispatcher) — and MUST
 *     NOT attach the editor bridge: a public page must never ship
 *     window.CanvasEditor, structural services, or the protocol bridge.
 *     SeoService::resolveForCanvasPublic() (the saas-platform host's
 *     CANVAS_PUBLIC-context resolution) resolves this surface's metadata:
 *     indexable by definition, unlike the preview contexts.
 *
 * Mode is EXPLICIT at construction, never inferred from a request, so the
 * same document builder cannot accidentally leak editor state into public
 * output (or public/SEO state into the editor) — the same fail-closed
 * discipline DocumentValidator applies to capability keys in untrusted input.
 */
class ThemeRuntimeDocument
{
    public const MODE_EDITOR = 'editor';
    public const MODE_PUBLIC = 'public';

    /** Every valid mode — the closed set callers may request. */
    public const MODES = [self::MODE_EDITOR, self::MODE_PUBLIC];

    /** Runtime capabilities a public page consumes, keyed by asset key. */
    private const PUBLIC_REQUIRED_ASSETS = [
        // theme-runtime::canvas.identity — window.CanvasIdentity (version 1):
        // DOM-free traversal contract the JS runtime requires to address
        // blocks by data-tb-block-id. Loaded FIRST, before interaction.runtime.
        'canvas.identity' => 'theme-runtime::canvas.identity',
        // theme-runtime::interaction.runtime — ThemeInteractionDispatcher:
        // delegated click/submit handling for data-tb-interaction blocks
        // (scroll_to / open_modal / toggle_element / navigate / call_api).
        'interaction.runtime' => 'theme-runtime::interaction.runtime',
    ];

    /** Capability suffixes the public JS runtime may consume. */
    private const RUNTIME_CAPABILITIES = ['runtime'];

    public function __construct(
        private readonly SeoService $seoService,
        private string $mode = self::MODE_EDITOR,
    ) {
        if (!in_array($mode, self::MODES, true)) {
            throw new \InvalidArgumentException(
                "Unknown ThemeRuntimeDocument mode \"{$mode}\" (expected one of: "
                . implode(', ', self::MODES) . ')'
            );
        }
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function isPublic(): bool
    {
        return $this->mode === self::MODE_PUBLIC;
    }

    public function isEditor(): bool
    {
        return $this->mode === self::MODE_EDITOR;
    }

    /**
     * The asset views a host layout must attach for this mode.
     *
     * MODE_PUBLIC: strictly the runtime pair above — NEVER
     * theme-runtime::canvas.editor-bootstrap (window.CanvasEditor +
     * structural services + protocol bridge live there, and none of them
     * may exist on a public page). MODE_EDITOR: the full editor bridge
     * (which itself includes identity and interaction runtime, in the
     * contract order its view owns).
     *
     * @return list<string> Blade view names, in required include order.
     */
    public function requiredAssets(): array
    {
        return $this->isPublic()
            ? array_values(self::PUBLIC_REQUIRED_ASSETS)
            : ['theme-runtime::canvas.editor-bootstrap'];
    }

    /**
     * The capabilityProjection for this mode — the capability vocabulary the
     * JS runtime on THIS surface may consume.
     *
     * Editor surfaces project the full editing capability set (the bridge
     * and structural services own it — e.g. NodeCapabilities' canEdit /
     * canMove / canDelete / allowedChildKinds authoring flags).
     * MODE_PUBLIC is strictly filtered to 'runtime' capabilities only:
     * keys in the `runtime` namespace (`runtime.interactions`,
     * `runtime.identity.version`, …) — the runtime/identity/interaction
     * behavior the public JS consumes — never authoring capabilities,
     * which are editor-only by definition.
     *
     * @param  array<string, mixed> $capabilities Full capability projection
     *         (e.g. page/node capability metadata the host resolved).
     * @return array<string, mixed> Mode-filtered projection (same keys only
     *         when the projection is already editor-only).
     */
    public function capabilityProjection(array $capabilities = []): array
    {
        if (!$this->isPublic()) {
            return $capabilities;
        }

        return array_filter(
            $capabilities,
            static function ($value, string $key): bool {
                if ($key === 'runtime') {
                    return true;
                }
                foreach (self::RUNTIME_CAPABILITIES as $namespace) {
                    if (str_starts_with($key, "{$namespace}.")) {
                        return true;
                    }
                }
                return false;
            },
            ARRAY_FILTER_USE_BOTH
        );
    }

    public function resolveMetaData(
        array $pageData,
        array $themeSettings = [],
        array $globalStyles = [],
    ): MetaData {
        $source = array_merge($pageData, [
            'seo' => $pageData['seo'] ?? [],
            'theme_settings' => $themeSettings,
            'global_styles' => $globalStyles,
        ]);

        return $this->seoService->resolveForCanvasPublic($source);
    }
}
