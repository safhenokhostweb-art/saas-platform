<?php

namespace SaaSPlatform\Modules\Education\Services\Education;

use Illuminate\Database\QueryException;
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
 *
 * RC-01 protection — two-layer duplicate guard:
 *   Layer 1 (application): lockForUpdate() re-check inside the transaction.
 *              Guards the null-site_id case (where the DB unique constraint
 *              cannot protect due to MySQL NULL semantics) and provides
 *              defense-in-depth for non-null sites.
 *   Layer 2 (database): UNIQUE index on (title, module_slug, site_id, auto_generated).
 *              Primary guard for non-null site_id rows. Violation is caught
 *              and handled idempotently — the existing draft is returned.
 */
class EducationDraftPersistenceService implements EducationDraftPersistenceServiceInterface
{
    public function persist(
        GeneratedContentDto     $content,
        ContentGenerationJobDto $job,
        int                     $authorId,
    ): EducationArticle {
        try {
            return DB::transaction(function () use ($content, $job, $authorId) {
                // Layer 1: re-check with write lock inside the transaction.
                // Prevents phantom inserts for null-site_id rows and adds
                // defense-in-depth for the non-null case.
                $existing = EducationArticle::query()
                    ->where('status', EducationArticle::STATUS_DRAFT)
                    ->where('auto_generated', true)
                    ->where('module_slug', $job->moduleSlug)
                    ->where('title', $content->title)
                    ->when($job->siteId !== null, fn($q) => $q->where('site_id', $job->siteId))
                    ->when($job->siteId === null, fn($q) => $q->whereNull('site_id'))
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }

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
        } catch (QueryException $e) {
            // Layer 2: unique constraint violation (MySQL 1062) — a concurrent
            // process beat us to the insert. Return the existing draft so the
            // caller remains idempotent.
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }

            return EducationArticle::query()
                ->where('status', EducationArticle::STATUS_DRAFT)
                ->where('auto_generated', true)
                ->where('module_slug', $job->moduleSlug)
                ->where('title', $content->title)
                ->when($job->siteId !== null, fn($q) => $q->where('site_id', $job->siteId))
                ->when($job->siteId === null, fn($q) => $q->whereNull('site_id'))
                ->firstOrFail();
        }
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

    public function logFailure(
        ContentGenerationJobDto $job,
        string                  $status,
        string                  $providerName,
    ): void {
        EducationGenerationLog::create([
            'site_id'       => $job->siteId,
            'module_slug'   => $job->moduleSlug,
            'content_type'  => $job->contentType,
            'provider'      => $providerName,
            'status'        => $status,
            'source_reason' => $job->sourceReason,
            'metadata'      => $job->metadata,
            // confidence_score and edu_article_id intentionally null on failure
        ]);
    }
}
