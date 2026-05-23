<?php

namespace SaaSPlatform\Modules\Forms\Contracts;

use SaaSPlatform\Modules\Forms\Models\Form;
use SaaSPlatform\Modules\Forms\Models\FormField;

interface FormFieldRepositoryInterface
{
    public function create(Form $form, array $data): FormField;

    public function update(FormField $field, array $data): FormField;

    public function delete(FormField $field): bool;

    public function toggle(FormField $field): FormField;

    public function sort(Form $form, array $positions): void;
}
