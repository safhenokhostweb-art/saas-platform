<?php

namespace SaaSPlatform\ExtensionSdk;

/**
 * Phase 3G-B-2 — the shared, pure-declarative description of one Extension
 * block a manifest provides: its full `ext:{vendor}:{block}` type, editor
 * metadata, and control schema. Mirrors the authoring-safe subset of
 * customer-site's App\Builder\Definition\ThemeBuilderElementDefinition
 * semantics (type, label, category, icon, controls, is_container,
 * allowed_children, has_assets) but deliberately EXCLUDES everything
 * executable or runtime-derived on that class: no renderer class reference
 * (that lives only on customer-site's ThemeBuilderExtensionBlock),
 * no `ownership` classification (host-derived from local registry state,
 * meaningless outside customer-site's runtime), and no `supportsDynamicData`
 * flag (no current commercial-manifest consumer needs it — not guessed in).
 *
 * `allowed_children` is carried as declarative metadata only, matching
 * ADR-043's status quo: customer-site does not enforce it today, so this
 * package must not pretend otherwise. `has_assets` is a plain boolean
 * capability flag kept for backward compatibility, strictly validated for
 * consistency with `assets` below — never an independent authority.
 *
 * Phase 3G-B-6A (ADR-048) — `assets` is the ONE declarative, JSON-safe,
 * non-executable CSS/JS reference list a manifest may carry per element:
 * `{css: string[], js: string[]}`, each entry a root-relative public path
 * (starting with exactly one "/", no ".." segments, no scheme, ASCII
 * letters/digits/"_-./" only, ending in the matching extension) — the exact
 * value shape customer-site's own `App\Builder\Contracts\HasAssets::
 * getAssets()` already returns and its Blade views already emit verbatim as
 * `<link href>` / `<script src>` (see resources/views/themes/default/
 * page.blade.php). Never a full URL (no scheme/host), never a filesystem
 * path, never a PHP class/method reference (no `rendererClass` analog
 * exists or is ever added here) — this package still never carries anything
 * a consumer would need to EXECUTE to discover assets, only to link to.
 */
final class ExtensionElementDefinition
{
    private const ASSET_PATH_PATTERN = '#^/[A-Za-z0-9_\-./]+$#';

    /**
     * @param array<string, ExtensionControlDefinition> $controls keyed by control key
     * @param array{css: string[], js: string[]} $assets
     */
    private function __construct(
        public readonly string $type,
        public readonly string $label,
        public readonly string $category,
        public readonly string $icon,
        public readonly array $controls,
        public readonly bool $isContainer,
        public readonly ?array $allowedChildren,
        public readonly bool $hasAssets,
        public readonly array $assets,
    ) {
    }

    /**
     * @param ExtensionControlDefinition[] $controls
     * @param array{css?: string[], js?: string[]} $assets
     */
    public static function make(
        string $type,
        string $label,
        string $category,
        array $controls,
        string $icon = '',
        bool $isContainer = false,
        ?array $allowedChildren = null,
        bool $hasAssets = false,
        array $assets = [],
    ): self {
        $keyed = [];
        foreach ($controls as $control) {
            if (!$control instanceof ExtensionControlDefinition) {
                throw ExtensionSdkException::invalidManifestShape('Every control must be an ExtensionControlDefinition instance.');
            }
            if (array_key_exists($control->key, $keyed)) {
                throw ExtensionSdkException::duplicateControlKey($control->key);
            }
            $keyed[$control->key] = $control;
        }

        if ($allowedChildren !== null) {
            foreach ($allowedChildren as $child) {
                if (!is_string($child) || $child === '') {
                    throw ExtensionSdkException::invalidAllowedChildrenShape($type);
                }
            }
        }

        $normalizedAssets = self::assertValidAssets($type, $assets);

        if ($hasAssets !== ($normalizedAssets['css'] !== [] || $normalizedAssets['js'] !== [])) {
            throw ExtensionSdkException::hasAssetsInconsistentWithAssets($type);
        }

        return new self($type, $label, $category, $icon, $keyed, $isContainer, $allowedChildren, $hasAssets, $normalizedAssets);
    }

    public static function fromArray(array $data): self
    {
        $controls = [];
        foreach ((array) ($data['controls'] ?? []) as $key => $controlData) {
            if (!is_array($controlData)) {
                throw ExtensionSdkException::invalidManifestShape('Every entry in "controls" must be an object.');
            }
            $controls[] = ExtensionControlDefinition::fromArray((string) $key, $controlData);
        }

        $allowedChildren = $data['allowed_children'] ?? null;
        if ($allowedChildren !== null && !is_array($allowedChildren)) {
            throw ExtensionSdkException::invalidAllowedChildrenShape((string) ($data['type'] ?? ''));
        }

        // Absent entirely on a pre-3G-B-6A manifest row — defaults to empty,
        // never a migration, never a required key (§5).
        $assets = $data['assets'] ?? [];
        if (!is_array($assets)) {
            throw ExtensionSdkException::invalidAssetShape((string) ($data['type'] ?? ''));
        }

        return self::make(
            type: (string) ($data['type'] ?? ''),
            label: (string) ($data['label'] ?? ''),
            category: (string) ($data['category'] ?? ''),
            controls: $controls,
            icon: (string) ($data['icon'] ?? ''),
            isContainer: (bool) ($data['is_container'] ?? false),
            allowedChildren: $allowedChildren,
            hasAssets: (bool) ($data['has_assets'] ?? false),
            assets: $assets,
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'label' => $this->label,
            'category' => $this->category,
            'icon' => $this->icon,
            'controls' => array_map(static fn (ExtensionControlDefinition $c) => $c->toArray(), $this->controls),
            'is_container' => $this->isContainer,
            'allowed_children' => $this->allowedChildren,
            'has_assets' => $this->hasAssets,
            'assets' => $this->assets,
        ];
    }

    /**
     * @param array{css?: mixed, js?: mixed} $assets
     * @return array{css: string[], js: string[]}
     */
    private static function assertValidAssets(string $type, array $assets): array
    {
        $extra = array_diff(array_keys($assets), ['css', 'js']);
        if ($extra !== []) {
            throw ExtensionSdkException::invalidAssetShape($type);
        }

        $normalized = [];
        foreach (['css', 'js'] as $family) {
            $list = $assets[$family] ?? [];
            if (!is_array($list) || !array_is_list($list)) {
                throw ExtensionSdkException::invalidAssetShape($type);
            }

            foreach ($list as $value) {
                if (!is_string($value) || !self::isValidAssetPath($value, $family)) {
                    throw ExtensionSdkException::invalidAssetReference($type, $family, is_string($value) ? $value : gettype($value));
                }
            }

            $normalized[$family] = array_values($list);
        }

        return $normalized;
    }

    /** Root-relative public path only — see this class's own doc comment for the exact contract. */
    private static function isValidAssetPath(string $path, string $expectedExtension): bool
    {
        if ($path === '' || $path[0] !== '/' || (strlen($path) > 1 && $path[1] === '/')) {
            return false; // must have exactly one leading "/" — rejects "//host/..." protocol-relative and bare relative paths
        }

        if (!preg_match(self::ASSET_PATH_PATTERN, $path)) {
            return false; // rejects any scheme ("javascript:", "data:", "https:"), quotes, markup, whitespace, backslashes
        }

        if (!str_ends_with($path, '.' . $expectedExtension)) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false; // rejects path traversal
            }
        }

        return true;
    }

    /**
     * Derived {control_key => default_value} map — sourced from each
     * control's own `default`, never a separately stored authority (no
     * current runtime definition carries a second, independent top-level
     * defaults map, so this package does not invent one).
     */
    public function defaults(): array
    {
        $defaults = [];
        foreach ($this->controls as $key => $control) {
            $defaults[$key] = $control->default;
        }

        return $defaults;
    }
}
