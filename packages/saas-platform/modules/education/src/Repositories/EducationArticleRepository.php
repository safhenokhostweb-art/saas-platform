<?php

namespace SaaSPlatform\Modules\Education\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationArticleRepositoryInterface;
use SaaSPlatform\Modules\Education\Models\EducationArticle;

class EducationArticleRepository implements EducationArticleRepositoryInterface
{
    public function find(int $id): ?EducationArticle
    {
        return EducationArticle::with('category', 'author')->find($id);
    }

    public function findBySlug(string $slug): ?EducationArticle
    {
        return EducationArticle::with('category', 'author', 'media')
            ->where('slug', $slug)
            ->first();
    }

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return EducationArticle::with('category', 'author')
            ->when(! empty($filters['search']), fn($q) => $q->search($filters['search']))
            ->when(! empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['category_id']), fn($q) => $q->where('edu_category_id', $filters['category_id']))
            ->when(! empty($filters['module_slug']), fn($q) => $q->forModule($filters['module_slug']))
            ->when(! empty($filters['content_type']), fn($q) => $q->where('content_type', $filters['content_type']))
            ->when(! empty($filters['site_id']), fn($q) => $q->forSite($filters['site_id']))
            ->when(! empty($filters['role']), fn($q) => $q->forRole($filters['role']))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): EducationArticle
    {
        return EducationArticle::create($data);
    }

    public function update(EducationArticle $article, array $data): EducationArticle
    {
        $article->update($data);
        return $article->fresh(['category', 'author']);
    }

    public function delete(EducationArticle $article): void
    {
        $article->delete();
    }

    public function search(string $term, ?int $siteId, ?string $role, ?string $moduleSlug): Collection
    {
        return EducationArticle::published()
            ->when($siteId !== null, fn($q) => $q->forSite($siteId))
            ->when($role, fn($q) => $q->forRole($role))
            ->when($moduleSlug, fn($q) => $q->forModule($moduleSlug))
            ->where(function ($q) use ($term) {
                $q->whereFullText(['title', 'excerpt'], $term)
                  ->orWhere('title', 'like', "%{$term}%");
            })
            ->with('category')
            ->limit(20)
            ->get();
    }

    public function forRoute(string $routePattern, ?int $siteId, ?string $role): Collection
    {
        return EducationArticle::published()
            ->forRoute($routePattern)
            ->when($siteId !== null, fn($q) => $q->forSite($siteId))
            ->when($role, fn($q) => $q->forRole($role))
            ->with('category')
            ->limit(5)
            ->get();
    }

    public function forModule(string $moduleSlug, ?int $siteId): Collection
    {
        return EducationArticle::published()
            ->forModule($moduleSlug)
            ->when($siteId !== null, fn($q) => $q->forSite($siteId))
            ->with('category')
            ->get();
    }

    private function buildSearch(string $term): array
    {
        // Normalize Persian/Arabic characters for better matching
        return [str_replace(['ي', 'ك'], ['ی', 'ک'], $term)];
    }
}
