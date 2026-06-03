<?php

namespace SaaSPlatform\Modules\Education\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use SaaSPlatform\Modules\Education\Models\EducationCategory;
use SaaSPlatform\Modules\Education\Services\EducationCategoryService;

class EducationCategoryController extends Controller
{
    public function __construct(
        private readonly EducationCategoryService $categories,
    ) {}

    public function index(): View
    {
        $categories = $this->categories->roots();
        return view('education::admin.education.categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'slug'         => ['nullable', 'string', 'max:120', 'unique:edu_categories,slug'],
            'parent_id'    => ['nullable', 'exists:edu_categories,id'],
            'icon'         => ['nullable', 'string', 'max:50'],
            'content_type' => ['required', 'in:articles,walkthroughs,mixed'],
            'visibility'   => ['required', 'in:public,authenticated,platform_only,role_based'],
        ]);

        $this->categories->create($data);
        return back()->with('success', 'دسته‌بندی اضافه شد.');
    }

    public function update(Request $request, EducationCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'icon'         => ['nullable', 'string', 'max:50'],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
            'is_active'    => ['boolean'],
            'content_type' => ['required', 'in:articles,walkthroughs,mixed'],
            'visibility'   => ['required', 'in:public,authenticated,platform_only,role_based'],
        ]);

        $this->categories->update($category, $data);
        return back()->with('success', 'دسته‌بندی بروز شد.');
    }

    public function destroy(EducationCategory $category): RedirectResponse
    {
        $this->categories->delete($category);
        return back()->with('success', 'دسته‌بندی حذف شد.');
    }
}
