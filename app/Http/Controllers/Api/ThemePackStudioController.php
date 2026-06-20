<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ThemeMarketplace;
use App\Models\ThemePack;
use App\Services\ThemeBuilder\ThemeTemplateImportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * TB-AI Studio Import Pipeline API
 *
 * Endpoints consumed by the local TB-AI Studio desktop tool.
 * Auth: AuthenticateStudioApi middleware (Bearer key + optional HMAC).
 */
class ThemePackStudioController extends Controller
{
    public function __construct(
        private readonly ThemeTemplateImportExportService $io,
    ) {}

    /**
     * POST /api/v1/studio/import
     * Accept a theme pack ZIP from TB-AI Studio.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'pack' => 'required|file|mimes:zip|max:51200', // 50MB max
            'meta' => 'nullable|string',
        ]);

        $meta = [];
        if ($request->has('meta')) {
            $meta = json_decode($request->input('meta'), true) ?? [];
        }

        try {
            $pack = $this->io->importPackZip(
                $request->file('pack')->path(),
                null, // platform-level pack (no site_id)
                null,
            );

            // Mark as Studio import
            $tags = $pack->tags ?? [];
            if (!in_array('studio-import', $tags, true)) $tags[] = 'studio-import';
            $pack->update(['tags' => $tags]);

            // Persist extra meta from Studio
            if (!empty($meta['name']))        $pack->update(['name'        => $meta['name']]);
            if (!empty($meta['description'])) $pack->update(['description' => $meta['description']]);
            if (!empty($meta['author']))      $pack->update(['author'      => $meta['author']]);
            if (!empty($meta['version']))     $pack->update(['version'     => $meta['version']]);

            $previewUrl = url("/admin/theme-packs/{$pack->id}");

            return response()->json([
                'message' => 'Pack imported successfully.',
                'data'    => [
                    'id'          => $pack->id,
                    'uuid'        => $pack->uuid,
                    'name'        => $pack->name,
                    'preview_url' => $previewUrl,
                    'install_url' => url("/api/v1/studio/install/{$pack->uuid}"),
                ],
            ]);

        } catch (\Throwable $e) {
            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/studio/validate
     * Validate a ZIP without importing.
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate(['pack' => 'required|file|mimes:zip|max:51200']);

        $issues = [];
        $info   = [];

        $zip = new ZipArchive();
        if ($zip->open($request->file('pack')->path()) !== true) {
            return response()->json(['valid' => false, 'issues' => ['Invalid ZIP file.']]);
        }

        // Check manifest.json
        $manifest = $zip->getFromName('manifest.json');
        if ($manifest === false) {
            $issues[] = 'manifest.json missing.';
        } else {
            $m = json_decode($manifest, true) ?? [];
            if (($m['format'] ?? '') !== 'theme-pack-v1') {
                $issues[] = 'Unsupported format: ' . ($m['format'] ?? 'unknown') . '. Expected theme-pack-v1.';
            }
            $info['manifest'] = $m;
        }

        // Check pack.json
        $packJson = $zip->getFromName('pack.json');
        if ($packJson === false) {
            $issues[] = 'pack.json missing.';
        } else {
            $pack = json_decode($packJson, true) ?? [];
            if (empty($pack['name'])) $issues[] = 'pack.json: name field missing.';
            if (empty($pack['pack_data'])) $issues[] = 'pack.json: pack_data missing.';

            $info['pack'] = [
                'name'        => $pack['name']        ?? null,
                'version'     => $pack['version']     ?? null,
                'author'      => $pack['author']       ?? null,
                'license'     => $pack['license_type'] ?? null,
                'pages'       => count($pack['pack_data']['pages']     ?? []),
                'templates'   => count($pack['pack_data']['templates'] ?? []),
                'headers'     => count($pack['pack_data']['headers']   ?? []),
                'footers'     => count($pack['pack_data']['footers']   ?? []),
            ];
        }

        $zip->close();

        return response()->json([
            'valid'  => empty($issues),
            'issues' => $issues,
            'info'   => $info,
        ]);
    }

    /**
     * GET /api/v1/studio/preview/{uuid}
     * Return pack contents summary for preview.
     */
    public function preview(string $uuid): JsonResponse
    {
        $pack = ThemePack::where('uuid', $uuid)->firstOrFail();

        return response()->json([
            'data' => [
                'uuid'        => $pack->uuid,
                'name'        => $pack->name,
                'description' => $pack->description,
                'version'     => $pack->version,
                'author'      => $pack->author,
                'tags'        => $pack->tags,
                'summary'     => $pack->contentSummary(),
                'global_styles' => $pack->pack_data['global_styles'] ?? [],
                'pages'       => array_map(fn($p) => ['title' => $p['title'] ?? '', 'slug' => $p['slug'] ?? '', 'block_count' => count($p['blocks'] ?? [])], $pack->pack_data['pages'] ?? []),
                'templates'   => array_map(fn($t) => ['name' => $t['name'] ?? '', 'category' => $t['category'] ?? ''], $pack->pack_data['templates'] ?? []),
            ],
        ]);
    }

    /**
     * POST /api/v1/studio/install/{uuid}
     * Activate pack (mark is_public=true, ready for marketplace).
     */
    public function install(string $uuid): JsonResponse
    {
        $pack = ThemePack::where('uuid', $uuid)->firstOrFail();
        $pack->update(['is_public' => true, 'is_active' => true]);

        return response()->json([
            'message' => 'Pack installed and made public.',
            'data'    => ['uuid' => $pack->uuid, 'name' => $pack->name],
        ]);
    }

    /**
     * POST /api/v1/studio/publish/{uuid}
     * Create or update a marketplace listing for this pack.
     */
    public function publish(string $uuid, Request $request): JsonResponse
    {
        $pack = ThemePack::where('uuid', $uuid)->firstOrFail();

        $listing = $request->validate([
            'price'        => 'nullable|numeric|min:0',
            'is_featured'  => 'nullable|boolean',
            'category'     => 'nullable|string|max:100',
            'preview_url'  => 'nullable|url',
        ]);

        // Ensure pack is public
        $pack->update(['is_public' => true, 'is_active' => true]);

        // Check if marketplace uses theme_marketplace table (TB-2)
        if (class_exists(\App\Models\ThemeMarketplace::class)) {
            $existing = \App\Models\ThemeMarketplace::where('theme_pack_uuid', $uuid)->first();

            if ($existing) {
                $existing->update([
                    'price'       => $listing['price'] ?? $existing->price,
                    'is_featured' => $listing['is_featured'] ?? $existing->is_featured,
                ]);
                $marketplaceId = $existing->id;
            } else {
                $mp = \App\Models\ThemeMarketplace::create([
                    'theme_pack_uuid' => $uuid,
                    'name'            => $pack->name,
                    'description'     => $pack->description,
                    'price'           => $listing['price'] ?? 0,
                    'is_featured'     => $listing['is_featured'] ?? false,
                    'is_active'       => true,
                    'tags'            => $pack->tags ?? [],
                    'preview_url'     => $listing['preview_url'] ?? null,
                ]);
                $marketplaceId = $mp->id;
            }

            return response()->json([
                'message'        => 'Pack published to marketplace.',
                'marketplace_id' => $marketplaceId,
                'marketplace_url' => url('/admin/theme-marketplace'),
            ]);
        }

        return response()->json([
            'message' => 'Pack activated (marketplace table not available).',
            'data'    => ['uuid' => $pack->uuid],
        ]);
    }
}
