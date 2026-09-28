<?php
namespace App\Services\Seo;

use SaaSPlatform\CoreUtils\Contracts\Services\SeoOverrideProviderInterface;

class NullSeoOverrideProvider implements SeoOverrideProviderInterface
{
    public function overrideFor(): ?array
    {
        return null;
    }

    public function extraJsonLd(): array
    {
        return [];
    }
}