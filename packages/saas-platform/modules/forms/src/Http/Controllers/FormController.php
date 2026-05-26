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

class FormController extends Controller
{
    public function __construct(
        protected FormServiceInterface      $formService,
        protected FormFieldService          $fieldService,
        protected ActivityLoggerInterface   $activityLogger
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

        $this->activityLogger->log('form.created', 'ایجاد فرم: ' . $form->title, $form);

        return redirect()->route('admin.forms.edit', $form)
            ->with('success', 'فرم ساخته شد؛ اکنون فیلدها را اضافه کنید.');
    }

    public function edit(Form $form)
    {
        $form->load('fields');
        return view('forms::admin.forms.edit', compact('form'));
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
