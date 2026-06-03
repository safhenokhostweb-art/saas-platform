<?php

namespace SaaSPlatform\Modules\Education\Services;

use App\Helpers\AdminMenu;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationFeatureRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\FeatureDiscoveryServiceInterface;
use SaaSPlatform\Modules\Education\DataTransferObjects\FeatureDto;

class FeatureDiscoveryService implements FeatureDiscoveryServiceInterface
{
    public function __construct(
        private readonly EducationFeatureRepositoryInterface $features,
    ) {}

    public function discover(?int $siteId = null): int
    {
        $dtos = collect()
            ->merge($this->discoverFromMenu())
            ->merge($this->discoverFromManifests())
            ->unique(fn(FeatureDto $d) => "{$d->moduleSlug}.{$d->featureSlug}");

        return $this->features->upsertMany($dtos->all(), $siteId);
    }

    public function discoverFromMenu(): Collection
    {
        $dtos = collect();

        foreach (AdminMenu::MENU_KEYS as $key => $label) {
            $routeName    = AdminMenu::MENU_ROUTES[$key] ?? null;
            $moduleSlug   = $this->keyToModule($key);
            $featureSlug  = $key;
            $menuLocation = $this->resolveMenuGroup($key);

            $dtos->push(new FeatureDto(
                moduleSlug:   $moduleSlug,
                featureSlug:  $featureSlug,
                featureName:  $label,
                routeName:    $routeName,
                menuLocation: $menuLocation,
                description:  "آیتم منوی ادمین: {$label}",
                metadata:     ['source' => 'admin_menu'],
            ));
        }

        return $dtos;
    }

    public function discoverFromRoutes(): Collection
    {
        $dtos    = collect();
        $routes  = Route::getRoutes();
        $known   = collect(AdminMenu::MENU_ROUTES)->values()->toArray();

        foreach ($routes as $route) {
            $name = $route->getName();
            if (! $name || ! str_starts_with($name, 'admin.')) {
                continue;
            }
            if (in_array($name, $known, true)) {
                continue; // already covered by menu discovery
            }
            if (! str_contains($name, '.index')) {
                continue; // only index routes for discovery
            }

            $parts       = explode('.', $name);
            $moduleSlug  = $parts[1] ?? 'platform';
            $featureSlug = implode('.', array_slice($parts, 1));
            $featureName = ucwords(str_replace(['.', '-', '_'], ' ', $featureSlug));

            $dtos->push(new FeatureDto(
                moduleSlug:  $moduleSlug,
                featureSlug: $featureSlug,
                featureName: $featureName,
                routeName:   $name,
                metadata:    ['source' => 'route_scan'],
            ));
        }

        return $dtos;
    }

    public function discoverFromManifests(): Collection
    {
        $dtos = collect();

        foreach (config() as $key => $value) {
            if (! str_starts_with($key, 'module-manifest-') || ! is_array($value)) {
                continue;
            }

            $module      = $value['module'] ?? [];
            $moduleSlug  = $module['slug'] ?? null;
            $moduleName  = $module['name'] ?? $moduleSlug;
            $capabilities = $value['capabilities'] ?? [];

            if (! $moduleSlug) {
                continue;
            }

            if (empty($capabilities)) {
                // At minimum register the module itself as a feature
                $dtos->push(new FeatureDto(
                    moduleSlug:  $moduleSlug,
                    featureSlug: $moduleSlug,
                    featureName: $moduleName,
                    routeName:   null,
                    metadata:    ['source' => 'module_manifest'],
                ));
                continue;
            }

            foreach ($capabilities as $capability) {
                $capSlug = is_string($capability) ? $capability : ($capability['slug'] ?? null);
                $capName = is_array($capability) ? ($capability['name'] ?? $capSlug) : $capSlug;

                if (! $capSlug) {
                    continue;
                }

                $dtos->push(new FeatureDto(
                    moduleSlug:  $moduleSlug,
                    featureSlug: "{$moduleSlug}.{$capSlug}",
                    featureName: "{$moduleName} — {$capName}",
                    routeName:   null,
                    metadata:    ['source' => 'module_manifest', 'capability' => $capSlug],
                ));
            }
        }

        return $dtos;
    }

    private function keyToModule(string $menuKey): string
    {
        // Map menu keys that belong to platform-level modules
        $map = [
            'dashboard'            => 'platform',
            'users'                => 'platform',
            'customers'            => 'platform',
            'sites'                => 'platform',
            'site_license'         => 'platform',
            'sites_menus'          => 'platform',
            'forms'                => 'forms',
            'forms_submissions'    => 'forms',
            'profile_fields'       => 'platform',
            'settings_profile'     => 'platform',
            'access'               => 'platform',
            'captcha'              => 'platform',
            'activity_log'         => 'platform',
            'platform_settings'    => 'platform',
            'pages'                => 'pages',
            'module_labels'        => 'platform',
            'resellers'            => 'resellers',
            'reseller_commissions' => 'resellers',
            'products'             => 'store',
            'support_plans'        => 'store',
            'payout_requests'      => 'store',
            'support_tickets'      => 'tickets',
            'payment_gateways'     => 'store',
            'modules'              => 'platform',
            'module_licenses'      => 'platform',
            'licenses'             => 'platform',
            'updates'              => 'platform',
            'theme_builder'        => 'theme',
            'theme_manager'        => 'theme',
            'app_releases'         => 'platform',
            'education'            => 'education',
        ];

        return $map[$menuKey] ?? 'platform';
    }

    private function resolveMenuGroup(string $menuKey): ?string
    {
        foreach (AdminMenu::getGroups() as $group => $keys) {
            if (in_array($menuKey, $keys, true)) {
                return $group;
            }
        }
        return null;
    }
}
