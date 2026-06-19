<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThemeTemplate;
use App\Services\ThemeBuilder\ThemeTemplateImportExportService;
use App\Services\ThemeBuilder\ThemeTemplateService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ThemeTemplateController extends Controller
{
    public function __construct(
        protected ThemeTemplateService $svc,
        protected ThemeTemplateImportExportService $io,
    ) {}

    public function index(Request $request)
    {
        $templates = $this->svc->search(
            type:     $request->input('type'),
            category: $request->input('category'),
            term:     $request->input('search'),
            perPage:  24,
        );

        $options = $this->svc->filterOptions();

        return view('admin.theme-templates.index', compact('templates', 'options'));
    }

    public function create()
    {
        $options = $this->svc->filterOptions();
        return view('admin.theme-templates.create', compact('options'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:200',
            'type'         => 'required|in:' . implode(',', array_keys(ThemeTemplate::TYPES)),
            'category'     => 'required|in:' . implode(',', array_keys(ThemeTemplate::CATEGORIES)),
            'description'  => 'nullable|string|max:1000',
            'tags'         => 'nullable|string',
            'author'       => 'nullable|string|max:100',
            'version'      => 'nullable|string|max:20',
            'license_type' => 'nullable|in:free,premium,commercial,mit',
            'blocks'       => 'required|json',
            'is_public'    => 'nullable|boolean',
        ]);

        $data['blocks'] = json_decode($data['blocks'], true);
        $data['tags']   = $data['tags'] ? array_map('trim', explode(',', $data['tags'])) : [];

        $template = $this->svc->saveTemplate($data);

        return redirect()->route('admin.theme-templates.index')
            ->with('success', 'قالب «' . $template->name . '» ایجاد شد.');
    }

    public function show(ThemeTemplate $themeTemplate)
    {
        return view('admin.theme-templates.show', ['template' => $themeTemplate]);
    }

    public function edit(ThemeTemplate $themeTemplate)
    {
        $options = $this->svc->filterOptions();
        return view('admin.theme-templates.edit', ['template' => $themeTemplate, 'options' => $options]);
    }

    public function update(Request $request, ThemeTemplate $themeTemplate)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:200',
            'type'         => 'required|in:' . implode(',', array_keys(ThemeTemplate::TYPES)),
            'category'     => 'required|in:' . implode(',', array_keys(ThemeTemplate::CATEGORIES)),
            'description'  => 'nullable|string|max:1000',
            'tags'         => 'nullable|string',
            'is_public'    => 'nullable|boolean',
            'is_active'    => 'nullable|boolean',
        ]);

        $data['tags']      = $data['tags'] ? array_map('trim', explode(',', $data['tags'])) : [];
        $data['is_public'] = $request->boolean('is_public');
        $data['is_active'] = $request->boolean('is_active', true);

        $this->svc->update($themeTemplate, $data);

        return redirect()->route('admin.theme-templates.show', $themeTemplate)
            ->with('success', 'قالب به‌روز شد.');
    }

    public function destroy(ThemeTemplate $themeTemplate)
    {
        $themeTemplate->delete();
        return redirect()->route('admin.theme-templates.index')
            ->with('success', 'قالب حذف شد.');
    }

    public function export(ThemeTemplate $themeTemplate): StreamedResponse
    {
        $json = $this->io->exportTemplateJson($themeTemplate);
        $filename = 'template-' . $themeTemplate->uuid . '.json';

        return response()->streamDownload(
            fn() => print($json),
            $filename,
            ['Content-Type' => 'application/json']
        );
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:json|max:2048']);

        try {
            $json     = file_get_contents($request->file('file')->path());
            $template = $this->io->importTemplateJson($json);
            return redirect()->route('admin.theme-templates.show', $template)
                ->with('success', 'قالب با موفقیت وارد شد.');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }
    }
}
