<?php

namespace App\Modules\MotionGraphics\Support;

use App\Models\Theme;

/**
 * The Theme Builder <-> Motion Graphics bridge for a full, sellable
 * marketplace Theme (as opposed to ThemeTemplateReference, which covers a
 * single reusable ThemeTemplate section/snippet). Derives the same kind of
 * stable `template_reference` string that MotionRuntimeInstallationService
 * and TemplateAssetPackageService already key on — TemplateAssetPackage's
 * own migration docblock is explicit that this column is a deliberately
 * opaque string with no foreign key into any Theme Builder table, so a
 * second reference-derivation class (not a second table) is the correct,
 * additive way to extend the existing bridge to Theme.
 *
 * Deliberately not a new contract/interface, mirroring
 * ThemeTemplateReference: which motion assets a Theme requires is an admin
 * decision, supplied separately when calling TemplateAssetPackageService /
 * MotionRuntimeInstallationService.
 */
final class ThemeReference
{
    private const PREFIX = 'theme:';

    public static function for(Theme $theme): string
    {
        return self::PREFIX.$theme->slug;
    }

    public static function isThemeReference(string $templateReference): bool
    {
        return str_starts_with($templateReference, self::PREFIX);
    }
}
