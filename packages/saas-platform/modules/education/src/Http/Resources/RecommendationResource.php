<?php

namespace SaaSPlatform\Modules\Education\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use SaaSPlatform\Modules\Education\Models\EducationRecommendation;

/**
 * Transforms an EducationRecommendation (optionally enriched with ai_score) for the mobile SDK.
 *
 * Accepts either:
 *   - a plain EducationRecommendation model
 *   - an array: ['recommendation' => EducationRecommendation, 'ai_score' => float, 'score_breakdown' => []]
 */
class RecommendationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (is_array($this->resource)) {
            /** @var EducationRecommendation $rec */
            $rec     = $this->resource['recommendation'];
            $aiScore = $this->resource['ai_score'] ?? null;
            $breakdown = $this->resource['score_breakdown'] ?? null;
        } else {
            $rec     = $this->resource;
            $aiScore = null;
            $breakdown = null;
        }

        $payload = [
            'id'                  => $rec->id,
            'recommendation_type' => $rec->recommendation_type,
            'reason'              => $rec->reason,
            'priority'            => $rec->priority,
            'created_at'          => $rec->created_at?->toIso8601String(),
            'article'             => $rec->article ? [
                'id'          => $rec->article->id,
                'title'       => $rec->article->title,
                'slug'        => $rec->article->slug,
                'excerpt'     => $rec->article->excerpt,
                'module_slug' => $rec->article->module_slug,
            ] : null,
            'walkthrough'         => $rec->walkthrough ? [
                'id'    => $rec->walkthrough->id,
                'title' => $rec->walkthrough->title,
                'slug'  => $rec->walkthrough->slug,
            ] : null,
            'learning_path'       => $rec->learningPath ? [
                'id'    => $rec->learningPath->id,
                'title' => $rec->learningPath->title,
                'slug'  => $rec->learningPath->slug,
            ] : null,
        ];

        if ($aiScore !== null) {
            $payload['ai_score'] = $aiScore;
        }

        if ($breakdown !== null) {
            $payload['score_breakdown'] = $breakdown;
        }

        return $payload;
    }
}
