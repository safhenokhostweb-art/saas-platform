<?php

namespace SaaSPlatform\Modules\Education\Services\Education;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationDraftPersistenceServiceInterface;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationGenerationLog;

/**
 * Persists AI-generated content as draft articles.
 *
 * Rules:
 *   - Status is ALWAYS 'draft' — never auto-publish.
 *   - auto_generated flag is set to true.
 *   - Site scope from the job DTO is enforced.
 *   - The entire write (article + log) is wrapped in a DB transaction.
 */
class EducationDraftPersistenceService implements EducationDraftPersistenceServiceInterface
{
    public function persist(
        GeneratedContentDto     $content,
        ContentGenerationJobDto $job,
        int                     $authorId,
    ): EducationArticle {
        return DB::transaction(function () use ($content, $job, $authorId) {
            $article = EducationArticle::create([
                'site_id'        => $job->siteId,
                'author_id'      => $authorId,
                'title'          => $content->title,
                'slug'           => Str::slug($content->title) . '-' . Str::random(6),
                'excerpt'        => $content->summary,
                'body'           => $content->body,
                'status'         => EducationArticle::STATUS_DRAFT,
                'auto_generated' => true,
                'module_slug'    => $job->moduleSlug,
                'content_type'   => $job->contentType,
                'tags'           => $content->tags,
                'meta'           => [
                    'source_reason'    => $job->sourceReason,
                    'confidence_score' => $content->confidenceScore,
                    'provider'         => $content->provider,
                ],
            ]);

            EducationGenerationLog::create([
                'site_id'          => $job->siteId,
                'module_slug'      => $job->moduleSlug,
                'content_type'     => $job->contentType,
                'provider'         => $content->provider,
                'status'           => EducationGenerationLog::STATUS_SUCCESS,
                'confidence_score' => $content->confidenceScore,
                'source_reason'    => $job->sourceReason,
                'edu_article_id'   => $article->id,
                'metadata'         => $job->metadata,
            ]);

            return $article;
        });
    }

    public function draftExists(ContentGenerationJobDto $job, string $title): bool
    {
        return EducationArticle::query()
            ->where('status', EducationArticle::STATUS_DRAFT)
            ->where('auto_generated', true)
            ->where('module_slug', $job->moduleSlug)
            ->where('title', $title)
            ->when($job->siteId !== null, fn($q) => $q->where('site_id', $job->siteId))
            ->when($job->siteId === null, fn($q) => $q->whereNull('site_id'))
            ->exists();
    }
}
