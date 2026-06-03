<?php

namespace SaaSPlatform\Modules\Education\Services;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Models\EducationArticle;

class EducationCoverageTracker
{
    public function report(): array
    {
        $manifests = $this->loadModuleSlugs();
        $covered   = EducationArticle::published()
            ->whereNotNull('module_slug')
            ->pluck('module_slug')
            ->unique()
            ->values()
            ->toArray();

        $uncovered = array_values(array_diff($manifests, $covered));
        $total     = count($manifests);
        $coveredN  = count(array_intersect($manifests, $covered));

        return [
            'total_modules'   => $total,
            'covered'         => $coveredN,
            'uncovered'       => count($uncovered),
            'coverage_pct'    => $total > 0 ? round($coveredN / $total * 100, 1) : 100,
            'uncovered_slugs' => $uncovered,
        ];
    }

    private function loadModuleSlugs(): array
    {
        $slugs = [];

        foreach (config() as $key => $value) {
            if (str_starts_with($key, 'module-manifest-') && is_array($value)) {
                $slug = $value['module']['slug'] ?? null;
                if ($slug) {
                    $slugs[] = $slug;
                }
            }
        }

        return array_unique($slugs);
    }
}
