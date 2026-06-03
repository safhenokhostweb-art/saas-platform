<?php

namespace SaaSPlatform\Modules\Education\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use SaaSPlatform\Modules\Education\Models\EducationLearningPath;
use SaaSPlatform\Modules\Education\Services\EducationLearningPathService;

class EducationLearningPathController extends Controller
{
    public function __construct(
        private readonly EducationLearningPathService $paths,
    ) {}

    public function index(Request $request): View
    {
        $items = $this->paths->paginate($request->only(['is_active']), config('education.pagination', 20));
        return view('education::admin.education.learning-paths.index', compact('items'));
    }

    public function create(): View
    {
        return view('education::admin.education.learning-paths.edit', ['path' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePath($request);
        $this->paths->create($data);
        return redirect()->route('admin.education.learning-paths.index')->with('success', 'مسیر یادگیری ایجاد شد.');
    }

    public function edit(EducationLearningPath $learningPath): View
    {
        $learningPath->load('steps');
        return view('education::admin.education.learning-paths.edit', ['path' => $learningPath]);
    }

    public function update(Request $request, EducationLearningPath $learningPath): RedirectResponse
    {
        $data = $this->validatePath($request);
        $this->paths->update($learningPath, $data);
        return back()->with('success', 'مسیر یادگیری بروز شد.');
    }

    public function destroy(EducationLearningPath $learningPath): RedirectResponse
    {
        $this->paths->delete($learningPath);
        return redirect()->route('admin.education.learning-paths.index')->with('success', 'مسیر یادگیری حذف شد.');
    }

    private function validatePath(Request $request): array
    {
        return $request->validate([
            'title'              => ['required', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'target_roles'       => ['nullable', 'array'],
            'module_slug'        => ['nullable', 'string', 'max:60'],
            'is_active'          => ['boolean'],
            'estimated_minutes'  => ['nullable', 'integer', 'min:1'],
            'steps'              => ['nullable', 'array'],
            'steps.*.title'      => ['required', 'string', 'max:255'],
            'steps.*.step_type'  => ['required', 'in:article,walkthrough,task,external_link'],
            'steps.*.reference_id'   => ['nullable', 'integer'],
            'steps.*.reference_type' => ['nullable', 'string'],
            'steps.*.is_required'    => ['boolean'],
        ]);
    }
}
