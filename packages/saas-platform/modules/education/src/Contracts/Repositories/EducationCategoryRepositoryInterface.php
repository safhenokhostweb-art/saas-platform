<?php

namespace SaaSPlatform\Modules\Education\Contracts\Repositories;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Models\EducationCategory;

interface EducationCategoryRepositoryInterface
{
    public function all(?int $siteId = null): Collection;
    public function roots(?int $siteId = null): Collection;
    public function find(int $id): ?EducationCategory;
    public function create(array $data): EducationCategory;
    public function update(EducationCategory $category, array $data): EducationCategory;
    public function delete(EducationCategory $category): void;
}
