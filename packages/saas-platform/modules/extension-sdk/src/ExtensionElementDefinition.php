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
 * capability flag (an extension has or doesn't have an asset to emit) —
 * never an asset URL, path, or executable collector.
 */
final class ExtensionElementDefinition
{
    /** @param array<string, ExtensionControlDefinition> $controls keyed by control key */
    private function __construct(
        public readonly string $type,
        public readonly string $label,
        public readonly string $category,
        public readonly string $icon,
        public readonly array $controls,
        public readonly bool $isContainer,
        public readonly ?array $allowedChildren,
        public readonly bool $hasAssets,
    ) {
    }

    /** @param ExtensionControlDefinition[] $controls */
    public static function make(
        string $type,
        string $label,
        string $category,
        array $controls,
        string $icon = '',
        bool $isContainer = false,
        ?array $allowedChildren = null,
        bool $hasAssets = false,
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

        return new self($type, $label, $category, $icon, $keyed, $isContainer, $allowedChildren, $hasAssets);
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

        return self::make(
            type: (string) ($data['type'] ?? ''),
            label: (string) ($data['label'] ?? ''),
            category: (string) ($data['category'] ?? ''),
            controls: $controls,
            icon: (string) ($data['icon'] ?? ''),
            isContainer: (bool) ($data['is_container'] ?? false),
            allowedChildren: $allowedChildren,
            hasAssets: (bool) ($data['has_assets'] ?? false),
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
        ];
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
