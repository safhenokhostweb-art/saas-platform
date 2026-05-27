<?php

namespace SaaSPlatform\Modules\Forms\Services;

use SaaSPlatform\Modules\Forms\Models\Form;
use SaaSPlatform\Modules\Forms\Models\FormField;
use Illuminate\Support\Str;

class FormBuilderService
{
    /**
     * Serialize form data for the Alpine.js builder component.
     */
    public function serializeForBuilder(Form $form): array
    {
        $form->load('fields');

        return [
            'id'          => $form->id,
            'title'       => $form->title,
            'slug'        => $form->slug,
            'is_active'   => $form->is_active,
            'step_count'  => $form->step_count ?? 1,
            'settings'    => $form->settings ?? [],
            'fields'      => $form->fields->map(fn($f) => $this->serializeField($f))->values()->toArray(),
        ];
    }

    public function serializeField(FormField $field): array
    {
        return [
            'id'               => $field->id,
            'type'             => $field->type,
            'label'            => $field->label,
            'name'             => $field->name,
            'placeholder'      => $field->placeholder ?? '',
            'help_text'        => $field->help_text ?? '',
            'is_required'      => (bool) $field->is_required,
            'is_active'        => (bool) $field->is_active,
            'options'          => $field->options ?? [],
            'conditions'       => $field->conditions ?? [],
            'section_id'       => $field->section_id ?? null,
            'column_span'      => $field->column_span ?? 12,
            'default_value'    => $field->default_value ?? '',
            'css_class'        => $field->css_class ?? '',
            'validation_rules' => $field->validation_rules ?? [],
            'position'         => $field->position ?? 0,
        ];
    }

    public function createField(Form $form, array $data): FormField
    {
        $maxPosition = $form->fields()->max('position') ?? -1;

        return FormField::create([
            'form_id'          => $form->id,
            'type'             => $data['type'],
            'label'            => $data['label'] ?? $this->defaultLabel($data['type']),
            'name'             => $data['name'] ?? $this->generateName($form, $data['type']),
            'placeholder'      => $data['placeholder'] ?? null,
            'help_text'        => $data['help_text'] ?? null,
            'is_required'      => $data['is_required'] ?? false,
            'is_active'        => true,
            'options'          => $data['options'] ?? null,
            'conditions'       => $data['conditions'] ?? null,
            'section_id'       => $data['section_id'] ?? null,
            'column_span'      => $data['column_span'] ?? 12,
            'default_value'    => $data['default_value'] ?? null,
            'css_class'        => $data['css_class'] ?? null,
            'validation_rules' => $data['validation_rules'] ?? null,
            'position'         => $data['position'] ?? ($maxPosition + 1),
        ]);
    }

    public function updateField(FormField $field, array $data): FormField
    {
        $allowed = [
            'label', 'name', 'placeholder', 'help_text', 'is_required',
            'is_active', 'options', 'conditions', 'section_id', 'column_span',
            'default_value', 'css_class', 'validation_rules', 'position',
        ];

        $field->update(array_intersect_key($data, array_flip($allowed)));
        return $field->fresh();
    }

    public function sortFields(Form $form, array $orderedIds): void
    {
        foreach ($orderedIds as $position => $id) {
            FormField::where('id', $id)
                ->where('form_id', $form->id)
                ->update(['position' => $position]);
        }
    }

    public function updateFormSettings(Form $form, array $settings): Form
    {
        $current = $form->settings ?? [];
        $form->update([
            'settings'   => array_merge($current, $settings),
            'title'      => $settings['title'] ?? $form->title,
            'slug'       => $settings['slug'] ?? $form->slug,
            'is_active'  => $settings['is_active'] ?? $form->is_active,
            'step_count' => $settings['step_count'] ?? $form->step_count,
        ]);

        return $form->fresh();
    }

    private function defaultLabel(string $type): string
    {
        return match ($type) {
            'text'           => 'فیلد متنی',
            'textarea'       => 'متن بلند',
            'email'          => 'ایمیل',
            'number'         => 'عدد',
            'phone'          => 'تلفن',
            'date'           => 'تاریخ',
            'select'         => 'لیست انتخابی',
            'radio'          => 'رادیو',
            'checkbox'       => 'چک‌باکس',
            'rating'         => 'امتیاز',
            'section_header' => 'بخش جدید',
            default          => 'فیلد جدید',
        };
    }

    private function generateName(Form $form, string $type): string
    {
        $base     = Str::slug($this->defaultLabel($type), '_');
        $count    = $form->fields()->where('type', $type)->count() + 1;
        return "{$base}_{$count}";
    }

    public function duplicateField(FormField $field): FormField
    {
        $maxPosition = $field->form->fields()->max('position') ?? 0;

        return FormField::create([
            'form_id'          => $field->form_id,
            'type'             => $field->type,
            'label'            => $field->label . ' (کپی)',
            'name'             => $field->name . '_copy_' . Str::random(4),
            'placeholder'      => $field->placeholder,
            'help_text'        => $field->help_text,
            'is_required'      => $field->is_required,
            'is_active'        => $field->is_active,
            'options'          => $field->options,
            'conditions'       => $field->conditions,
            'section_id'       => $field->section_id,
            'column_span'      => $field->column_span ?? 12,
            'default_value'    => $field->default_value,
            'css_class'        => $field->css_class,
            'validation_rules' => $field->validation_rules,
            'position'         => $maxPosition + 1,
        ]);
    }
}
