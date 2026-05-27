<?php

namespace SaaSPlatform\Modules\Forms\Http\Controllers;

use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\Http\BaseController as Controller;
use SaaSPlatform\Modules\Forms\Models\Form;
use SaaSPlatform\Modules\Forms\Models\FormField;
use SaaSPlatform\Modules\Forms\Services\FormBuilderService;
use SaaSPlatform\Modules\Forms\Services\FormConditionalLogicService;
use SaaSPlatform\Modules\Forms\Services\FormTemplateService;

class FormBuilderController extends Controller
{
    public function __construct(
        protected FormBuilderService          $builderService,
        protected FormConditionalLogicService $logicService,
        protected FormTemplateService         $templateService,
    ) {}

    public function show(Form $form)
    {
        $formData  = $this->builderService->serializeForBuilder($form);
        $templates = $this->templateService->getBuiltInTemplates();
        $fieldTypes = config('forms.field_types', []);
        $categories = config('forms.field_type_categories', []);

        return view('forms::admin.forms.builder', compact(
            'form', 'formData', 'templates', 'fieldTypes', 'categories'
        ));
    }

    public function storeField(Request $request, Form $form)
    {
        $validated = $request->validate([
            'type'             => 'required|string',
            'label'            => 'nullable|string|max:255',
            'name'             => 'nullable|string|max:100',
            'placeholder'      => 'nullable|string|max:255',
            'help_text'        => 'nullable|string|max:500',
            'is_required'      => 'boolean',
            'options'          => 'nullable|array',
            'column_span'      => 'nullable|integer|in:4,6,12',
            'default_value'    => 'nullable|string',
            'css_class'        => 'nullable|string|max:200',
            'position'         => 'nullable|integer',
        ]);

        $field = $this->builderService->createField($form, $validated);

        return response()->json([
            'field'   => $this->builderService->serializeField($field),
            'message' => 'فیلد اضافه شد',
        ]);
    }

    public function updateField(Request $request, Form $form, FormField $field)
    {
        $validated = $request->validate([
            'label'            => 'nullable|string|max:255',
            'name'             => 'nullable|string|max:100',
            'placeholder'      => 'nullable|string|max:255',
            'help_text'        => 'nullable|string|max:500',
            'is_required'      => 'nullable|boolean',
            'is_active'        => 'nullable|boolean',
            'options'          => 'nullable|array',
            'conditions'       => 'nullable|array',
            'section_id'       => 'nullable|string',
            'column_span'      => 'nullable|integer|in:4,6,12',
            'default_value'    => 'nullable|string',
            'css_class'        => 'nullable|string|max:200',
            'validation_rules' => 'nullable|array',
            'position'         => 'nullable|integer',
        ]);

        $updated = $this->builderService->updateField($field, $validated);

        return response()->json([
            'field'   => $this->builderService->serializeField($updated),
            'message' => 'فیلد ذخیره شد',
        ]);
    }

    public function destroyField(Form $form, FormField $field)
    {
        $field->delete();
        return response()->json(['message' => 'فیلد حذف شد']);
    }

    public function duplicateField(Form $form, FormField $field)
    {
        $copy = $this->builderService->duplicateField($field);

        return response()->json([
            'field'   => $this->builderService->serializeField($copy),
            'message' => 'فیلد کپی شد',
        ]);
    }

    public function sortFields(Request $request, Form $form)
    {
        $validated = $request->validate([
            'order'   => 'required|array',
            'order.*' => 'required',
        ]);

        $this->builderService->sortFields($form, $validated['order']);

        return response()->json(['message' => 'ترتیب ذخیره شد']);
    }

    public function updateSettings(Request $request, Form $form)
    {
        $validated = $request->validate([
            'title'                 => 'nullable|string|max:255',
            'slug'                  => 'nullable|string|max:255',
            'is_active'             => 'nullable|boolean',
            'step_count'            => 'nullable|integer|min:1|max:10',
            'is_multi_step'         => 'nullable|boolean',
            'steps'                 => 'nullable|array',
            'success_message'       => 'nullable|string|max:500',
            'redirect_url'          => 'nullable|url',
            'submit_button_text'    => 'nullable|string|max:100',
            'show_progress_bar'     => 'nullable|boolean',
            'enable_simple_captcha' => 'nullable|boolean',
            'enable_hcaptcha'       => 'nullable|boolean',
            'intro_text'            => 'nullable|string',
        ]);

        $updated = $this->builderService->updateFormSettings($form, $validated);

        return response()->json([
            'form'    => ['title' => $updated->title, 'slug' => $updated->slug, 'is_active' => $updated->is_active],
            'message' => 'تنظیمات ذخیره شد',
        ]);
    }
}
