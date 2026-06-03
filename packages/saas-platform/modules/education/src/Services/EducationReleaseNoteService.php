<?php

namespace SaaSPlatform\Modules\Education\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use SaaSPlatform\Modules\Education\Models\EducationArticleRevision;
use SaaSPlatform\Modules\Education\Models\EducationReleaseNote;

class EducationReleaseNoteService
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return EducationReleaseNote::with('revision.article')
            ->when(! empty($filters['module_slug']), fn($q) => $q->where('module_slug', $filters['module_slug']))
            ->when(! empty($filters['site_id']), fn($q) => $q->forSite($filters['site_id']))
            ->when(! empty($filters['release_type']), fn($q) => $q->where('release_type', $filters['release_type']))
            ->when(isset($filters['is_published']), fn($q) => $q->where('is_published', $filters['is_published']))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function find(int $id): ?EducationReleaseNote
    {
        return EducationReleaseNote::with('revision.article')->find($id);
    }

    public function create(array $data): EducationReleaseNote
    {
        if (($data['is_published'] ?? false) && empty($data['published_at'])) {
            $data['published_at'] = now();
        }
        return EducationReleaseNote::create($data);
    }

    public function update(EducationReleaseNote $note, array $data): EducationReleaseNote
    {
        if (($data['is_published'] ?? false) && ! $note->published_at) {
            $data['published_at'] = now();
        }
        $note->update($data);
        return $note->fresh('revision');
    }

    public function delete(EducationReleaseNote $note): void
    {
        $note->delete();
    }

    public function linkRevision(EducationReleaseNote $note, EducationArticleRevision $revision): EducationReleaseNote
    {
        $note->update(['revision_id' => $revision->id]);
        return $note->fresh('revision');
    }
}
