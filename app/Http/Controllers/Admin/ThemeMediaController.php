<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Services\Infrastructure\MediaStorage;
use App\Services\Infrastructure\StorageManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ThemeMediaController extends Controller
{
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const ALLOWED   = ['jpg','jpeg','png','gif','webp','svg'];

    public function __construct(private readonly MediaStorage $media)
    {
    }

    public function index(Theme $theme): JsonResponse
    {
        $disk  = $this->media->diskNameFor('public');
        $dir   = "theme-media/{$theme->id}";
        $files = Storage::disk($disk)->files($dir);
        $images = array_map(fn ($f) => [
            'url'  => Storage::disk($disk)->url($f),
            'name' => basename($f),
            'size' => Storage::disk($disk)->size($f),
        ], $files);
        // newest first
        usort($images, fn ($a, $b) => strcmp($b['name'], $a['name']));
        return response()->json(['images' => array_values($images)]);
    }

    public function upload(Request $request, Theme $theme, StorageManager $storage): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:5120|mimes:jpg,jpeg,png,gif,webp,svg',
        ]);

        $file = $request->file('file');
        if ($file->getSize() > self::MAX_BYTES) {
            return response()->json(['error' => 'حداکثر اندازه ۵ مگابایت'], 422);
        }

        $disk = $this->media->diskNameFor('public');
        $dir  = "theme-media/{$theme->id}";
        $path = $storage->storeUploadedFile($file, $dir, $disk);
        if (!$path) {
            return response()->json(['error' => 'خطا در ذخیره فایل'], 500);
        }

        return response()->json([
            'url'  => Storage::disk($disk)->url($path),
            'name' => basename($path),
        ]);
    }

    public function destroy(Request $request, Theme $theme): JsonResponse
    {
        $disk = $this->media->diskNameFor('public');
        $url  = $request->input('url', '');
        $base = Storage::disk($disk)->url('');
        if (!str_starts_with($url, $base)) {
            return response()->json(['error' => 'مسیر نامعتبر'], 422);
        }

        $path = ltrim(str_replace($base, '', $url), '/');
        // Guard: must be inside theme-media/{themeId}/
        if (!str_starts_with($path, "theme-media/{$theme->id}/")) {
            return response()->json(['error' => 'دسترسی رد شد'], 403);
        }

        Storage::disk($disk)->delete($path);
        return response()->json(['ok' => true]);
    }
}
