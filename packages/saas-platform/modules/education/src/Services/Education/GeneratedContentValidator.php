<?php

namespace SaaSPlatform\Modules\Education\Services\Education;

use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Exceptions\InvalidGeneratedContentException;

/**
 * Validates AI-generated content before it is accepted for persistence.
 *
 * Rules:
 *   - Title required, min 5 chars, max 255 chars
 *   - Body required, min 100 chars, max 100 000 chars
 *   - No empty <section> blocks in body
 *   - Confidence score in [0, 100]
 *
 * Throws InvalidGeneratedContentException with all collected errors.
 */
class GeneratedContentValidator
{
    private const TITLE_MIN_LENGTH      =     5;
    private const TITLE_MAX_LENGTH      =   255;
    private const BODY_MIN_LENGTH       =   100;
    private const BODY_MAX_LENGTH       = 100_000;
    private const CONFIDENCE_SCORE_MIN  =   0.0;
    private const CONFIDENCE_SCORE_MAX  = 100.0;

    /**
     * @throws InvalidGeneratedContentException
     */
    public function validate(GeneratedContentDto $dto): void
    {
        $errors = [];

        // ----- Title -----
        $titleLen = mb_strlen(trim($dto->title));

        if ($titleLen === 0) {
            $errors[] = 'Title is required.';
        } elseif ($titleLen < self::TITLE_MIN_LENGTH) {
            $errors[] = "Title must be at least " . self::TITLE_MIN_LENGTH . " characters (got {$titleLen}).";
        } elseif ($titleLen > self::TITLE_MAX_LENGTH) {
            $errors[] = "Title must not exceed " . self::TITLE_MAX_LENGTH . " characters (got {$titleLen}).";
        }

        // ----- Body -----
        $bodyLen = mb_strlen(trim($dto->body));

        if ($bodyLen === 0) {
            $errors[] = 'Body is required.';
        } elseif ($bodyLen < self::BODY_MIN_LENGTH) {
            $errors[] = "Body must be at least " . self::BODY_MIN_LENGTH . " characters (got {$bodyLen}).";
        } elseif ($bodyLen > self::BODY_MAX_LENGTH) {
            $errors[] = "Body must not exceed " . self::BODY_MAX_LENGTH . " characters.";
        } else {
            // No empty <section> blocks
            if (preg_match('/<section[^>]*>\s*<\/section>/i', $dto->body)) {
                $errors[] = 'Body contains one or more empty <section> blocks.';
            }
        }

        // ----- Confidence score -----
        if ($dto->confidenceScore < self::CONFIDENCE_SCORE_MIN || $dto->confidenceScore > self::CONFIDENCE_SCORE_MAX) {
            $errors[] = "Confidence score must be between 0 and 100 (got {$dto->confidenceScore}).";
        }

        if (! empty($errors)) {
            throw new InvalidGeneratedContentException($errors);
        }
    }
}
