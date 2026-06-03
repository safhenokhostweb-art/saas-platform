<?php

namespace SaaSPlatform\Modules\Education\Contracts\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Models\EducationArticle;

interface EducationArticleRepositoryInterface
{
    public function find(int $id): ?EducationArticle;
    public function findBySlug(string $slug): ?EducationArticle;
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;
    public function create(array $data): EducationArticle;
    public function update(EducationArticle $article, array $data): EducationArticle;
    public function delete(EducationArticle $article): void;
    public function search(string $term, ?int $siteId, ?string $role, ?string $moduleSlug): Collection;
    public function forRoute(string $routePattern, ?int $siteId, ?string $role): Collection;
    public function forModule(string $moduleSlug, ?int $siteId): Collection;
}
