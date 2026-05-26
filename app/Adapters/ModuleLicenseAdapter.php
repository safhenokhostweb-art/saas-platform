<?php

namespace App\Adapters;

use App\Models\Module;
use App\Services\ModuleAssignmentService;
use App\Services\ModuleLicenseService;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\Contracts\Platform\SiteModelInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\ModuleLicenseServiceInterface;

/**
 * Implements ModuleLicenseServiceInterface using the platform's concrete
 * Module, ModuleLicense, and ModuleAssignmentService. Packages call this
 * via the interface — they never import App\ models directly.
 */
class ModuleLicenseAdapter implements ModuleLicenseServiceInterface
{
    public function __construct(
        protected ModuleLicenseService    $licenseService,
        protected ModuleAssignmentService $assignmentService
    ) {}

    public function checkEntitlement(SiteModelInterface $site, string $moduleSlug): array
    {
        try {
            $module = Module::where('slug', $moduleSlug)->first();

            if (!$module) {
                Log::warning('module_license.module_not_found', ['slug' => $moduleSlug, 'site_id' => $site->getId()]);
                return ['allowed' => false, 'reason' => "ماژول {$moduleSlug} در سیستم ثبت نشده است."];
            }

            if ($module->isRetired()) {
                return ['allowed' => false, 'reason' => "ماژول {$moduleSlug} بازنشسته شده است."];
            }

            if ($module->isDistributionDisabled()) {
                return ['allowed' => false, 'reason' => "ماژول {$moduleSlug} غیرفعال است."];
            }

            if ($module->isCore()) {
                return ['allowed' => true, 'reason' => null];
            }

            // Resolve the concrete Site model for methods that require it
            $concretesite = \App\Models\Site::find($site->getId());
            if (!$concretesite) {
                return ['allowed' => false, 'reason' => 'سایت یافت نشد.'];
            }

            if ($module->isPaidMarketplace() || $module->isLicenseOnly()) {
                $hasLicense = $this->licenseService->hasValidLicense($concretesite, $module->id);
                return $hasLicense
                    ? ['allowed' => true, 'reason' => null]
                    : ['allowed' => false, 'reason' => "لایسنس معتبر برای ماژول {$moduleSlug} یافت نشد."];
            }

            if ($module->isFreeAssignable()) {
                $isAssigned = $this->assignmentService->isModuleEnabledForSite($concretesite, $moduleSlug);
                return $isAssigned
                    ? ['allowed' => true, 'reason' => null]
                    : ['allowed' => false, 'reason' => "ماژول {$moduleSlug} برای این سایت فعال نشده است."];
            }

            return ['allowed' => false, 'reason' => "دسترسی به ماژول {$moduleSlug} مجاز نیست."];
        } catch (\Throwable $e) {
            Log::error('module_license.check_error', ['slug' => $moduleSlug, 'error' => $e->getMessage()]);
            return ['allowed' => false, 'reason' => 'خطا در بررسی مجوز دسترسی.'];
        }
    }

    public function logDenial(
        SiteModelInterface $site,
        string $moduleSlug,
        string $context,
        string $reason,
        array $requestContext = []
    ): void {
        Log::warning('module_license.denial', array_merge([
            'site_id'   => $site->getId(),
            'module'    => $moduleSlug,
            'context'   => $context,
            'reason'    => $reason,
        ], $requestContext));
    }
}
