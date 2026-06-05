<?php

namespace SaaSPlatform\Modules\Education\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the combined education analytics payload for the API response.
 *
 * Input ($this->resource) is expected to be an array with keys:
 *   profile, recommendations, gaps, readiness, analytics
 */
class EducationAnalyticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource;

        return [
            'profile'         => $data['profile']         ?? [],
            'recommendations' => $data['recommendations'] ?? [],
            'gaps'            => $data['gaps']            ?? [],
            'readiness'       => $data['readiness']       ?? [],
            'analytics'       => $data['analytics']       ?? [],
        ];
    }
}
