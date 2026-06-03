<?php

namespace SaaSPlatform\Modules\Education\DataTransferObjects;

class FeatureDto
{
    public function __construct(
        public readonly string  $moduleSlug,
        public readonly string  $featureSlug,
        public readonly string  $featureName,
        public readonly ?string $routeName,
        public readonly array   $permissions    = [],
        public readonly ?string $menuLocation   = null,
        public readonly ?string $description    = null,
        public readonly array   $metadata       = [],
    ) {}

    public function toArray(): array
    {
        return [
            'module_slug'      => $this->moduleSlug,
            'feature_slug'     => $this->featureSlug,
            'feature_name'     => $this->featureName,
            'route_name'       => $this->routeName,
            'permissions_json' => $this->permissions   ?: null,
            'metadata_json'    => array_filter([
                'menu_location' => $this->menuLocation,
                'description'   => $this->description,
            ] + $this->metadata) ?: null,
        ];
    }
}
