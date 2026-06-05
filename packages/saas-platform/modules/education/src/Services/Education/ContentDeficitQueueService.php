<?php

namespace SaaSPlatform\Modules\Education\Services\Education;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Contracts\Services\ContentDeficitQueueServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\ContentReadinessServiceInterface;
use SaaSPlatform\Modules\Education\Dto\ContentDeficitQueueItemDto;

/**
 * Builds an in-memory content-deficit queue from ContentReadinessService output.
 *
 * Priority rules (by readiness_score):
 *   < 25  → Critical
 *   < 50  → High
 *   < 75  → Medium
 *   >= 75 → Low
 *
 * Each missing-content label maps to a deficit_type.
 * Items ordered ascending by readiness_score (most urgent first).
 */
class ContentDeficitQueueService implements ContentDeficitQueueServiceInterface
{
    // Patterns that identify each deficit type from the Persian missing_content labels
    private const DEFICIT_PATTERNS = [
        self::TYPE_ARTICLES       => 'مقاله',
        self::TYPE_WALKTHROUGHS   => 'walkthrough',
        self::TYPE_LEARNING_PATHS => 'مسیر یادگیری',
        self::TYPE_FRESHNESS      => 'مستندات',
    ];

    public function __construct(
        private readonly ContentReadinessServiceInterface $readinessService,
    ) {}

    // -----------------------------------------------------------------------
    // Public interface
    // -----------------------------------------------------------------------

    public function build(?int $siteId): Collection
    {
        $modules = $this->readinessService->evaluate($siteId);
        $queue   = collect();

        foreach ($modules as $module) {
            $priority = $this->priorityFromScore($module['readiness_score']);

            foreach ($module['missing_content'] as $label) {
                $deficitType = $this->resolveDeficitType($label);

                $queue->push(new ContentDeficitQueueItemDto(
                    moduleSlug:     $module['module_slug'],
                    deficitType:    $deficitType,
                    readinessScore: $module['readiness_score'],
                    priority:       $priority,
                ));
            }
        }

        // Lowest readiness first (most urgent)
        return $queue->sortBy(fn(ContentDeficitQueueItemDto $item) => $item->readinessScore)->values();
    }

    // -----------------------------------------------------------------------

    private function priorityFromScore(float $score): string
    {
        return match (true) {
            $score < self::THRESHOLD_CRITICAL => 'Critical',
            $score < self::THRESHOLD_HIGH     => 'High',
            $score < self::THRESHOLD_MEDIUM   => 'Medium',
            default                           => 'Low',
        };
    }

    private function resolveDeficitType(string $label): string
    {
        foreach (self::DEFICIT_PATTERNS as $type => $pattern) {
            if (str_contains($label, $pattern)) {
                return $type;
            }
        }
        return self::TYPE_ARTICLES;
    }
}
