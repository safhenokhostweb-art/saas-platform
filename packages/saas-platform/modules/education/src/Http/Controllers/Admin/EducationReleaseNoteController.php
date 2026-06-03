<?php

namespace SaaSPlatform\Modules\Education\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use SaaSPlatform\Modules\Education\Models\EducationReleaseNote;
use SaaSPlatform\Modules\Education\Services\EducationReleaseNoteService;

class EducationReleaseNoteController extends Controller
{
    public function __construct(
        private readonly EducationReleaseNoteService $notes,
    ) {}

    public function index(Request $request): View
    {
        $items = $this->notes->paginate($request->only(['module_slug', 'release_type', 'is_published']), config('education.pagination', 20));
        return view('education::admin.education.release-notes.index', compact('items'));
    }

    public function create(): View
    {
        return view('education::admin.education.release-notes.edit', ['note' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateNote($request);
        $this->notes->create($data);
        return redirect()->route('admin.education.release-notes.index')->with('success', 'یادداشت انتشار ذخیره شد.');
    }

    public function edit(EducationReleaseNote $releaseNote): View
    {
        $releaseNote->load('revision.article');
        return view('education::admin.education.release-notes.edit', ['note' => $releaseNote]);
    }

    public function update(Request $request, EducationReleaseNote $releaseNote): RedirectResponse
    {
        $data = $this->validateNote($request);
        $this->notes->update($releaseNote, $data);
        return back()->with('success', 'یادداشت انتشار بروز شد.');
    }

    public function destroy(EducationReleaseNote $releaseNote): RedirectResponse
    {
        $this->notes->delete($releaseNote);
        return redirect()->route('admin.education.release-notes.index')->with('success', 'یادداشت حذف شد.');
    }

    private function validateNote(Request $request): array
    {
        return $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'body'           => ['required', 'string'],
            'version_number' => ['required', 'string', 'max:30'],
            'release_type'   => ['required', 'in:feature,fix,improvement,breaking,security'],
            'module_slug'    => ['nullable', 'string', 'max:60'],
            'revision_id'    => ['nullable', 'exists:edu_article_revisions,id'],
            'is_published'   => ['boolean'],
        ]);
    }
}
