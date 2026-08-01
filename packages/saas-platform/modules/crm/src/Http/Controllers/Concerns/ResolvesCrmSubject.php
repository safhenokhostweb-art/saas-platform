<?php

namespace SaaSPlatform\Modules\Crm\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Phase 19 — Part 6: resolves the short, URL-safe subject_type alias
 * (see config('crm.subject_types')) into the real model, scoped to the
 * given site — shared by CrmActivityController/CrmTaskController/
 * CrmAttachmentController since all three attach polymorphically to the
 * same four entities.
 */
trait ResolvesCrmSubject
{
    protected function resolveSubject(string $subjectType, int $subjectId, int $siteId): Model
    {
        $map = config('crm.subject_types', []);

        if (!isset($map[$subjectType])) {
            throw new NotFoundHttpException("Unknown CRM subject type [{$subjectType}].");
        }

        /** @var Model $modelClass */
        $modelClass = $map[$subjectType];

        return $modelClass::where('site_id', $siteId)->findOrFail($subjectId);
    }
}
