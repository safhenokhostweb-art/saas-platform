<?php

namespace SaaSPlatform\Modules\Education\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationArticleRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationServiceInterface;
use SaaSPlatform\Modules\Education\Models\EducationArticle;

class EducationArticleService implements EducationServiceInterface
{
    public function __construct(
        private readonly EducationArticleRepositoryInterface $articles,
    ) {}

    public function find(int $id): ?EducationArticle
    {
        return $this->articles->find($id);
    }

    public function findBySlug(string $slug): ?EducationArticle
    {
        return $this->articles->findBySlug($slug);
    }

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->articles->paginate($filters, $perPage);
    }

    public function create(array $data): EducationArticle
    {
        $data = $this->prepareTags($data);

        if ($data['status'] === EducationArticle::STATUS_PUBLISHED && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $this->articles->create($data);
    }

    public function update(EducationArticle $article, array $data): EducationArticle
    {
        $data = $this->prepareTags($data);

        if (
            ($data['status'] ?? null) === EducationArticle::STATUS_PUBLISHED
            && ! $article->published_at
        ) {
            $data['published_at'] = now();
        }

        return $this->articles->update($article, $data);
    }

    public function delete(EducationArticle $article): void
    {
        $this->articles->delete($article);
    }

    public function publish(EducationArticle $article): EducationArticle
    {
        return $this->articles->update($article, [
            'status'       => EducationArticle::STATUS_PUBLISHED,
            'published_at' => $article->published_at ?? now(),
        ]);
    }

    public function archive(EducationArticle $article): EducationArticle
    {
        return $this->articles->update($article, ['status' => EducationArticle::STATUS_ARCHIVED]);
    }

    public function recordView(EducationArticle $article): void
    {
        $article->incrementView();
    }

    public function recordHelpfulness(EducationArticle $article, bool $helpful): void
    {
        $article->markHelpful($helpful);
    }

    private function prepareTags(array $data): array
    {
        if (isset($data['tags_raw'])) {
            $data['tags'] = array_values(
                array_filter(array_map('trim', explode(',', $data['tags_raw'])))
            );
            unset($data['tags_raw']);
        }
        return $data;
    }
}
