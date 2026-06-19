<?php

namespace App\Services\ThemeBuilder;

use App\Models\ThemePack;
use App\Models\ThemeTemplate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ThemeTemplateImportExportService
{
    // ── Template Export / Import ──────────────────────────────────

    public function exportTemplateJson(ThemeTemplate $template): string
    {
        return json_encode($template->toExportArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Import a template from a JSON string.
     * Returns the created ThemeTemplate or throws on invalid JSON/missing fields.
     */
    public function importTemplateJson(string $json, ?int $siteId = null, ?int $userId = null): ThemeTemplate
    {
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException('فایل JSON نامعتبر است.');
        }

        if (empty($data['blocks']) || !is_array($data['blocks'])) {
            throw new \InvalidArgumentException('قالب فاقد داده بلوک است.');
        }

        // Avoid duplicate UUID conflicts — always assign a fresh UUID
        $uuid = (string) Str::uuid();

        return ThemeTemplate::create([
            'uuid'              => $uuid,
            'type'              => $data['type']              ?? 'section',
            'category'          => $data['category']          ?? 'hero',
            'name'              => ($data['name'] ?? 'قالب وارد شده') . ' (Import)',
            'description'       => $data['description']       ?? null,
            'tags'              => $data['tags']               ?? [],
            'author'            => $data['author']             ?? null,
            'version'           => $data['version']            ?? '1.0.0',
            'license_type'      => $data['license_type']       ?? 'free',
            'compatibility'     => $data['compatibility']      ?? [],
            'blocks'            => $data['blocks'],
            'global_styles'     => $data['global_styles']      ?? null,
            'demo_data'         => $data['demo_data']          ?? null,
            'thumbnail_url'     => $data['thumbnail_url']      ?? null,
            'preview_image_url' => $data['preview_image_url']  ?? null,
            'is_public'         => false,
            'site_id'           => $siteId,
            'created_by'        => $userId,
        ]);
    }

    // ── Theme Pack Export / Import ────────────────────────────────

    public function exportPackJson(ThemePack $pack): string
    {
        return json_encode($pack->toExportArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Export a theme pack as a ZIP archive.
     * Returns the path to the temporary ZIP file.
     */
    public function exportPackZip(ThemePack $pack): string
    {
        $tmpDir  = sys_get_temp_dir();
        $zipPath = $tmpDir . '/theme-pack-' . $pack->uuid . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('خطا در ساخت فایل ZIP');
        }

        // manifest.json
        $zip->addFromString('manifest.json', json_encode([
            'format'  => 'theme-pack-v1',
            'name'    => $pack->name,
            'version' => $pack->version,
            'uuid'    => $pack->uuid,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // pack.json — full data
        $zip->addFromString('pack.json', $this->exportPackJson($pack));

        // Individual template files
        $templates = $pack->pack_data['templates'] ?? [];
        foreach ($templates as $i => $tplArray) {
            $zip->addFromString(
                "templates/template_{$i}.json",
                json_encode($tplArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        }

        $zip->close();
        return $zipPath;
    }

    /**
     * Import a theme pack from a JSON string.
     */
    public function importPackJson(string $json, ?int $siteId = null, ?int $userId = null): ThemePack
    {
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException('فایل JSON نامعتبر است.');
        }

        return ThemePack::create([
            'uuid'              => (string) Str::uuid(),
            'name'              => ($data['name'] ?? 'پک وارد شده') . ' (Import)',
            'description'       => $data['description']       ?? null,
            'version'           => $data['version']            ?? '1.0.0',
            'author'            => $data['author']             ?? null,
            'license_type'      => $data['license_type']       ?? 'free',
            'tags'              => $data['tags']               ?? [],
            'includes'          => $data['includes']           ?? [],
            'pack_data'         => $data['pack_data']          ?? [],
            'preview_image_url' => $data['preview_image_url']  ?? null,
            'is_public'         => false,
            'site_id'           => $siteId,
            'created_by'        => $userId,
        ]);
    }

    /**
     * Import a theme pack from a ZIP file.
     * Extracts pack.json and creates a ThemePack record.
     */
    public function importPackZip(string $zipPath, ?int $siteId = null, ?int $userId = null): ThemePack
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \InvalidArgumentException('فایل ZIP نامعتبر است.');
        }

        $packJson = $zip->getFromName('pack.json');
        $zip->close();

        if ($packJson === false) {
            throw new \InvalidArgumentException('فایل pack.json در ZIP یافت نشد.');
        }

        return $this->importPackJson($packJson, $siteId, $userId);
    }

    /**
     * Build a theme pack from a Theme's current pages + templates.
     */
    public function buildPackFromTheme(\App\Models\Theme $theme, array $meta, ?int $siteId = null, ?int $userId = null): ThemePack
    {
        $pages   = $theme->pages ?? [];
        $headers = [];
        $footers = [];
        $pageList = [];

        foreach ($pages as $key => $page) {
            if ($key === '__header__' || str_starts_with($key, 'header')) {
                $headers[] = ['key' => $key, 'title' => $page['title'] ?? $key, 'blocks' => $page['blocks'] ?? []];
            } elseif ($key === '__footer__' || str_starts_with($key, 'footer')) {
                $footers[] = ['key' => $key, 'title' => $page['title'] ?? $key, 'blocks' => $page['blocks'] ?? []];
            } else {
                $pageList[] = ['key' => $key, 'title' => $page['title'] ?? $key, 'blocks' => $page['blocks'] ?? []];
            }
        }

        $includes = [];
        if (!empty($theme->global_styles)) $includes[] = 'global_styles';
        if (!empty($headers))              $includes[] = 'headers';
        if (!empty($footers))              $includes[] = 'footers';
        if (!empty($pageList))             $includes[] = 'pages';

        return ThemePack::create([
            'uuid'         => (string) Str::uuid(),
            'name'         => $meta['name'] ?? $theme->name . ' Pack',
            'description'  => $meta['description'] ?? null,
            'version'      => $meta['version'] ?? '1.0.0',
            'author'       => $meta['author'] ?? null,
            'license_type' => $meta['license_type'] ?? 'free',
            'tags'         => $meta['tags'] ?? [],
            'includes'     => $includes,
            'pack_data'    => [
                'global_styles' => $theme->global_styles ?? [],
                'theme_settings'=> ['name' => $theme->name, 'preset' => $theme->preset ?? null],
                'headers'       => $headers,
                'footers'       => $footers,
                'pages'         => $pageList,
                'templates'     => [],
                'demo_data'     => $meta['demo_data'] ?? [],
            ],
            'is_public'    => $meta['is_public'] ?? false,
            'site_id'      => $siteId,
            'created_by'   => $userId,
        ]);
    }
}
