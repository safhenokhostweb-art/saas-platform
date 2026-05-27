<?php

namespace SaaSPlatform\Modules\Forms\Http\Controllers;

use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\Http\BaseController as Controller;
use SaaSPlatform\Modules\Forms\Models\Form;
use SaaSPlatform\Modules\Forms\Models\FormAutomation;
use SaaSPlatform\Modules\Forms\Services\FormAutomationService;

class FormAutomationController extends Controller
{
    public function __construct(
        protected FormAutomationService $automationService
    ) {}

    public function index(Form $form)
    {
        $automations = $this->automationService->getForForm($form);
        return view('forms::admin.forms.automation.index', compact('form', 'automations'));
    }

    public function store(Request $request, Form $form)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'type'          => 'required|in:email,webhook,slack,telegram,autoresponder',
            'config'        => 'required|array',
            'config.to'     => 'required_if:type,email,autoresponder|nullable|email',
            'config.url'    => 'required_if:type,webhook|nullable|url',
            'conditions'    => 'nullable|array',
            'is_active'     => 'boolean',
        ]);

        $automation = $this->automationService->create($form, $validated);

        if ($request->wantsJson()) {
            return response()->json(['automation' => $automation, 'message' => 'قانون خودکارسازی اضافه شد']);
        }

        return back()->with('success', 'قانون خودکارسازی اضافه شد');
    }

    public function update(Request $request, Form $form, FormAutomation $automation)
    {
        $validated = $request->validate([
            'name'       => 'nullable|string|max:255',
            'type'       => 'nullable|in:email,webhook,slack,telegram,autoresponder',
            'config'     => 'nullable|array',
            'conditions' => 'nullable|array',
            'is_active'  => 'nullable|boolean',
        ]);

        $updated = $this->automationService->update($automation, $validated);

        if ($request->wantsJson()) {
            return response()->json(['automation' => $updated, 'message' => 'بروزرسانی شد']);
        }

        return back()->with('success', 'قانون بروزرسانی شد');
    }

    public function destroy(Form $form, FormAutomation $automation)
    {
        $this->automationService->delete($automation);
        return back()->with('success', 'قانون حذف شد');
    }

    public function toggle(Form $form, FormAutomation $automation)
    {
        $this->automationService->toggle($automation);
        return back()->with('success', $automation->is_active ? 'غیرفعال شد' : 'فعال شد');
    }
}
