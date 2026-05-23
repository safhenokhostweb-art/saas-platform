<?php

namespace SaaSPlatform\Modules\Forms\Services;

use SaaSPlatform\Modules\Forms\Contracts\FormFieldRepositoryInterface;
use SaaSPlatform\Modules\Forms\Models\Form;
use SaaSPlatform\Modules\Forms\Models\FormField;

class FormFieldService
{
    public function __construct(
        protected FormFieldRepositoryInterface $repository
    ) {}

    public function create(Form $form, array $data): FormField
    {
        $data['is_required'] = !empty($data['is_required']);
        $data['is_active'] = true;
        $data['position'] = $form->fields()->count() + 1;

        // Parse options for select/radio/checkbox
        if (in_array($data['type'] ?? '', ['select', 'radio', 'checkbox'], true) && !empty($data['options_raw'])) {
            $lines = preg_split('/\r\n|\r|\n/', (string) $data['options_raw']) ?: [];
            $data['options'] = array_values(array_filter(array_map('trim', $lines), fn ($p) => $p !== ''));
            unset($data['options_raw']);
        }

        return $this->repository->create($form, $data);
    }

    public function delete(FormField $field): bool
    {
        return $this->repository->delete($field);
    }

    public function toggle(FormField $field): FormField
    {
        return $this->repository->toggle($field);
    }

    public function sort(Form $form, array $positions): void
    {
        $this->repository->sort($form, $positions);
    }
}
