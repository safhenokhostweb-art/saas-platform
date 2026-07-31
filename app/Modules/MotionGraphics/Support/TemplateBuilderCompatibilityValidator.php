<?php

namespace App\Modules\MotionGraphics\Support;

use App\Modules\MotionGraphics\DTO\LicenseCompatibilityDecision;
use App\Modules\MotionGraphics\DTO\MotionAssetReferenceDTO;
use App\Modules\MotionGraphics\DTO\PerformanceBudgetDecision;
use App\Modules\MotionGraphics\DTO\ResponsiveSupportDecision;
use App\Modules\MotionGraphics\DTO\ValidationReport;

/**
 * The richer, named-dimension validation gate task scope item 4 asks
 * for — asset type, section, industry, theme, performance budget,
 * responsive support, license status — run once per required asset
 * before a package activation is accepted. See
 * docs/motion-runtime-installation/TEMPLATE_ACTIVATION_MODEL.md.
 *
 * `asset_type` and `theme` are checked directly against the mapped
 * `MotionAssetReferenceDTO->compatibility` allowlist (from
 * ReadModelMapper); `section`/`industry` are checked as tag membership,
 * since that is how classification is actually expressed today (see
 * docs/motion-read-contracts/ASSET_READ_CONTRACT.md's `compatibility`
 * shape). Performance budget, responsive support, and license all
 * remain honest, explicit stubs (see their DTOs) until a read contract
 * exposes real data for them — this validator never fabricates a pass
 * or fail for data it does not have.
 */
final class TemplateBuilderCompatibilityValidator
{
    /**
     * @param array<string, mixed> $rules
     */
    public function validate(MotionAssetReferenceDTO $asset, array $rules): ValidationReport
    {
        $errors = [];

        if (isset($rules['asset_type']) && $rules['asset_type'] !== $asset->type) {
            $errors[] = [
                'code' => 'asset_type_mismatch',
                'message' => "Asset type [{$asset->type}] does not satisfy the required type [{$rules['asset_type']}].",
            ];
        }

        foreach (['section', 'industry'] as $tagDimension) {
            if (! isset($rules[$tagDimension])) {
                continue;
            }
            $tags = $asset->compatibility['tags'] ?? [];
            if (! in_array($rules[$tagDimension], is_array($tags) ? $tags : [], true)) {
                $errors[] = [
                    'code' => $tagDimension.'_mismatch',
                    'message' => "Asset does not carry the required {$tagDimension} tag [{$rules[$tagDimension]}].",
                ];
            }
        }

        if (($rules['theme_tokens_required'] ?? false) === true && ($asset->compatibility['theme_support'] ?? false) !== true) {
            $errors[] = ['code' => 'theme_mismatch', 'message' => 'Asset does not support theme tokens.'];
        }

        $performance = PerformanceBudgetDecision::notImplemented();
        if (! $performance->allowed) {
            $errors[] = ['code' => $performance->code, 'message' => $performance->reason];
        }

        $responsive = ResponsiveSupportDecision::notImplemented();
        if (! $responsive->allowed) {
            $errors[] = ['code' => $responsive->code, 'message' => $responsive->reason];
        }

        $license = LicenseCompatibilityDecision::notImplemented();
        if (! $license->allowed) {
            $errors[] = ['code' => $license->code, 'message' => $license->reason];
        }

        return $errors === [] ? ValidationReport::pass() : ValidationReport::fail($errors);
    }
}
