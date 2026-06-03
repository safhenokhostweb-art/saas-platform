<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use SaaSPlatform\Modules\Education\Models\EducationArticle;

interface EducationServiceInterface
{
    public function find(int $id): ?EducationArticle;
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator;
    public function create(array $data): EducationArticle;
    public function update(EducationArticle $article, array $data): EducationArticle;
    public function delete(EducationArticle $article): void;
    public function publish(EducationArticle $article): EducationArticle;
    public function archive(EducationArticle $article): EducationArticle;
}
