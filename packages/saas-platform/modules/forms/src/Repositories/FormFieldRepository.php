<?php

namespace SaaSPlatform\Modules\Forms\Repositories;

use SaaSPlatform\Modules\Forms\Contracts\FormFieldRepositoryInterface;
use SaaSPlatform\Modules\Forms\Models\Form;
use SaaSPlatform\Modules\Forms\Models\FormField;

class FormFieldRepository implements FormFieldRepositoryInterface
{
    public function create(Form $form, array $data): FormField
    {
        return $form->fields()->create($data);
    }

    public function update(FormField $field, array $data): FormField
    {
        $field->update($data);
        return $field->fresh();
    }

    public function delete(FormField $field): bool
    {
        return $field->delete();
    }

    public function toggle(FormField $field): FormField
    {
        $field->update(['is_active' => !$field->is_active]);
        return $field->fresh();
    }

    public function sort(Form $form, array $positions): void
    {
        foreach ($positions as $position => $id) {
            FormField::where('id', $id)->update(['position' => $position]);
        }
    }
}
