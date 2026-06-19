<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Models\ThemePack;
use App\Services\ThemeBuilder\ThemeTemplateImportExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ThemePackController extends Controller
{
    public function __construct(
        protected ThemeTemplateImportExportService $io,
    ) {}

    public function index()
    {
        $packs = ThemePack::active()->orderByDesc('created_at')->paginate(20);
        return view('admin.theme-packs.index', compact('packs'));
    }

    public function create()
    {
        $themes = Theme::orderBy('name')->get(['id', 'name']);
        return view('admin.theme-packs.create', compact('themes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:200',
            'description'  => 'nullable|string|max:1000',
            'version'      => 'nullable|string|max:20',
            'author'       => 'nullable|string|max:100',
            'license_type' => 'nullable|in:free,premium,commercial,mit',
            'theme_id'     => 'nullable|exists:themes,id',
            'is_public'    => 'nullable|boolean',
        ]);

        if (!empty($data['theme_id'])) {
            $theme = Theme::findOrFail($data['theme_id']);
            $pack  = $this->io->buildPackFromTheme($theme, $data);
        } else {
            $pack = ThemePack::create([
                'name'         => $data['name'],
                'description'  => $data['description'] ?? null,
                'version'      => $data['version'] ?? '1.0.0',
                'author'       => $data['author'] ?? null,
                'license_type' => $data['license_type'] ?? 'free',
                'pack_data'    => ['global_styles' => [], 'headers' => [], 'footers' => [], 'pages' => [], 'templates' => []],
                'is_public'    => $data['is_public'] ?? false,
            ]);
        }

        return redirect()->route('admin.theme-packs.show', $pack)
            ->with('success', 'پک تم «' . $pack->name . '» ایجاد شد.');
    }

    public function show(ThemePack $themePack)
    {
        return view('admin.theme-packs.show', ['pack' => $themePack]);
    }

    public function destroy(ThemePack $themePack)
    {
        $themePack->delete();
        return redirect()->route('admin.theme-packs.index')
            ->with('success', 'پک حذف شد.');
    }

    public function exportJson(ThemePack $themePack): StreamedResponse
    {
        $json     = $this->io->exportPackJson($themePack);
        $filename = 'theme-pack-' . $themePack->uuid . '.json';

        return response()->streamDownload(
            fn() => print($json),
            $filename,
            ['Content-Type' => 'application/json']
        );
    }

    public function exportZip(ThemePack $themePack): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $zipPath  = $this->io->exportPackZip($themePack);
        $filename = 'theme-pack-' . $themePack->uuid . '.zip';

        return response()->download($zipPath, $filename, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    public function importJson(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:json|max:5120']);

        try {
            $json = file_get_contents($request->file('file')->path());
            $pack = $this->io->importPackJson($json);
            return redirect()->route('admin.theme-packs.show', $pack)
                ->with('success', 'پک با موفقیت وارد شد.');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }
    }

    public function importZip(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:zip|max:20480']);

        try {
            $pack = $this->io->importPackZip($request->file('file')->path());
            return redirect()->route('admin.theme-packs.show', $pack)
                ->with('success', 'پک ZIP با موفقیت وارد شد.');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }
    }
}
