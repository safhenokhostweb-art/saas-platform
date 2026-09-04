<?php

namespace SaaSPlatform\ExtensionSdk;

/**
 * Phase 3G-B-2 — the ONE canonical Extension SDK contract-version
 * vocabulary, shared by saas-platform's ExtensionRelease validation and
 * customer-site's ThemeBuilderExtensionDefinition runtime registration.
 * Replaces the two previously independent numeric authorities (saas-platform
 * had none yet; customer-site's ThemeBuilderExtensionDefinition::
 * SDK_CONTRACT_V1 is now DEFINED in terms of this class — see that class's
 * doc comment). Remains a plain integer vocabulary — no semver, no ranges.
 */
final class ExtensionSdkContract
{
    public const V1 = 1;

    /** @var int[] */
    public const SUPPORTED = [self::V1];

    public static function isSupported(int $version): bool
    {
        return in_array($version, self::SUPPORTED, true);
    }
}
