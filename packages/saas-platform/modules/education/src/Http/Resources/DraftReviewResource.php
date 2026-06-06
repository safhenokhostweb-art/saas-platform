<?php

namespace SaaSPlatform\Modules\Education\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for an auto-generated draft article in the review queue.
 *
 * Used by:
 *   GET  /api/v1/education/drafts
 *   POST /api/v1/education/drafts/{id}/approve
 *   POST /api/v1/education/drafts/{id}/reject
 */
class DraftReviewResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'title'          => $this->title,
            'excerpt'        => $this->excerpt,
            'status'         => $this->status,
            'module_slug'    => $this->module_slug,
            'content_type'   => $this->content_type,
            'site_id'        => $this->site_id,
            'auto_generated' => $this->auto_generated,
            'is_reviewable'  => $this->isReviewable(),
            'has_been_reviewed' => $this->hasBeenReviewed(),
            'meta'           => $this->meta,
            // Review audit
            'reviewed_by_id' => $this->reviewed_by_id,
            'reviewed_at'    => $this->reviewed_at?->toIso8601String(),
            'review_note'    => $this->review_note,
            // Timestamps
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
