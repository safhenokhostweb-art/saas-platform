<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Models\EducationArticle;

interface EducationDraftPersistenceServiceInterface
{
    /**
     * Persist a GeneratedContentDto as a draft article and write a generation log entry.
     *
     * The article is ALWAYS stored with status='draft'. It will never be auto-published.
     * The entire operation runs inside a DB transaction.
     *
     * Returns the newly created draft EducationArticle.
     */
    public function persist(
        GeneratedContentDto    $content,
        ContentGenerationJobDto $job,
        int                    $authorId,
    ): EducationArticle;

    /**
     * Check whether an identical draft already exists for the same site + module + title.
     * Used by commands to enforce idempotency before calling persist().
     */
    public function draftExists(ContentGenerationJobDto $job, string $title): bool;

    /**
     * Write a generation failure log entry without creating any article.
     *
     * Called by commands when executeJob() throws AiProviderException
     * (status = STATUS_PROVIDER_ERROR) or InvalidGeneratedContentException
     * (status = STATUS_VALIDATION_FAILED).
     *
     * The status argument MUST be one of EducationGenerationLog::STATUS_VALIDATION_FAILED
     * or EducationGenerationLog::STATUS_PROVIDER_ERROR.
     */
    public function logFailure(
        ContentGenerationJobDto $job,
        string                  $status,
        string                  $providerName,
    ): void;
}
