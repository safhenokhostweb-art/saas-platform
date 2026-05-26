<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PHASE 8.0 — Secure, access-controlled attachment streaming.
 *
 * Serves files stored on the PRIVATE disk.
 * Before streaming, validates:
 *   1. Authenticated user (admin) or valid tenant.api credential (via middleware)
 *   2. The requested path belongs to the authenticated tenant's site
 *   3. The file actually exists on the private disk
 *
 * Route: GET /admin/attachments/{type}/{path}
 * Tenant API: GET /api/tenant/attachments/{path}  (guarded by tenant.api middleware)
 */
class SecureAttachmentController extends Controller
{
    private const ALLOWED_MIME_EXTENSIONS = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'pdf'  => 'application/pdf',
        'txt'  => 'text/plain',
        'csv'  => 'text/csv',
        'zip'  => 'application/zip',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /**
     * Stream an attachment from the private disk to an authenticated admin.
     */
    public function download(Request $request, string $path): StreamedResponse
    {
        // Sanitize path — no traversal, no null bytes
        $path = $this->sanitizePath($path);

        if ($path === null) {
            abort(400, 'Invalid attachment path.');
        }

        if (!Storage::disk('private')->exists($path)) {
            abort(404, 'Attachment not found.');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime      = static::ALLOWED_MIME_EXTENSIONS[$extension] ?? 'application/octet-stream';
        $filename  = basename($path);

        return Storage::disk('private')->download($path, $filename, [
            'Content-Type'              => $mime,
            'X-Content-Type-Options'    => 'nosniff',
            'Content-Disposition'       => 'attachment; filename="' . addslashes($filename) . '"',
        ]);
    }

    /**
     * Stream a tenant-owned attachment (used from API routes).
     * Validates the file belongs to the requesting tenant's site.
     */
    public function tenantDownload(Request $request, string $path): StreamedResponse
    {
        $site = $request->attributes->get('tenant_site');

        if (!$site) {
            abort(403, 'Tenant authentication required.');
        }

        $path = $this->sanitizePath($path);

        if ($path === null) {
            abort(400, 'Invalid attachment path.');
        }

        // Enforce path prefix matches the tenant's site folder
        $expectedPrefix = "ticket-attachments/site-{$site->id}/";
        if (!str_starts_with($path, $expectedPrefix)) {
            abort(403, 'Access to this attachment is not allowed for your tenant.');
        }

        if (!Storage::disk('private')->exists($path)) {
            abort(404, 'Attachment not found.');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime      = static::ALLOWED_MIME_EXTENSIONS[$extension] ?? 'application/octet-stream';
        $filename  = basename($path);

        return Storage::disk('private')->download($path, $filename, [
            'Content-Type'           => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition'    => 'attachment; filename="' . addslashes($filename) . '"',
        ]);
    }

    private function sanitizePath(string $path): ?string
    {
        // Reject null bytes and path traversal sequences
        if (str_contains($path, "\0") || str_contains($path, '..') || str_contains($path, '\\')) {
            return null;
        }

        $path = ltrim($path, '/');

        if ($path === '') {
            return null;
        }

        // Only allow alphanumeric, hyphens, underscores, dots, forward slashes
        if (!preg_match('#^[a-zA-Z0-9/_\-\.]+$#', $path)) {
            return null;
        }

        return $path;
    }
}
