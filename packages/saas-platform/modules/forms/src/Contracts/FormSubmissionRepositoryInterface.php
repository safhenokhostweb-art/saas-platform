<?php

namespace SaaSPlatform\Modules\Forms\Contracts;

use SaaSPlatform\Modules\Forms\Models\FormSubmission;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface FormSubmissionRepositoryInterface
{
    public function getAll(array $filters = []): LengthAwarePaginator;

    public function getExportData(array $filters = []): Collection;

    public function getById(int $id): ?FormSubmission;

    public function create(array $data): FormSubmission;

    public function delete(FormSubmission $submission): bool;

    public function collectDataKeys($submissions): array;
}
