<?php

namespace SaaSPlatform\Modules\Forms\Services;

use SaaSPlatform\Modules\Forms\Models\Form;
use SaaSPlatform\Modules\Forms\Models\FormField;
use SaaSPlatform\Modules\Forms\Models\FormTemplate;
use Illuminate\Database\Eloquent\Collection;

class FormTemplateService
{
    public function getAll(?int $siteId = null): Collection
    {
        return FormTemplate::query()
            ->when($siteId, fn($q) => $q->where(
                fn($q2) => $q2->where('is_public', true)->orWhere('site_id', $siteId)
            ), fn($q) => $q->where('is_public', true))
            ->orderBy('category')
            ->orderBy('title')
            ->get();
    }

    public function createFromForm(Form $form, array $data): FormTemplate
    {
        $form->load('fields');

        $schema = [
            'title'  => $form->title,
            'fields' => $form->fields->map(fn($f) => [
                'type'             => $f->type,
                'label'            => $f->label,
                'name'             => $f->name,
                'placeholder'      => $f->placeholder,
                'help_text'        => $f->help_text,
                'is_required'      => $f->is_required,
                'options'          => $f->options,
                'column_span'      => $f->column_span ?? 12,
                'validation_rules' => $f->validation_rules,
            ])->values()->toArray(),
        ];

        return FormTemplate::create([
            'title'       => $data['title'] ?? $form->title . ' (قالب)',
            'description' => $data['description'] ?? null,
            'category'    => $data['category'] ?? 'general',
            'schema'      => $schema,
            'is_public'   => $data['is_public'] ?? false,
            'site_id'     => $data['site_id'] ?? null,
        ]);
    }

    public function applyToForm(FormTemplate $template, Form $form): void
    {
        $schema = $template->schema;
        $fields = $schema['fields'] ?? [];

        $form->fields()->delete();

        foreach ($fields as $index => $fieldData) {
            FormField::create([
                'form_id'          => $form->id,
                'type'             => $fieldData['type'],
                'label'            => $fieldData['label'],
                'name'             => $fieldData['name'] ?? \Illuminate\Support\Str::slug($fieldData['label'], '_'),
                'placeholder'      => $fieldData['placeholder'] ?? null,
                'help_text'        => $fieldData['help_text'] ?? null,
                'is_required'      => $fieldData['is_required'] ?? false,
                'is_active'        => true,
                'options'          => $fieldData['options'] ?? null,
                'position'         => $index,
                'column_span'      => $fieldData['column_span'] ?? 12,
                'validation_rules' => $fieldData['validation_rules'] ?? null,
            ]);
        }

        $form->update(['template_id' => $template->id]);
    }

    public function delete(FormTemplate $template): void
    {
        $template->delete();
    }

    public function getCategories(): array
    {
        return [
            'general'  => 'عمومی',
            'contact'  => 'تماس با ما',
            'survey'   => 'نظرسنجی',
            'register' => 'ثبت‌نام',
            'support'  => 'پشتیبانی',
            'order'    => 'سفارش',
        ];
    }

    public function getBuiltInTemplates(): array
    {
        return [
            [
                'title'       => 'فرم تماس ساده',
                'description' => 'نام، ایمیل، موضوع و پیام',
                'category'    => 'contact',
                'fields'      => [
                    ['type' => 'text',     'label' => 'نام و نام خانوادگی', 'name' => 'full_name',    'is_required' => true],
                    ['type' => 'email',    'label' => 'ایمیل',              'name' => 'email',         'is_required' => true],
                    ['type' => 'text',     'label' => 'موضوع',              'name' => 'subject',       'is_required' => true],
                    ['type' => 'textarea', 'label' => 'پیام',               'name' => 'message',       'is_required' => true],
                ],
            ],
            [
                'title'       => 'فرم نظرسنجی',
                'description' => 'رضایت‌سنجی با امتیاز و نظر',
                'category'    => 'survey',
                'fields'      => [
                    ['type' => 'radio',    'label' => 'رضایت کلی',          'name' => 'satisfaction',  'is_required' => true,  'options' => ['خیلی راضی', 'راضی', 'بی‌طرف', 'ناراضی', 'خیلی ناراضی']],
                    ['type' => 'textarea', 'label' => 'پیشنهادات و انتقادات', 'name' => 'feedback',   'is_required' => false],
                    ['type' => 'text',     'label' => 'ایمیل (اختیاری)',    'name' => 'email',         'is_required' => false],
                ],
            ],
            [
                'title'       => 'فرم ثبت‌نام رویداد',
                'description' => 'ثبت‌نام شرکت‌کننده در رویداد',
                'category'    => 'register',
                'fields'      => [
                    ['type' => 'text',   'label' => 'نام',          'name' => 'first_name', 'is_required' => true,  'column_span' => 6],
                    ['type' => 'text',   'label' => 'نام خانوادگی', 'name' => 'last_name',  'is_required' => true,  'column_span' => 6],
                    ['type' => 'email',  'label' => 'ایمیل',        'name' => 'email',      'is_required' => true],
                    ['type' => 'phone',  'label' => 'شماره تماس',  'name' => 'phone',      'is_required' => true],
                    ['type' => 'select', 'label' => 'نوع بلیط',     'name' => 'ticket_type','is_required' => true,  'options' => ['حضوری', 'آنلاین', 'VIP']],
                ],
            ],
        ];
    }
}
