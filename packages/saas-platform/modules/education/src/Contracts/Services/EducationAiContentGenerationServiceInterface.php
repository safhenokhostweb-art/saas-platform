<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;

interface EducationAiContentGenerationServiceInterface
{
    /**
     * Build generation jobs from the content-deficit queue for a site
     * and call the configured AI provider for each job.
     *
     * Returns a Collection of GeneratedContentDto — one per successful
     * generation. Failed or skipped items are omitted (logged separately).
     *
     * This method does NOT persist anything to the database.
     *
     * @return Collection<int, GeneratedContentDto>
     */
    public function generateFromDeficits(?int $siteId, ?string $moduleSlug = null): Collection;

    /**
     * Build generation jobs from the gap queue for a site and call
     * the configured AI provider for remediation content.
     *
     * Returns a Collection of GeneratedContentDto.
     * Does NOT persist.
     *
     * @return Collection<int, GeneratedContentDto>
     */
    public function generateFromGaps(?int $siteId, ?string $moduleSlug = null): Collection;

    /**
     * Execute a single generation job through the provider and validate the result.
     *
     * @throws \SaaSPlatform\Modules\Education\Exceptions\AiProviderException
     * @throws \SaaSPlatform\Modules\Education\Exceptions\InvalidGeneratedContentException
     */
    public function executeJob(ContentGenerationJobDto $job): GeneratedContentDto;

    /**
     * Returns true when the underlying AI provider is enabled.
     */
    public function isProviderEnabled(): bool;
}
