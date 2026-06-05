<?php

namespace SaaSPlatform\Modules\Education\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Services\EducationArticleService;
use SaaSPlatform\Modules\Education\Services\EducationCategoryService;

class EducationArticleController extends Controller
{
    public function __construct(
        private readonly EducationArticleService  $articles,
        private readonly EducationCategoryService $categories,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', EducationArticle::class);

        $articles   = $this->articles->paginate($request->only(['search', 'status', 'category_id', 'module_slug', 'content_type']), config('education.pagination', 20));
        $categories = $this->categories->all();

        return view('education::admin.education.articles.index', compact('articles', 'categories'));
    }

    public function create(): View
    {
        $this->authorize('create', EducationArticle::class);

        $categories   = $this->categories->all();
        $contentTypes = ['article', 'guide', 'tutorial', 'faq', 'reference'];

        return view('education::admin.education.articles.create', compact('categories', 'contentTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', EducationArticle::class);
        $data = $request->validate([
            'edu_category_id' => ['required', 'exists:edu_categories,id'],
            'title'           => ['required', 'string', 'max:255'],
            'excerpt'         => ['nullable', 'string', 'max:600'],
            'body'            => ['required', 'string'],
            'status'          => ['required', 'in:draft,published,archived'],
            'content_type'    => ['required', 'in:article,guide,tutorial,faq,reference'],
            'module_slug'     => ['nullable', 'string', 'max:60'],
            'route_pattern'   => ['nullable', 'string', 'max:120'],
            'tags_raw'        => ['nullable', 'string'],
            'target_roles'    => ['nullable', 'array'],
        ]);

        $data['author_id'] = auth()->id();
        $this->articles->create($data);

        return redirect()->route('admin.education.articles.index')->with('success', 'مقاله ذخیره شد.');
    }

    public function show(EducationArticle $article): View
    {
        $this->authorize('view', $article);

        $article->load('category', 'author', 'revisions', 'media');
        return view('education::admin.education.articles.show', compact('article'));
    }

    public function edit(EducationArticle $article): View
    {
        $this->authorize('update', $article);

        $categories   = $this->categories->all();
        $contentTypes = ['article', 'guide', 'tutorial', 'faq', 'reference'];

        return view('education::admin.education.articles.edit', compact('article', 'categories', 'contentTypes'));
    }

    public function update(Request $request, EducationArticle $article): RedirectResponse
    {
        $this->authorize('update', $article);
        $data = $request->validate([
            'edu_category_id' => ['required', 'exists:edu_categories,id'],
            'title'           => ['required', 'string', 'max:255'],
            'excerpt'         => ['nullable', 'string', 'max:600'],
            'body'            => ['required', 'string'],
            'status'          => ['required', 'in:draft,published,archived'],
            'content_type'    => ['required', 'in:article,guide,tutorial,faq,reference'],
            'module_slug'     => ['nullable', 'string', 'max:60'],
            'route_pattern'   => ['nullable', 'string', 'max:120'],
            'tags_raw'        => ['nullable', 'string'],
            'target_roles'    => ['nullable', 'array'],
        ]);

        $this->articles->update($article, $data);

        return redirect()->route('admin.education.articles.show', $article)->with('success', 'مقاله بروز شد.');
    }

    public function destroy(EducationArticle $article): RedirectResponse
    {
        $this->authorize('delete', $article);

        $this->articles->delete($article);
        return redirect()->route('admin.education.articles.index')->with('success', 'مقاله حذف شد.');
    }

    public function publish(EducationArticle $article): RedirectResponse
    {
        $this->authorize('update', $article);

        $this->articles->publish($article);
        return back()->with('success', 'مقاله منتشر شد.');
    }
}
