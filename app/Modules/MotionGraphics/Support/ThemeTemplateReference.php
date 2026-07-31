<?php

namespace App\Modules\MotionGraphics\Support;

use App\Models\ThemeTemplate;

/**
 * Phase 6 (docs/motion-integration-phase6-audit.md §4): the Template
 * Builder connection point. Derives the stable `template_reference`
 * string that MotionRuntimeInstallationService::installTemplateForSite()
 * and TemplateAssetPackageService already key on, from a real
 * App\Models\ThemeTemplate row — the platform's actual Template Builder
 * (app/Models/ThemeTemplate.php), not the standalone module's own
 * aspirational template-builder-contract docs.
 *
 * Deliberately not a new contract/interface: ThemeTemplate has no
 * knowledge of Motion assets (which motion assets a template requires is
 * an admin decision — "assign Motion packages to templates" per the task
 * — supplied separately as requiredAssetRefs), so this is pure reference
 * derivation, not business logic.
 */
final class ThemeTemplateReference
{
    private const PREFIX = 'theme-template:';

    public static function for(ThemeTemplate $themeTemplate): string
    {
        return self::PREFIX.$themeTemplate->uuid;
    }

    public static function isThemeTemplateReference(string $templateReference): bool
    {
        return str_starts_with($templateReference, self::PREFIX);
    }
}
