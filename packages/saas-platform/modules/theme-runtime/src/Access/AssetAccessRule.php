<?php

namespace SaaSPlatform\ThemeRuntime\Access;

/**
 * ADR-087: who may use one platform asset.
 *
 * Public reaches every site. Restricted reaches a site that holds a grant
 * for the asset, or that meets every declared condition: its business
 * vertical equals `required_vertical` (when set) and it holds at least one
 * of `entitlement_keys` (when set; the keys are the platform's module /
 * licence slugs, i.e. the site's entitlement projection). A restricted
 * rule with no condition is grant-only.
 *
 * Parsing fails closed: any visibility other than exactly "public" is
 * restricted, and a malformed condition is dropped, which can only make
 * the rule stricter (a restricted rule left without conditions is
 * grant-only), never public.
 */
final class AssetAccessRule
{
    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_RESTRICTED = 'restricted';

    /** Locked assets are listed with a badge (upgrade path) ... */
    public const DISPLAY_TEASER = 'teaser';

    /** ... or not listed at all. */
    public const DISPLAY_HIDDEN = 'hidden';

    private const SLUG_PATTERN = '/^[a-z0-9][a-z0-9_\-]{0,63}$/';

    /** @param list<string> $entitlementKeys */
    private function __construct(
        public readonly bool $isPublic,
        public readonly ?string $requiredVertical,
        public readonly array $entitlementKeys,
        public readonly string $lockedDisplay,
        public readonly ?string $family = null,
        public readonly ?string $familyLabel = null,
    ) {}

    public static function public(): self
    {
        return new self(true, null, [], self::DISPLAY_HIDDEN);
    }

    /** An asset nobody declared: grant-only and never listed while locked. */
    public static function failClosed(): self
    {
        return new self(false, null, [], self::DISPLAY_HIDDEN);
    }

    /**
     * From a manifest family, a platform override row or a provisioning
     * payload entry. Accepts `visibility` ("public"/"restricted") or the
     * platform column spelling `is_public` (bool).
     */
    public static function fromArray(array $raw, ?string $family = null): self
    {
        $isPublic = array_key_exists('is_public', $raw)
            ? $raw['is_public'] === true
            : ($raw['visibility'] ?? null) === self::VISIBILITY_PUBLIC;

        $label = $raw['label'] ?? null;
        $label = is_string($label) && trim($label) !== '' ? mb_substr(trim($label), 0, 120) : null;

        if ($isPublic) {
            return new self(true, null, [], self::DISPLAY_HIDDEN, $family, $label);
        }

        $vertical = $raw['required_vertical'] ?? null;
        $vertical = is_string($vertical) && preg_match(self::SLUG_PATTERN, $vertical) === 1 ? $vertical : null;

        return new self(
            false,
            $vertical,
            self::cleanSlugs($raw['entitlement_keys'] ?? []),
            ($raw['locked_display'] ?? null) === self::DISPLAY_TEASER ? self::DISPLAY_TEASER : self::DISPLAY_HIDDEN,
            $family,
            $label,
        );
    }

    /** @return list<string> */
    public static function cleanSlugs(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $value) {
            if (is_string($value) && preg_match(self::SLUG_PATTERN, $value) === 1) {
                $out[$value] = true;
            }
        }

        return array_keys($out);
    }

    public static function isValidSlug(mixed $value): bool
    {
        return is_string($value) && preg_match(self::SLUG_PATTERN, $value) === 1;
    }

    public function hasConditions(): bool
    {
        return $this->requiredVertical !== null || $this->entitlementKeys !== [];
    }

    /** The platform column / payload spelling. */
    public function toArray(): array
    {
        return [
            'is_public' => $this->isPublic,
            'required_vertical' => $this->requiredVertical,
            'entitlement_keys' => $this->entitlementKeys,
            'locked_display' => $this->lockedDisplay,
        ];
    }

    public function withFamily(?string $family, ?string $label): self
    {
        return new self($this->isPublic, $this->requiredVertical, $this->entitlementKeys, $this->lockedDisplay, $family, $label);
    }
}
