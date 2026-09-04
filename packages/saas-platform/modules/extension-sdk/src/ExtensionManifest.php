<?php

namespace SaaSPlatform\ExtensionSdk;

/**
 * Phase 3G-B-2 (ADR-045 points 5, 10, 11) — the ONE canonical declarative
 * Extension RELEASE manifest authority, replacing
 * ExtensionReleaseService::assertValidManifest()'s prior structural-only
 * (non-empty, non-list) validation with real schema enforcement.
 *
 * Represents ONE Extension release declaration in full: which product it
 * belongs to, its package version, the SDK contract it targets, and the
 * elements (blocks) it provides. Deliberately carries no executable
 * behavior — no renderer class names, no closures — so it is always
 * JSON-safe to persist as `ExtensionRelease.manifest` and safe for
 * saas-platform to construct/validate without ever executing third-party
 * Extension provider code.
 *
 * The `ext:{vendor}:{block}` grammar checked by assertValidBlockType() below
 * is a narrow, MANIFEST-STRUCTURAL check only (prefix + vendor grammar +
 * block-name grammar) — it is not a registry/collision authority and must
 * never become one. Persisted-block identity governance (claiming,
 * collision, canonical/host/extension precedence) remains solely owned by
 * customer-site's ExtensionBlockTypeIdentity/ExtensionBlockTypeRegistry, per
 * ADR-045: "do not merge ExtensionProductIdentity with
 * ExtensionBlockTypeIdentity — they solve different problems." Whoever
 * changes the block-name grammar in either place must keep this one in
 * parity: `/^[a-z0-9]+(_[a-z0-9]+)*$/`, 1-60 characters.
 */
final class ExtensionManifest
{
    private const VERSION_PATTERN = '/^\d+\.\d+\.\d+$/';
    private const BLOCK_TYPE_PREFIX = 'ext:';
    private const BLOCK_NAME_PATTERN = '/^[a-z0-9]+(_[a-z0-9]+)*$/';
    private const BLOCK_NAME_MIN = 1;
    private const BLOCK_NAME_MAX = 60;

    /** @param array<string, ExtensionElementDefinition> $elements keyed by full ext:* type */
    private function __construct(
        public readonly ExtensionProductIdentity $productIdentity,
        public readonly string $packageVersion,
        public readonly int $sdkContractVersion,
        public readonly array $elements,
    ) {
    }

    /** @param ExtensionElementDefinition[] $elements */
    public static function make(
        ExtensionProductIdentity $productIdentity,
        string $packageVersion,
        int $sdkContractVersion,
        array $elements,
    ): self {
        self::assertValidVersion($packageVersion);
        self::assertValidSdkContract($sdkContractVersion);

        if ($elements === []) {
            throw ExtensionSdkException::emptyElements();
        }

        $keyed = [];
        foreach ($elements as $element) {
            if (!$element instanceof ExtensionElementDefinition) {
                throw ExtensionSdkException::invalidManifestShape('Every element must be an ExtensionElementDefinition instance.');
            }

            $vendorSlug = self::assertValidBlockType($element->type);

            if ($vendorSlug !== $productIdentity->vendorSlug()) {
                throw ExtensionSdkException::elementVendorMismatch($element->type, $productIdentity->vendorSlug());
            }

            if (array_key_exists($element->type, $keyed)) {
                throw ExtensionSdkException::duplicateElementIdentity($element->type);
            }

            self::assertJsonSafe($element->defaults(), $element->type);

            $keyed[$element->type] = $element;
        }

        return new self($productIdentity, $packageVersion, $sdkContractVersion, $keyed);
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['product_identity']) || !is_string($data['product_identity'])) {
            throw ExtensionSdkException::invalidManifestShape('a string "product_identity" field is required.');
        }

        if (!isset($data['elements']) || !is_array($data['elements'])) {
            throw ExtensionSdkException::invalidManifestShape('an "elements" list is required.');
        }

        $elements = [];
        foreach ($data['elements'] as $elementData) {
            if (!is_array($elementData)) {
                throw ExtensionSdkException::invalidManifestShape('every entry in "elements" must be an object.');
            }
            $elements[] = ExtensionElementDefinition::fromArray($elementData);
        }

        return self::make(
            productIdentity: ExtensionProductIdentity::fromString($data['product_identity']),
            packageVersion: (string) ($data['package_version'] ?? ''),
            sdkContractVersion: (int) ($data['sdk_contract_version'] ?? 0),
            elements: $elements,
        );
    }

    public function toArray(): array
    {
        return [
            'product_identity' => $this->productIdentity->toString(),
            'package_version' => $this->packageVersion,
            'sdk_contract_version' => $this->sdkContractVersion,
            'elements' => array_values(array_map(
                static fn (ExtensionElementDefinition $e) => $e->toArray(),
                $this->elements
            )),
        ];
    }

    private static function assertValidVersion(string $version): void
    {
        if (!preg_match(self::VERSION_PATTERN, $version)) {
            throw ExtensionSdkException::malformedVersion($version);
        }
    }

    private static function assertValidSdkContract(int $sdkContractVersion): void
    {
        if (!ExtensionSdkContract::isSupported($sdkContractVersion)) {
            throw ExtensionSdkException::unsupportedSdkContractVersion($sdkContractVersion, ExtensionSdkContract::SUPPORTED);
        }
    }

    /** Returns the parsed vendor slug. Manifest-structural check only — see class doc comment. */
    private static function assertValidBlockType(string $type): string
    {
        if (!str_starts_with($type, self::BLOCK_TYPE_PREFIX)) {
            throw ExtensionSdkException::malformedElementType($type);
        }

        $withoutPrefix = substr($type, strlen(self::BLOCK_TYPE_PREFIX));
        $parts = explode(':', $withoutPrefix, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw ExtensionSdkException::malformedElementType($type);
        }

        [$vendorSlug, $blockName] = $parts;

        if (!ExtensionProductIdentity::isValidVendorSlug($vendorSlug)) {
            throw ExtensionSdkException::malformedElementType($type);
        }

        $length = strlen($blockName);
        if (
            $length < self::BLOCK_NAME_MIN
            || $length > self::BLOCK_NAME_MAX
            || preg_match(self::BLOCK_NAME_PATTERN, $blockName) !== 1
        ) {
            throw ExtensionSdkException::malformedElementType($type);
        }

        return $vendorSlug;
    }

    private static function assertJsonSafe(mixed $value, string $context): void
    {
        if ($value === null || is_scalar($value)) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                self::assertJsonSafe($item, $context);
            }

            return;
        }

        throw ExtensionSdkException::nonJsonSafeValue($context);
    }
}
