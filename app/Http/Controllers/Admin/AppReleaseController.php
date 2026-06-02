<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppReleaseController extends Controller
{
    // ── GET /admin/app-releases ───────────────────────────────────────────────

    public function index(): View
    {
        $releases = AppRelease::with('uploader')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('platform');

        $latestByPlatform = AppRelease::where('is_latest', true)
            ->get()
            ->keyBy('platform');

        $stats = [
            'total'     => AppRelease::count(),
            'downloads' => AppRelease::sum('download_count'),
            'platforms' => AppRelease::distinct('platform')->count(),
        ];

        return view('admin.app-releases.index', compact('releases', 'latestByPlatform', 'stats'));
    }

    // ── POST /admin/app-releases ──────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'platform'      => ['required', 'in:android,windows,macos,linux'],
            'version'       => ['required', 'string', 'max:30', 'regex:/^\d+\.\d+\.\d+$/'],
            'build_number'  => ['required', 'integer', 'min:1'],
            'release_notes' => ['nullable', 'string', 'max:2000'],
            'file'          => ['required', 'file', 'max:204800'], // 200 MB max
            'set_latest'    => ['nullable', 'boolean'],
        ]);

        $file      = $request->file('file');
        $platform  = $request->input('platform');
        $version   = $request->input('version');
        $build     = $request->input('build_number');
        $filename  = "v{$version}-build{$build}-" . $file->getClientOriginalName();

        // Capture metadata BEFORE move() — after move, SplFileInfo path changes
        $originalName = $file->getClientOriginalName();
        $fileSize     = $file->getSize();
        $mimeType     = $file->getMimeType() ?? 'application/octet-stream';

        // PHP native move — avoids Flysystem/dirname() bug on PHP 8.4 + Windows
        $targetDir = storage_path('app' . DIRECTORY_SEPARATOR . 'private'
            . DIRECTORY_SEPARATOR . 'app-releases'
            . DIRECTORY_SEPARATOR . $platform);

        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $file->move($targetDir, $filename);
        $stored = "app-releases/{$platform}/{$filename}";

        $setLatest = $request->boolean('set_latest', true);

        if ($setLatest) {
            AppRelease::where('platform', $platform)->update(['is_latest' => false]);
        }

        AppRelease::create([
            'platform'      => $platform,
            'version'       => $version,
            'build_number'  => $build,
            'file_path'     => $stored,
            'file_size'     => $fileSize,
            'file_name'     => $originalName,
            'mime_type'     => $mimeType,
            'release_notes' => $request->input('release_notes') ?: null,
            'is_latest'     => $setLatest,
            'uploaded_by'   => auth()->id(),
        ]);

        return back()->with('success', "نسخه {$version} برای پلتفرم {$platform} آپلود شد.");
    }

    // ── GET /admin/app-releases/{release}/download ────────────────────────────

    public function download(AppRelease $release): mixed
    {
        $absolutePath = storage_path('app' . DIRECTORY_SEPARATOR . 'private'
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $release->file_path));

        if (! file_exists($absolutePath)) {
            abort(404, 'فایل یافت نشد.');
        }

        $release->increment('download_count');

        return response()->download($absolutePath, $release->file_name, [
            'Content-Type'        => $release->mime_type,
            'Content-Disposition' => 'attachment; filename="' . $release->file_name . '"',
        ]);
    }

    // ── PATCH /admin/app-releases/{release}/set-latest ────────────────────────

    public function setLatest(AppRelease $release): RedirectResponse
    {
        AppRelease::where('platform', $release->platform)->update(['is_latest' => false]);
        $release->update(['is_latest' => true]);

        return back()->with('success', "نسخه {$release->version} به عنوان جدیدترین نسخه {$release->platform} انتخاب شد.");
    }

    // ── DELETE /admin/app-releases/{release} ──────────────────────────────────

    public function destroy(AppRelease $release): RedirectResponse
    {
        $absolutePath = storage_path('app' . DIRECTORY_SEPARATOR . 'private'
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $release->file_path));

        if (file_exists($absolutePath)) {
            unlink($absolutePath);
        }

        $release->delete();

        return back()->with('success', 'نسخه حذف شد.');
    }
}
