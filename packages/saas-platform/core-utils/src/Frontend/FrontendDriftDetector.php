<?php

namespace SaaSPlatform\CoreUtils\Frontend;

/**
 * Phase 10.0 — Frontend Drift Detector
 * Detects visual drift between saas-platform and customer-site frontend implementations.
 */
class FrontendDriftDetector
{
    private array $driftPoints = [];

    public function detect(): array
    {
        $this->driftPoints = [];

        $this->detectCssVariableDrift();
        $this->detectTailwindVersionDrift();
        $this->detectSeparateNavigationSystems();
        $this->detectCustomerSiteLocalComponents();
        $this->detectSeparateAnimationSystems();

        return [
            'drift_count'  => count($this->driftPoints),
            'critical'     => array_filter($this->driftPoints, fn($d) => $d['severity'] === 'CRITICAL'),
            'high'         => array_filter($this->driftPoints, fn($d) => $d['severity'] === 'HIGH'),
            'medium'       => array_filter($this->driftPoints, fn($d) => $d['severity'] === 'MEDIUM'),
            'drift_points' => $this->driftPoints,
            'drift_free'   => count($this->driftPoints) === 0,
        ];
    }

    private function detectCssVariableDrift(): void
    {
        $platformCss  = resource_path('css/app.css');
        $customerCss  = base_path('../customer-site/resources/css/app.css');

        if (! file_exists($platformCss) || ! file_exists($customerCss)) return;

        $platformHasTokens  = str_contains(file_get_contents($platformCss), 'design-tokens.css');
        $customerHasTokens  = str_contains(file_get_contents($customerCss), 'design-tokens.css');

        if ($platformHasTokens && $customerHasTokens) return;

        if (! $platformHasTokens) {
            $this->addDrift('css-tokens-platform', 'CRITICAL', 'saas-platform/app.css does not import canonical design-tokens.css');
        }
        if (! $customerHasTokens) {
            $this->addDrift('css-tokens-customer', 'CRITICAL', 'customer-site/app.css does not import canonical design-tokens.css');
        }
    }

    private function detectTailwindVersionDrift(): void
    {
        $platformConfig   = base_path('tailwind.config.js');
        $customerVite     = base_path('../customer-site/vite.config.js');

        $platformIsV3 = file_exists($platformConfig);
        $customerIsV4 = file_exists($customerVite) && str_contains(file_get_contents($customerVite), '@tailwindcss/vite');

        if ($platformIsV3 && $customerIsV4) {
            $this->addDrift('tailwind-version', 'CRITICAL',
                'saas-platform uses Tailwind v3 (tailwind.config.js) but customer-site uses Tailwind v4 (@tailwindcss/vite). Design token CSS mitigates this but compiled utility classes may differ.');
        }
    }

    private function detectSeparateNavigationSystems(): void
    {
        $platformAdmin  = resource_path('views/layouts/admin.blade.php');
        $customerAdmin  = base_path('../customer-site/resources/views/admin/layout.blade.php');

        if (file_exists($platformAdmin) && file_exists($customerAdmin)) {
            $this->addDrift('navigation-systems', 'HIGH',
                'saas-platform and customer-site each have independent admin layout files — canonical x-core-ui::sidebar-group not yet wired to both layouts');
        }
    }

    private function detectCustomerSiteLocalComponents(): void
    {
        $customerComponents = base_path('../customer-site/resources/views/components');
        if (! is_dir($customerComponents)) return;

        $files = glob($customerComponents . '/*.blade.php');
        if (! empty($files)) {
            $names = array_map(fn($f) => basename($f, '.blade.php'), $files);
            $this->addDrift('customer-local-components', 'MEDIUM',
                'customer-site has local components: ' . implode(', ', $names) . ' — verify these do not duplicate core-ui');
        }
    }

    private function detectSeparateAnimationSystems(): void
    {
        $ticketsIndex = base_path('packages/saas-platform/modules/tickets/Resources/views/admin/tickets/index.blade.php');
        if (! file_exists($ticketsIndex)) return;

        $content = file_get_contents($ticketsIndex);
        if (str_contains($content, '@keyframes') && str_contains($content, '@push(\'head\')')) {
            $this->addDrift('tickets-inline-animations', 'CRITICAL',
                'tickets/admin/index.blade.php defines @keyframe animations in @push(head) — not using canonical token system');
        }
    }

    private function addDrift(string $key, string $severity, string $message): void
    {
        $this->driftPoints[$key] = compact('severity', 'message');
    }
}
