<?php

namespace SaaSPlatform\Modules\Forms\Services;

use App\Models\Module;
use App\Models\ModuleEntitlement;
use App\Models\Site;
use App\Services\ModuleAssignmentService;
use App\Services\ModuleLicenseService;
use Illuminate\Support\Facades\Log;

/**
 * TASK 2 — Licensing Enforcement
 *
 * Centralized licensing enforcement for the Forms module.
 * Integrates with the existing ModuleLicenseService and ModuleAssignmentService
 * to validate that a tenant has valid entitlement before accessing Forms.
 *
 * Rules:
 * - Core modules (type=core): always allowed
 * - Paid modules (type=paid): must have valid license
 * - Free-assignable modules: must be assigned via site_modules
 * - License-only modules: must have valid license
 * - Retired modules: new assignments blocked, existing preserved
 */
class FormsLicensingService
{
    /**
     * The module slug for Forms.
     */
    public const MODULE_SLUG = 'forms';

    public function __construct(
        protected ModuleAssignmentService $assignmentService,
        protected ModuleLicenseService $licenseService
    ) {}

    /**
     * Check if a site has valid entitlement for the Forms module.
     *
     * @param Site $site The tenant site to check
     * @return array{allowed: bool, reason: ?string}
     */
    public function checkEntitlement(Site $site): array
    {
        try {
            $module = Module::where('slug', self::MODULE_SLUG)->first();

            if (!$module) {
                Log::warning('forms.licensing.module_not_found', [
                    'site_id' => $site->id,
                    'slug' => self::MODULE_SLUG,
                ]);
                return [
                    'allowed' => false,
                    'reason' => 'ماژول فرم‌ها در سیستم ثبت نشده است.',
                ];
            }

            // Retired modules: block new access, preserve existing
            if ($module->isRetired()) {
                Log::info('forms.licensing.retired', [
                    'site_id' => $site->id,
                    'module_id' => $module->id,
                ]);
                return [
                    'allowed' => false,
                    'reason' => 'ماژول فرم‌ها بازنشسته شده است.',
                ];
            }

            // Disabled modules: block all access
            if ($module->isDistributionDisabled()) {
                return [
                    'allowed' => false,
                    'reason' => 'ماژول فرم‌ها غیرفعال است.',
                ];
            }

            // Core modules: always allowed
            if ($module->isCore()) {
                return [
                    'allowed' => true,
                    'reason' => null,
                ];
            }

            // Paid marketplace modules: must have valid license
            if ($module->isPaidMarketplace()) {
                $hasLicense = $this->licenseService->hasValidLicense($site, $module->id);
                if (!$hasLicense) {
                    Log::info('forms.licensing.paid_denied', [
                        'site_id' => $site->id,
                        'module_id' => $module->id,
                    ]);
                    return [
                        'allowed' => false,
                        'reason' => 'لایسنس معتبر برای ماژول فرم‌ها یافت نشد.',
                    ];
                }
                return [
                    'allowed' => true,
                    'reason' => null,
                ];
            }

            // Free-assignable modules: must be assigned via site_modules
            if ($module->isFreeAssignable()) {
                $isAssigned = $this->assignmentService->isModuleEnabledForSite($site, self::MODULE_SLUG);
                if (!$isAssigned) {
                    Log::info('forms.licensing.free_denied', [
                        'site_id' => $site->id,
                        'module_id' => $module->id,
                    ]);
                    return [
                        'allowed' => false,
                        'reason' => 'ماژول فرم‌ها برای این سایت فعال نشده است.',
                    ];
                }
                return [
                    'allowed' => true,
                    'reason' => null,
                ];
            }

            // License-only modules: must have valid license
            if ($module->isLicenseOnly()) {
                $hasLicense = $this->licenseService->hasValidLicense($site, $module->id);
                if (!$hasLicense) {
                    Log::info('forms.licensing.license_only_denied', [
                        'site_id' => $site->id,
                        'module_id' => $module->id,
                    ]);
                    return [
                        'allowed' => false,
                        'reason' => 'لایسنس معتبر برای ماژول فرم‌ها یافت نشد.',
                    ];
                }
                return [
                    'allowed' => true,
                    'reason' => null,
                ];
            }

            // Fallback: deny
            return [
                'allowed' => false,
                'reason' => 'دسترسی به ماژول فرم‌ها مجاز نیست.',
            ];
        } catch (\Throwable $e) {
            Log::error('forms.licensing.check_error', [
                'site_id' => $site->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'allowed' => false,
                'reason' => 'خطا در بررسی مجوز دسترسی.',
            ];
        }
    }

    /**
     * Quick boolean check for route/controller gating.
     */
    public function isAllowed(Site $site): bool
    {
        $result = $this->checkEntitlement($site);
        return $result['allowed'];
    }

    /**
     * Log an entitlement denial event.
     */
    public function logDenial(Site $site, string $context, string $reason): void
    {
        Log::warning('forms.licensing.denial', [
            'site_id' => $site->id,
            'context' => $context,
            'reason' => $reason,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
        ]);
    }
}
