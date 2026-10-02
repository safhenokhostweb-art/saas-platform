<?php

namespace App\Services\Infrastructure;

use App\Models\Site;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Single point of truth for "which disk does this media live on".
 *
 * Application code must never hardcode 'public' / 'uploads' / 'private' /
 * 'arvan' as a Storage::disk() argument — ask this service for the disk
 * name (or the Filesystem instance) instead. It resolves the actual disk
 * from config/filesystems.php's `upload_disk` / `media_private_disk` keys
 * (env MEDIA_DISK / MEDIA_PRIVATE_DISK — see also the legacy UPLOAD_DISK
 * fallback), so moving public/private media to S3 or Arvan Object Storage
 * later is an environment change, not a code change.
 *
 * Kinds:
 *   'public'  — theme media, sitemap, generally web-visible generated files
 *   'uploads' — avatars / legacy public/uploads-rooted content
 *   'private' — tenant attachments, app releases, never web-accessible
 *
 * In local mode (default) each kind keeps resolving to the exact disk name
 * it always has, so existing stored paths/URLs are unaffected. Once
 * MEDIA_DISK points at an object-storage disk (e.g. "arvan" or "s3"), the
 * 'public' and 'uploads' kinds converge on that single remote disk; the
 * legacy local/public split was only ever a local-path artifact.
 */
class MediaStorage
{
    public function __construct(private readonly StorageManager $files)
    {
    }

    /**
     * Resolve the actual Laravel disk name for a given content kind.
     */
    public function diskNameFor(string $kind = 'public', ?Site $site = null): string
    {
        if ($override = $this->resolveTenantOverride($kind, $site)) {
            return $override;
        }

        if ($kind === 'private') {
            return config('filesystems.media_private_disk', 'private');
        }

        $mode = config('filesystems.upload_disk', 'public');

        // Local disks keep their historical, distinct roots — regardless of
        // which of the two local disks MEDIA_DISK/UPLOAD_DISK happens to
        // name, 'uploads' content stays on the 'uploads' disk and 'public'
        // content stays on the 'public' disk. (Bug fixed here: this used to
        // return $mode for the 'public' kind, so setting UPLOAD_DISK=uploads
        // silently moved theme-media/sitemap reads onto the wrong disk and
        // made existing files 404.)
        if (in_array($mode, ['public', 'uploads'], true)) {
            return $kind === 'uploads' ? 'uploads' : 'public';
        }

        // Remote/object-storage mode: public and uploads content share one disk.
        return $mode;
    }

    public function disk(string $kind = 'public', ?Site $site = null): Filesystem
    {
        return Storage::disk($this->diskNameFor($kind, $site));
    }

    public function putFile(
        UploadedFile $file,
        string $directory,
        string $kind = 'public',
        ?string $filename = null,
        ?Site $site = null
    ): string {
        return $this->files->storeUploadedFile($file, $directory, $this->diskNameFor($kind, $site), $filename);
    }

    public function url(string $path, string $kind = 'public', ?Site $site = null): string
    {
        return $this->files->url($path, $this->diskNameFor($kind, $site));
    }

    /**
     * Resolve a URL for an avatar path stored under the legacy convention
     * (values persisted with a literal "uploads/" prefix, e.g.
     * "uploads/avatars/42/abc.jpg"). Strips that prefix before resolving so
     * existing rows keep working unchanged while the disk itself becomes
     * configurable via diskNameFor('uploads').
     *
     * A stored path is only a claim that a file was once written there. When the
     * file is genuinely gone (deleted by hand, lost with a media migration, or
     * written by a deployment whose uploads disk never synced) the database
     * still points at it, and emitting the URL anyway produces a 404 on every
     * page load. Resolving null instead lets the caller fall back to its
     * initials avatar, which is both correct and free — no failed request is
     * made at all.
     */
    public function avatarUrl(?string $storedPath, ?Site $site = null): ?string
    {
        if (!$storedPath) {
            return null;
        }

        try {
            $relative = str_starts_with($storedPath, 'uploads/') ? substr($storedPath, 8) : $storedPath;

            if (! $this->exists($relative, 'uploads', $site)) {
                \Illuminate\Support\Facades\Log::warning('avatarUrl: stored avatar is missing on disk', [
                    'stored_path' => $storedPath,
                    'disk'        => $this->diskNameFor('uploads', $site),
                ]);

                return null;
            }

            return $this->url($relative, 'uploads', $site);
        } catch (\Throwable $e) {
            // Fail gracefully instead of an HTTP 500: if the uploads disk cannot
            // be resolved (e.g. its root directory is missing and not creatable),
            // hide the avatar — the rest of the page keeps rendering.
            \Illuminate\Support\Facades\Log::warning('avatarUrl: unable to resolve avatar URL', [
                'stored_path' => $storedPath,
                'exception'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function delete(string $path, string $kind = 'public', ?Site $site = null): bool
    {
        return $this->files->delete($path, $this->diskNameFor($kind, $site));
    }

    public function exists(string $path, string $kind = 'public', ?Site $site = null): bool
    {
        return $this->files->exists($path, $this->diskNameFor($kind, $site));
    }

    /**
     * Extension point for future per-tenant storage isolation
     * (Tenant A -> Bucket A, Tenant B -> Bucket B). No per-site disk
     * assignment exists today, so this always returns null, meaning "use
     * the global disk for this kind". When that need becomes real, wire it
     * to a per-site storage-provider lookup here (see
     * app/Models/Infrastructure/InfraStorageProvider.php) — every call site
     * already routes through this method, so nothing else has to change.
     */
    protected function resolveTenantOverride(string $kind, ?Site $site): ?string
    {
        return null;
    }
}
