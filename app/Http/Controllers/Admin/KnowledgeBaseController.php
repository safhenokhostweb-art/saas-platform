<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use App\Models\KbCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgeBaseController extends Controller
{
    // ── دسته‌بندی‌ها ──────────────────────────────────────────────────────────

    public function categories(): View
    {
        $categories = KbCategory::with('children', 'articles')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return view('admin.kb.categories', compact('categories'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'slug'      => ['nullable', 'string', 'max:120', 'unique:kb_categories,slug'],
            'parent_id' => ['nullable', 'exists:kb_categories,id'],
            'icon'      => ['nullable', 'string', 'max:50'],
        ]);

        if (empty($data['slug'])) {
            $data['slug'] = \Str::slug($data['name']) . '-' . \Str::random(4);
        }

        KbCategory::create($data);

        return back()->with('success', 'دسته‌بندی اضافه شد.');
    }

    public function updateCategory(Request $request, KbCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'icon'       => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['boolean'],
        ]);

        $category->update($data);

        return back()->with('success', 'دسته‌بندی بروز شد.');
    }

    public function destroyCategory(KbCategory $category): RedirectResponse
    {
        $category->delete();
        return back()->with('success', 'دسته‌بندی حذف شد.');
    }

    private function parseTags(?string $raw): array
    {
        if (empty($raw)) return [];
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    // ── مقالات ────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $articles = KbArticle::with('category', 'author')
            ->when($request->search, fn($q) => $q->search($request->search))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->category_id, fn($q) => $q->where('kb_category_id', $request->category_id))
            ->latest()
            ->paginate(20);

        $categories = KbCategory::active()->orderBy('sort_order')->get();

        return view('admin.kb.index', compact('articles', 'categories'));
    }

    public function create(): View
    {
        $categories = KbCategory::active()->orderBy('sort_order')->get();
        return view('admin.kb.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kb_category_id' => ['required', 'exists:kb_categories,id'],
            'title'          => ['required', 'string', 'max:255'],
            'excerpt'        => ['nullable', 'string', 'max:500'],
            'body'           => ['required', 'string'],
            'status'         => ['required', 'in:draft,published,archived'],
            'tags_raw'       => ['nullable', 'string'],
        ]);

        $data['tags']      = $this->parseTags($request->input('tags_raw'));
        $data['author_id'] = auth()->id();
        unset($data['tags_raw']);

        if ($data['status'] === KbArticle::STATUS_PUBLISHED) {
            $data['published_at'] = now();
        }

        KbArticle::create($data);

        return redirect()->route('admin.kb.index')->with('success', 'مقاله ذخیره شد.');
    }

    public function show(KbArticle $article): View
    {
        $article->load('category', 'author');
        return view('admin.kb.show', compact('article'));
    }

    public function edit(KbArticle $article): View
    {
        $categories = KbCategory::active()->orderBy('sort_order')->get();
        return view('admin.kb.edit', compact('article', 'categories'));
    }

    public function update(Request $request, KbArticle $article): RedirectResponse
    {
        $data = $request->validate([
            'kb_category_id' => ['required', 'exists:kb_categories,id'],
            'title'          => ['required', 'string', 'max:255'],
            'excerpt'        => ['nullable', 'string', 'max:500'],
            'body'           => ['required', 'string'],
            'status'         => ['required', 'in:draft,published,archived'],
            'tags_raw'       => ['nullable', 'string'],
        ]);

        $data['tags'] = $this->parseTags($request->input('tags_raw'));
        unset($data['tags_raw']);

        if ($data['status'] === KbArticle::STATUS_PUBLISHED && ! $article->published_at) {
            $data['published_at'] = now();
        }

        $article->update($data);

        return redirect()->route('admin.kb.show', $article)->with('success', 'مقاله بروز شد.');
    }

    public function destroy(KbArticle $article): RedirectResponse
    {
        $article->delete();
        return redirect()->route('admin.kb.index')->with('success', 'مقاله حذف شد.');
    }
}
