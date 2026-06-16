<?php

namespace App\Services\Infrastructure;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Centralised storage abstraction for the SaaS platform.
 *
 * All uploads, CSV exports/imports and file deletions MUST go through
 * this service. Never call mkdir / fopen / $file->move() directly in
 * application code — those calls belong here and here only.
 *
 * Configured disks (config/filesystems.php):
 *   public   → storage/app/public    (web via /storage symlink)
 *   private  → storage/app/private   (never web-accessible)
 *   local    → storage/app/private   (Laravel default, private)
 *   exports  → storage/app/exports   (temporary CSV export files)
 *   uploads  → public/uploads        (legacy avatar path, web via /uploads)
 *   s3       → AWS S3
 *   minio    → MinIO / compatible object storage (path-style endpoint)
 */
class StorageManager
{
    /**
     * Store an UploadedFile on the given disk inside $directory.
     *
     * Works around the PHP 8.4 + Flysystem TypeError where getRealPath()
     * returns false for HTTP-uploaded tmp files, causing fopen(false,'r')
     * to throw a ValueError. The file is first moved to a controlled temp
     * location via native PHP move_uploaded_file(), then streamed to Storage.
     *
     * Returns the relative path within the disk (e.g. "avatars/42/abc.jpg").
     */
    public function storeUploadedFile(
        UploadedFile $file,
        string $directory,
        string $disk,
        ?string $filename = null
    ): string {
        $ext      = $file->guessExtension() ?? ($file->getClientOriginalExtension() ?: 'bin');
        $basename = $filename ?? (Str::random(40) . '.' . $ext);
        $diskPath = trim($directory, '/') . '/' . $basename;

        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sm_' . Str::random(16) . '.' . $ext;
        $file->move(dirname($tmpPath), basename($tmpPath));

        $stream = fopen($tmpPath, 'rb');
        try {
            Storage::disk($disk)->writeStream($diskPath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            @unlink($tmpPath);
        }

        return $diskPath;
    }

    /**
     * Write a UTF-8 CSV (with BOM for Excel/Farsi) to a disk via a temp file.
     * Avoids holding the entire result set in memory.
     *
     * Returns the relative path within the disk.
     */
    public function writeCsv(
        string $directory,
        string $filename,
        array $headers,
        iterable $rows,
        string $disk = 'exports'
    ): string {
        $diskPath = trim($directory, '/') !== ''
            ? trim($directory, '/') . '/' . $filename
            : $filename;

        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'csv_' . Str::random(16) . '.csv';
        $handle  = fopen($tmpPath, 'w');
        try {
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
        } finally {
            fclose($handle);
        }

        $stream = fopen($tmpPath, 'rb');
        try {
            Storage::disk($disk)->writeStream($diskPath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            @unlink($tmpPath);
        }

        return $diskPath;
    }

    /**
     * Open a stored file as a readable PHP resource for streaming reads (e.g. fgetcsv).
     * Caller is responsible for closing the resource with fclose().
     *
     * @return resource
     */
    public function readStream(string $path, string $disk)
    {
        return Storage::disk($disk)->readStream($path);
    }

    /** Check whether a file exists on a disk. */
    public function exists(string $path, string $disk): bool
    {
        return Storage::disk($disk)->exists($path);
    }

    /** Delete a file from a disk. Silent no-op if the file does not exist. */
    public function delete(string $path, string $disk): bool
    {
        if (!Storage::disk($disk)->exists($path)) {
            return true;
        }
        return (bool) Storage::disk($disk)->delete($path);
    }

    /** Serve a file as a streamed download response. */
    public function download(string $path, string $disk, ?string $downloadName = null): StreamedResponse
    {
        return Storage::disk($disk)->download($path, $downloadName);
    }

    /** Return the public URL for a file on a public-visibility disk. */
    public function url(string $path, string $disk = 'public'): string
    {
        return Storage::disk($disk)->url($path);
    }

    /**
     * Remove the file at $path from $disk only if it exists.
     * Alias for delete() — use this when the semantic is "clean up old file."
     */
    public function deleteIfExists(string $path, string $disk): void
    {
        if ($path !== '' && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    /**
     * Sanitize a user-supplied filename so it is safe to use as a storage path component.
     *
     * - Strips directory separators (/ and \)
     * - Strips null bytes and control characters
     * - Strips leading dots (hidden-file trick)
     * - Replaces whitespace with underscores
     * - Collapses multiple consecutive dashes/underscores
     * - Limits to 200 characters (keeps extension)
     *
     * Returns the sanitized basename (no directory component).
     */
    public static function sanitizeFilename(string $name): string
    {
        // Remove null bytes
        $name = str_replace("\0", '', $name);

        // Strip directory separators — take only the basename
        $name = basename(str_replace('\\', '/', $name));

        // Remove control characters
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);

        // Replace spaces/tabs with underscore
        $name = preg_replace('/\s+/', '_', $name);

        // Remove characters that are not alphanumeric, dash, underscore, or dot
        $name = preg_replace('/[^\w.\-]/', '-', $name);

        // Strip leading dots (e.g. ".htaccess", ".env")
        $name = ltrim($name, '.');

        // Collapse multiple dots (path obfuscation: "file...ext")
        $name = preg_replace('/\.{2,}/', '.', $name);

        // Collapse consecutive dashes/underscores
        $name = preg_replace('/[-_]{2,}/', '-', $name);

        // Limit total length to 200 chars, preserving extension
        if (strlen($name) > 200) {
            $ext  = pathinfo($name, PATHINFO_EXTENSION);
            $base = substr(pathinfo($name, PATHINFO_FILENAME), 0, 195 - strlen($ext));
            $name = $ext !== '' ? "{$base}.{$ext}" : $base;
        }

        return $name !== '' ? $name : 'file';
    }

    /**
     * Validate that an UploadedFile has an allowed extension AND allowed MIME type.
     *
     * Throws \InvalidArgumentException on failure — callers should catch this
     * and return a validation error response.
     *
     * @param array<string> $allowedExtensions  e.g. ['jpg','jpeg','png','webp']
     * @param array<string> $allowedMimes        e.g. ['image/jpeg','image/png','image/webp']
     */
    public static function validateUpload(
        UploadedFile $file,
        array $allowedExtensions,
        array $allowedMimes
    ): void {
        $ext  = strtolower($file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getMimeType());

        if ($allowedExtensions !== [] && !in_array($ext, $allowedExtensions, true)) {
            throw new \InvalidArgumentException(
                "پسوند فایل مجاز نیست: {$ext}. مجاز: " . implode(', ', $allowedExtensions)
            );
        }

        if ($allowedMimes !== [] && !in_array($mime, $allowedMimes, true)) {
            throw new \InvalidArgumentException(
                "نوع MIME مجاز نیست: {$mime}. مجاز: " . implode(', ', $allowedMimes)
            );
        }
    }

    /**
     * Delete a temporary export file from the exports disk.
     * Silent no-op if file does not exist.
     */
    public function deleteExport(string $path): void
    {
        $this->deleteIfExists($path, 'exports');
    }
}
