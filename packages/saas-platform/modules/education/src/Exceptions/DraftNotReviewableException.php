<?php

namespace SaaSPlatform\Modules\Education\Exceptions;

use RuntimeException;
use SaaSPlatform\Modules\Education\Models\EducationArticle;

/**
 * Thrown when a review action is attempted on an article that is not reviewable.
 * An article is reviewable only when auto_generated=true and status='draft'.
 *
 * HTTP callers: catch and return 422 Unprocessable Entity.
 */
class DraftNotReviewableException extends RuntimeException
{
    public function __construct(EducationArticle $article)
    {
        parent::__construct(sprintf(
            'Article #%d cannot be reviewed: auto_generated=%s, status=%s. ' .
            'Only auto-generated articles with status=draft are reviewable.',
            $article->id,
            $article->auto_generated ? 'true' : 'false',
            $article->status,
        ));
    }
}
