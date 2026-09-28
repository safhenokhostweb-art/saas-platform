<?php
namespace App\Services\Seo;

use SaaSPlatform\CoreUtils\SEO\MetaContext;
use SaaSPlatform\CoreUtils\SEO\MetaData;
use SaaSPlatform\CoreUtils\SEO\MetaEngine;
use SaaSPlatform\CoreUtils\Contracts\Services\SeoOverrideProviderInterface;

class SeoService
{
    public function __construct(
        private readonly MetaEngine $metaEngine,
        private readonly SeoOverrideProviderInterface $overrideProvider,
    ) {}

    public function resolve(
        MetaContext $context,
        object|array|null $source = null,
        array $overrides = [],
        array $platformSettings = [],
        array $tenantSettings = [],
    ): MetaData {
        // The provider contributes DEFAULTS, not overrides: it fills in a field
        // only when neither the page ($source) nor the caller ($overrides)
        // supplies one. Merging it into $overrides instead — as this used to —
        // gave a module's site-wide title/description/robots higher precedence
        // than the page's own SEO fields, so installing SEO Advanced flattened
        // every page of the site onto one title. SeoOverrideProviderInterface
        // documents the value as the one to use "when the host has no existing
        // default for that field", and customer-site's own SeoService already
        // reads a model first and the provider second.
        return $this->metaEngine->resolve(
            $context,
            $source,
            $overrides,
            $platformSettings,
            $tenantSettings,
            $this->providerDefaults(),
        );
    }

    /**
     * Reads the bound provider defensively.
     *
     * A module adapter talks to its own tables, so an environment where the
     * module is registered but its migrations have not been applied would
     * otherwise turn every page render into a 500. A provider that throws
     * or returns something that is not a field map is treated exactly like
     * the Null Object — the caller still gets a fully resolved MetaData —
     * which is also the behaviour the contract documents and the behaviour
     * customer-site's own SeoService already implements.
     *
     * @return array<string, mixed>
     */
    private function providerDefaults(): array
    {
        try {
            $overrides = $this->overrideProvider->overrideFor();
        } catch (\Throwable) {
            return [];
        }

        return is_array($overrides) ? $overrides : [];
    }

    public function resolveForCanvasPublic(
        object|array|null $source = null,
        array $overrides = [],
    ): MetaData {
        return $this->resolve(
            MetaContext::CANVAS_PUBLIC,
            $source,
            $overrides,
        );
    }

    public function resolveForPlatformAdmin(
        object|array|null $source = null,
        array $overrides = [],
    ): MetaData {
        return $this->resolve(
            MetaContext::PLATFORM_ADMIN,
            $source,
            $overrides,
        );
    }

    public function resolveForPlatformPublic(
        object|array|null $source = null,
        array $overrides = [],
    ): MetaData {
        return $this->resolve(
            MetaContext::PLATFORM_PUBLIC,
            $source,
            $overrides,
        );
    }

    public function resolveForTenantAdmin(
        object|array|null $source = null,
        array $overrides = [],
    ): MetaData {
        return $this->resolve(
            MetaContext::TENANT_ADMIN,
            $source,
            $overrides,
        );
    }

    public function resolveForTenantPublic(
        object|array|null $source = null,
        array $overrides = [],
    ): MetaData {
        return $this->resolve(
            MetaContext::TENANT_PUBLIC,
            $source,
            $overrides,
        );
    }

    public function resolveForPreview(
        object|array|null $source = null,
        array $overrides = [],
    ): MetaData {
        return $this->resolve(
            MetaContext::PREVIEW_EDITOR,
            $source,
            $overrides,
        );
    }
}