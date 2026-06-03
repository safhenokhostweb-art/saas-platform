<?php

namespace SaaSPlatform\Modules\Education\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;
use SaaSPlatform\Modules\Education\Services\EducationWalkthroughService;

class EducationWalkthroughController extends Controller
{
    public function __construct(
        private readonly EducationWalkthroughService $walkthroughs,
    ) {}

    public function index(Request $request): View
    {
        $items = $this->walkthroughs->paginate($request->only(['module_slug', 'is_active']), config('education.pagination', 20));
        return view('education::admin.education.walkthroughs.index', compact('items'));
    }

    public function create(): View
    {
        return view('education::admin.education.walkthroughs.edit', ['walkthrough' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateWalkthrough($request);
        $this->walkthroughs->create($data);
        return redirect()->route('admin.education.walkthroughs.index')->with('success', 'راهنمای گام‌به‌گام ایجاد شد.');
    }

    public function edit(EducationWalkthrough $walkthrough): View
    {
        $walkthrough->load('steps');
        return view('education::admin.education.walkthroughs.edit', compact('walkthrough'));
    }

    public function update(Request $request, EducationWalkthrough $walkthrough): RedirectResponse
    {
        $data = $this->validateWalkthrough($request);
        $this->walkthroughs->update($walkthrough, $data);
        return back()->with('success', 'راهنما بروز شد.');
    }

    public function destroy(EducationWalkthrough $walkthrough): RedirectResponse
    {
        $this->walkthroughs->delete($walkthrough);
        return redirect()->route('admin.education.walkthroughs.index')->with('success', 'راهنما حذف شد.');
    }

    private function validateWalkthrough(Request $request): array
    {
        return $request->validate([
            'title'             => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'trigger_route'     => ['nullable', 'string', 'max:120'],
            'trigger_condition' => ['required', 'in:always,once,manual'],
            'module_slug'       => ['nullable', 'string', 'max:60'],
            'is_active'         => ['boolean'],
            'steps'             => ['nullable', 'array'],
            'steps.*.title'     => ['required', 'string', 'max:255'],
            'steps.*.body'      => ['nullable', 'string'],
            'steps.*.target_selector' => ['nullable', 'string', 'max:200'],
            'steps.*.placement' => ['nullable', 'in:top,bottom,left,right,center'],
            'steps.*.action'    => ['nullable', 'in:click,input,observe,next'],
        ]);
    }
}
