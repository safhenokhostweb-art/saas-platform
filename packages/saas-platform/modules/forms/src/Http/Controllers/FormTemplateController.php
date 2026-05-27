<?php

namespace SaaSPlatform\Modules\Forms\Http\Controllers;

use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\Http\BaseController as Controller;
use SaaSPlatform\Modules\Forms\Models\Form;
use SaaSPlatform\Modules\Forms\Models\FormTemplate;
use SaaSPlatform\Modules\Forms\Services\FormTemplateService;

class FormTemplateController extends Controller
{
    public function __construct(
        protected FormTemplateService $templateService
    ) {}

    public function index()
    {
        $templates  = $this->templateService->getAll();
        $categories = $this->templateService->getCategories();
        $builtIn    = $this->templateService->getBuiltInTemplates();

        return view('forms::admin.forms.templates.index', compact('templates', 'categories', 'builtIn'));
    }

    public function store(Request $request, Form $form)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'category'    => 'nullable|string|max:50',
            'is_public'   => 'boolean',
        ]);

        $template = $this->templateService->createFromForm($form, $validated);

        if ($request->wantsJson()) {
            return response()->json(['template' => $template, 'message' => 'قالب ذخیره شد']);
        }

        return back()->with('success', 'قالب با موفقیت ذخیره شد');
    }

    public function apply(Request $request, Form $form)
    {
        $validated = $request->validate([
            'template_id' => 'required',
        ]);

        $templateId = $validated['template_id'];

        $template = FormTemplate::find($templateId);
        if (! $template) {
            return response()->json(['error' => 'قالب یافت نشد'], 404);
        }

        $this->templateService->applyToForm($template, $form);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'قالب اعمال شد']);
        }

        return redirect()->route('admin.forms.builder', $form)
            ->with('success', 'قالب اعمال شد');
    }

    public function destroy(FormTemplate $template)
    {
        $this->templateService->delete($template);
        return back()->with('success', 'قالب حذف شد');
    }
}
