<?php

namespace SaaSPlatform\Modules\Forms\Contracts;

use SaaSPlatform\Modules\Forms\Models\Form;
use Illuminate\Pagination\LengthAwarePaginator;

interface FormServiceInterface
{
    public function getAll(array $filters = []): LengthAwarePaginator;

    public function getById(int $id): ?Form;

    public function create(array $data): Form;

    public function update(Form $form, array $data): Form;

    public function delete(Form $form): bool;

    public function getWithFields(int $id): ?Form;

    public function getPreviewData(Form $form): array;
}
