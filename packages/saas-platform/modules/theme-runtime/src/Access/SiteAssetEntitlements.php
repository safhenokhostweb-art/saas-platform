<?php

namespace SaaSPlatform\ThemeRuntime\Access;

/**
 * ADR-087: what one site holds, as the platform provisions it: its
 * business vertical, its entitlement keys (the module / licence
 * projection), the assets granted to it one by one, and the platform's
 * per-asset policy overrides.
 *
 * The platform builds it from its own tables; customer-site rebuilds it
 * from the provisioning payload (fromPayload), which is validated strictly
 * and rejected whole when malformed, so a caller keeps its last good copy.
 * A site that never received a payload holds none(): public assets only.
 */
final class SiteAssetEntitlements
{
    public const PAYLOAD_SCHEMA = 1;

    /**
     * @param list<string> $entitlementKeys
     * @param array<string, true> $grants ref => true
     * @param array<string, AssetAccessRule> $overrides ref => rule
     */
    private function __construct(
        public readonly ?string $vertical,
        public readonly array $entitlementKeys,
        private readonly array $grants,
        private readonly array $overrides,
        public readonly bool $platformDesigner = false,
        public readonly ?string $issuedAt = null,
    ) {}

    /** No payload: every restricted asset is locked or hidden. */
    public static function none(): self
    {
        return new self(null, [], [], []);
    }

    /**
     * The platform's own design surface (a global catalog theme that no
     * site owns yet): everything is usable, restricted assets keep their
     * badge so the designer sees what a tenant would need.
     */
    public static function platformDesigner(): self
    {
        return new self(null, [], [], [], true);
    }

    /**
     * @param list<string> $entitlementKeys
     * @param list<string> $grantRefs
     * @param array<string, array> $overrides ref => rule array
     */
    public static function make(?string $vertical, array $entitlementKeys, array $grantRefs = [], array $overrides = [], ?string $issuedAt = null): self
    {
        $grants = [];
        foreach ($grantRefs as $ref) {
            if (AssetRef::isValid($ref)) {
                $grants[$ref] = true;
            }
        }
        $rules = [];
        foreach ($overrides as $ref => $raw) {
            if (AssetRef::isValid($ref) && is_array($raw)) {
                $rules[$ref] = AssetAccessRule::fromArray($raw);
            }
        }

        return new self(
            AssetAccessRule::isValidSlug($vertical) ? $vertical : null,
            AssetAccessRule::cleanSlugs($entitlementKeys),
            $grants,
            $rules,
            false,
            $issuedAt,
        );
    }

    /** Null when the payload is not a well-formed schema-1 document. */
    public static function fromPayload(mixed $payload): ?self
    {
        if (!is_array($payload) || ($payload['schema'] ?? null) !== self::PAYLOAD_SCHEMA) {
            return null;
        }
        $vertical = $payload['vertical'] ?? null;
        if ($vertical !== null && !AssetAccessRule::isValidSlug($vertical)) {
            return null;
        }
        foreach (['entitlement_keys', 'grants'] as $listKey) {
            if (!is_array($payload[$listKey] ?? null) || !array_is_list($payload[$listKey])) {
                return null;
            }
        }
        foreach ($payload['grants'] as $ref) {
            if (!AssetRef::isValid($ref)) {
                return null;
            }
        }
        $overrides = $payload['overrides'] ?? [];
        if ($overrides instanceof \stdClass) {
            // toPayload()'s own empty-map spelling, read back without a JSON round trip.
            $overrides = (array) $overrides;
        }
        if (!is_array($overrides) || ($overrides !== [] && array_is_list($overrides))) {
            return null;
        }
        foreach ($overrides as $ref => $raw) {
            if (!AssetRef::isValid($ref) || !is_array($raw) || !is_bool($raw['is_public'] ?? null)) {
                return null;
            }
        }
        $issuedAt = $payload['issued_at'] ?? null;

        return self::make(
            $vertical,
            $payload['entitlement_keys'],
            $payload['grants'],
            $overrides,
            is_string($issuedAt) ? mb_substr($issuedAt, 0, 40) : null,
        );
    }

    public function toPayload(): array
    {
        $overrides = [];
        foreach ($this->overrides as $ref => $rule) {
            $overrides[$ref] = $rule->toArray();
        }
        ksort($overrides);
        $grants = array_keys($this->grants);
        sort($grants);

        return [
            'schema' => self::PAYLOAD_SCHEMA,
            'vertical' => $this->vertical,
            'entitlement_keys' => $this->entitlementKeys,
            'grants' => $grants,
            // An empty map must stay a JSON object, never a list.
            'overrides' => $overrides === [] ? new \stdClass() : $overrides,
            'issued_at' => $this->issuedAt,
        ];
    }

    public function hasGrant(string $ref): bool
    {
        return isset($this->grants[$ref]);
    }

    /** @param list<string> $keys */
    public function holdsAny(array $keys): bool
    {
        return array_intersect($keys, $this->entitlementKeys) !== [];
    }

    public function override(string $ref): ?AssetAccessRule
    {
        return $this->overrides[$ref] ?? null;
    }

    /** @return list<string> */
    public function grantRefs(): array
    {
        return array_keys($this->grants);
    }
}
