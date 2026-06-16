<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use App\Services\Infrastructure\StorageManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppReleaseController extends Controller
{
    public function __construct(private StorageManager $storage) {}

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
        // Sanitize the client-supplied name: strip path traversal, null bytes, and
        // non-safe characters before embedding it in the stored filename.
        $safeClientName = StorageManager::sanitizeFilename($file->getClientOriginalName());
        $filename  = "v{$version}-build{$build}-{$safeClientName}";

        // Capture metadata BEFORE move() — after move, SplFileInfo path changes
        $originalName = $file->getClientOriginalName();
        $fileSize     = $file->getSize();
        $mimeType     = $file->getMimeType() ?? 'application/octet-stream';

        $stored = $this->storage->storeUploadedFile($file, "app-releases/{$platform}", 'private', $filename);

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
        if (!$this->storage->exists($release->file_path, 'private')) {
            abort(404, 'فایل یافت نشد.');
        }

        $release->increment('download_count');

        return $this->storage->download($release->file_path, 'private', $release->file_name);
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
        $this->storage->delete($release->file_path, 'private');
        $release->delete();

        return back()->with('success', 'نسخه حذف شد.');
    }
}
