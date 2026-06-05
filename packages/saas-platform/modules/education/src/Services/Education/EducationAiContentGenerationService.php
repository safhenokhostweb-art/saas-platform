<?php

namespace SaaSPlatform\Modules\Education\Services\Education;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\ContentDeficitQueueServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationAiContentGenerationServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\KnowledgeGapQueueServiceInterface;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\ContentDeficitQueueItemDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Dto\KnowledgeGapQueueItemDto;
use SaaSPlatform\Modules\Education\Exceptions\AiProviderException;
use SaaSPlatform\Modules\Education\Exceptions\InvalidGeneratedContentException;

/**
 * Orchestrates AI content generation.
 *
 * Responsibilities:
 *   - Consume ContentDeficitQueueService and KnowledgeGapQueueService
 *   - Build ContentGenerationJobDto instances
 *   - Call the configured AiContentProviderInterface
 *   - Validate results via GeneratedContentValidator
 *   - Return GeneratedContentDto collection
 *
 * This service MUST NOT:
 *   - Save anything to the database
 *   - Call any controller
 *   - Trigger recommendations
 */
class EducationAiContentGenerationService implements EducationAiContentGenerationServiceInterface
{
    public function __construct(
        private readonly AiContentProviderInterface        $provider,
        private readonly ContentDeficitQueueServiceInterface $deficitQueue,
        private readonly KnowledgeGapQueueServiceInterface   $gapQueue,
        private readonly GeneratedContentValidator           $validator,
    ) {}

    // -----------------------------------------------------------------------
    // Public interface
    // -----------------------------------------------------------------------

    public function generateFromDeficits(?int $siteId, ?string $moduleSlug = null): Collection
    {
        if (! $this->isProviderEnabled()) {
            return collect();
        }

        $queue = $this->deficitQueue->build($siteId);

        if ($moduleSlug !== null) {
            $queue = $queue->filter(fn(ContentDeficitQueueItemDto $item) => $item->moduleSlug === $moduleSlug)->values();
        }

        return $this->executeJobs(
            $queue->map(fn(ContentDeficitQueueItemDto $item) => new ContentGenerationJobDto(
                moduleSlug:   $item->moduleSlug,
                contentType:  $item->deficitType,
                priority:     $item->priority,
                siteId:       $siteId,
                sourceReason: 'content_deficit',
                metadata:     ['readiness_score' => $item->readinessScore],
            ))
        );
    }

    public function generateFromGaps(?int $siteId, ?string $moduleSlug = null): Collection
    {
        if (! $this->isProviderEnabled()) {
            return collect();
        }

        $queue = $this->gapQueue->buildForSite($siteId);

        if ($moduleSlug !== null) {
            $queue = $queue->filter(fn(KnowledgeGapQueueItemDto $item) => $item->moduleSlug === $moduleSlug)->values();
        }

        // Deduplicate by module_slug — one remediation content piece per module per run
        $deduplicated = $queue->unique(fn(KnowledgeGapQueueItemDto $item) => $item->moduleSlug . '.' . ($item->siteId ?? 'null'))->values();

        return $this->executeJobs(
            $deduplicated->map(fn(KnowledgeGapQueueItemDto $item) => new ContentGenerationJobDto(
                moduleSlug:   $item->moduleSlug,
                contentType:  'remediation',
                priority:     $item->priority,
                siteId:       $item->siteId,
                sourceReason: 'knowledge_gap',
                metadata:     ['gap_score' => $item->gapScore, 'user_id' => $item->userId],
            ))
        );
    }

    public function executeJob(ContentGenerationJobDto $job): GeneratedContentDto
    {
        $result = $this->provider->generate($job);
        $this->validator->validate($result);
        return $result;
    }

    public function isProviderEnabled(): bool
    {
        return $this->provider->isEnabled();
    }

    // -----------------------------------------------------------------------

    /**
     * @param  Collection<int, ContentGenerationJobDto> $jobs
     * @return Collection<int, GeneratedContentDto>
     */
    private function executeJobs(Collection $jobs): Collection
    {
        $results = collect();

        foreach ($jobs as $job) {
            try {
                $results->push($this->executeJob($job));
            } catch (InvalidGeneratedContentException $e) {
                Log::warning('EDU-15: generated content failed validation', [
                    'module'  => $job->moduleSlug,
                    'reason'  => $job->sourceReason,
                    'errors'  => $e->getErrors(),
                ]);
            } catch (AiProviderException $e) {
                Log::error('EDU-15: AI provider error', [
                    'module'   => $job->moduleSlug,
                    'provider' => $this->provider->providerName(),
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }
}
