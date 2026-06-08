<?php

namespace SaaSPlatform\Modules\Forms\Http\Controllers;

use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\Contracts\Services\ActivityLoggerInterface;
use SaaSPlatform\CoreUtils\Http\BaseController as Controller;
use SaaSPlatform\Modules\Forms\Contracts\FormServiceInterface;
use SaaSPlatform\Modules\Forms\Http\Requests\FormFieldRequest;
use SaaSPlatform\Modules\Forms\Http\Requests\FormRequest;
use SaaSPlatform\Modules\Forms\Models\Form;
use SaaSPlatform\Modules\Forms\Models\FormField;
use SaaSPlatform\Modules\Forms\Services\FormFieldService;
use SaaSPlatform\Modules\Forms\Services\FormTemplateService;

class FormController extends Controller
{
    public function __construct(
        protected FormServiceInterface      $formService,
        protected FormFieldService          $fieldService,
        protected ActivityLoggerInterface   $activityLogger,
        protected FormTemplateService       $templateService,
    ) {}

    public function index()
    {
        $forms = $this->formService->getAll();
        return view('forms::admin.forms.index', compact('forms'));
    }

    public function create()
    {
        return view('forms::admin.forms.create');
    }

    public function store(FormRequest $request)
    {
        $form = $this->formService->create($request->validated());

        // Apply a built-in starter template if the user chose one on the create screen
        if ($request->boolean('builtin')) {
            $templates = $this->templateService->getBuiltInTemplates();
            $index = (int) $request->input('template', 0);
            $tpl = $templates[$index] ?? null;

            if ($tpl) {
                foreach ($tpl['fields'] as $position => $fieldData) {
                    \SaaSPlatform\Modules\Forms\Models\FormField::create([
                        'form_id'     => $form->id,
                        'type'        => $fieldData['type'],
                        'label'       => $fieldData['label'],
                        'name'        => $fieldData['name'],
                        'is_required' => $fieldData['is_required'] ?? false,
                        'is_active'   => true,
                        'options'     => isset($fieldData['options']) ? $fieldData['options'] : null,
                        'column_span' => $fieldData['column_span'] ?? 12,
                        'position'    => $position,
                    ]);
                }
            }
        }

        $this->activityLogger->log('form.created', 'ایجاد فرم: ' . $form->title, $form);

        return redirect()->route('admin.forms.edit', $form)
            ->with('success', 'فرم ساخته شد. فیلدها را سفارشی کنید.');
    }

    public function edit(Form $form)
    {
        return redirect()->route('admin.forms.builder', $form);
    }

    public function preview(Form $form)
    {
        $data = $this->formService->getPreviewData($form);
        return view('forms::admin.forms.preview', $data);
    }

    public function update(FormRequest $request, Form $form)
    {
        $this->formService->update($form, $request->validated());

        $this->activityLogger->log('form.updated', 'ویرایش فرم: ' . $form->title, $form);

        return back()->with('success', 'فرم ویرایش شد');
    }

    public function destroy(Form $form)
    {
        $title = $form->title;
        $id = $form->id;

        $this->formService->delete($form);

        $this->activityLogger->log('form.deleted', 'حذف فرم: ' . $title, null, ['deleted_id' => $id]);

        return redirect()->route('admin.forms.index')
            ->with('success', 'فرم حذف شد');
    }

    public function duplicate(Form $form)
    {
        $newForm = $this->formService->duplicate($form);

        $this->activityLogger->log('form.duplicated', 'کپی فرم: ' . $form->title . ' → ' . $newForm->title, $newForm);

        return redirect()->route('admin.forms.builder', $newForm)
            ->with('success', 'فرم «' . $form->title . '» کپی شد.');
    }

    public function storeField(FormFieldRequest $request, Form $form)
    {
        $this->fieldService->create($form, $request->validated());
        return back()->with('success', 'فیلد با موفقیت اضافه شد');
    }

    public function destroyField(FormField $field)
    {
        $this->fieldService->delete($field);
        return back()->with('success', 'فیلد حذف شد');
    }

    public function toggleField(FormField $field)
    {
        $this->fieldService->toggle($field);
        return back();
    }

    public function sortFields(Request $request, Form $form)
    {
        $this->fieldService->sort($form, $request->positions ?? []);
        return response()->json(['status' => 'ok']);
    }
}
