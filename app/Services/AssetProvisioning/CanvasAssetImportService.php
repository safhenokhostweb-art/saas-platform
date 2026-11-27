<?php

namespace App\Services\AssetProvisioning;

use App\Models\CanvasAssetVersion;
use App\Models\CanvasButtonPack;
use App\Models\CanvasButtonPreset;
use App\Models\CanvasFooterPreset;
use App\Models\CanvasHeaderPreset;
use App\Models\CanvasIconItem;
use App\Models\CanvasIconPack;
use App\Models\CanvasSectionPreset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\ThemeRuntime\Access\AssetRef;

class CanvasAssetImportService
{
    /**
     * Imports assets from a parsed array payload.
     *
     * @param array $payload
     * @return array Summary of import counts
     */
    public function import(array $payload): array
    {
        $summary = [
            'button_packs'    => 0,
            'button_presets'  => 0,
            'icon_packs'      => 0,
            'icon_items'      => 0,
            'header_presets'  => 0,
            'footer_presets'  => 0,
            'section_presets' => 0,
            'motion_graphics' => 0,
            'versions'        => 0,
        ];

        DB::transaction(function () use ($payload, &$summary) {
            // 1. Button Packs
            if (!empty($payload['button_packs']) && is_array($payload['button_packs'])) {
                foreach ($payload['button_packs'] as $packData) {
                    $slug = trim($packData['slug'] ?? '');
                    if ($slug === '') continue;

                    $version = trim($packData['version'] ?? '1.0.0');
                    $pack = CanvasButtonPack::query()->updateOrCreate(
                        ['slug' => $slug],
                        [
                            'title'           => $packData['title'] ?? $slug,
                            'description'     => $packData['description'] ?? null,
                            'category_label'  => $packData['category_label'] ?? 'عمومی',
                            'preview_image'   => $packData['preview_image'] ?? null,
                            'access_type'     => in_array($packData['access_type'] ?? '', ['free', 'paid'], true) ? $packData['access_type'] : 'free',
                            'price'           => (int) ($packData['price'] ?? 0),
                            'discount_price'  => isset($packData['discount_price']) && $packData['discount_price'] !== null ? (int) $packData['discount_price'] : null,
                            'current_version' => $version,
                            'is_active'       => (bool) ($packData['is_active'] ?? true),
                            'sort_order'      => (int) ($packData['sort_order'] ?? 0),
                        ]
                    );
                    $summary['button_packs']++;

                    if (!empty($version)) {
                        $this->recordVersion(AssetRef::KIND_BUTTON_PACK, $slug, $version, $packData['changelog'] ?? 'وارد شده از فایل دسته‌ای');
                        $summary['versions']++;
                    }

                    if (!empty($packData['presets']) && is_array($packData['presets'])) {
                        foreach ($packData['presets'] as $prData) {
                            $prSlug = trim($prData['slug'] ?? '');
                            if ($prSlug === '') continue;

                            CanvasButtonPreset::query()->updateOrCreate(
                                [
                                    'pack_id' => $pack->id,
                                    'slug'    => $prSlug,
                                ],
                                [
                                    'title'        => $prData['title'] ?? $prSlug,
                                    'description'  => $prData['description'] ?? null,
                                    'css_tokens'   => is_array($prData['css_tokens'] ?? null) ? $prData['css_tokens'] : (json_decode($prData['css_tokens'] ?? '{}', true) ?: []),
                                    'preview_html' => $prData['preview_html'] ?? null,
                                    'sort_order'   => (int) ($prData['sort_order'] ?? 0),
                                ]
                            );
                            $summary['button_presets']++;
                        }
                    }
                }
            }

            // 2. Icon Packs
            if (!empty($payload['icon_packs']) && is_array($payload['icon_packs'])) {
                foreach ($payload['icon_packs'] as $packData) {
                    $slug = trim($packData['slug'] ?? '');
                    if ($slug === '') continue;

                    $version = trim($packData['version'] ?? '1.0.0');
                    $pack = CanvasIconPack::query()->updateOrCreate(
                        ['slug' => $slug],
                        [
                            'title'           => $packData['title'] ?? $slug,
                            'description'     => $packData['description'] ?? null,
                            'category_label'  => $packData['category_label'] ?? 'عمومی',
                            'preview_image'   => $packData['preview_image'] ?? null,
                            'access_type'     => in_array($packData['access_type'] ?? '', ['free', 'paid'], true) ? $packData['access_type'] : 'free',
                            'price'           => (int) ($packData['price'] ?? 0),
                            'discount_price'  => isset($packData['discount_price']) && $packData['discount_price'] !== null ? (int) $packData['discount_price'] : null,
                            'current_version' => $version,
                            'is_active'       => (bool) ($packData['is_active'] ?? true),
                            'sort_order'      => (int) ($packData['sort_order'] ?? 0),
                        ]
                    );
                    $summary['icon_packs']++;

                    if (!empty($version)) {
                        $this->recordVersion(AssetRef::KIND_ICON_PACK, $slug, $version, $packData['changelog'] ?? 'وارد شده از فایل دسته‌ای');
                        $summary['versions']++;
                    }

                    if (!empty($packData['items']) && is_array($packData['items'])) {
                        foreach ($packData['items'] as $itData) {
                            $itSlug = trim($itData['slug'] ?? '');
                            if ($itSlug === '') continue;

                            CanvasIconItem::query()->updateOrCreate(
                                [
                                    'pack_id' => $pack->id,
                                    'slug'    => $itSlug,
                                ],
                                [
                                    'title'       => $itData['title'] ?? $itSlug,
                                    'keywords'    => is_array($itData['keywords'] ?? null) ? $itData['keywords'] : (json_decode($itData['keywords'] ?? '[]', true) ?: []),
                                    'svg_content' => $itData['svg_content'] ?? '<svg></svg>',
                                    'sort_order'  => (int) ($itData['sort_order'] ?? 0),
                                ]
                            );
                            $summary['icon_items']++;
                        }
                    }
                }
            }

            // 3. Header Presets
            if (!empty($payload['header_presets']) && is_array($payload['header_presets'])) {
                foreach ($payload['header_presets'] as $hData) {
                    $slug = trim($hData['slug'] ?? '');
                    if ($slug === '') continue;

                    $version = trim($hData['version'] ?? '1.0.0');
                    CanvasHeaderPreset::query()->updateOrCreate(
                        ['slug' => $slug],
                        [
                            'title'           => $hData['title'] ?? $slug,
                            'category_label'  => $hData['category_label'] ?? 'عمومی',
                            'preview_image'   => $hData['preview_image'] ?? null,
                            'blocks'          => $hData['blocks'] ?? null,
                            'component_alias' => $hData['component_alias'] ?? $slug,
                            'access_type'     => in_array($hData['access_type'] ?? '', ['free', 'paid'], true) ? $hData['access_type'] : 'free',
                            'price'           => (int) ($hData['price'] ?? 0),
                            'discount_price'  => isset($hData['discount_price']) && $hData['discount_price'] !== null ? (int) $hData['discount_price'] : null,
                            'current_version' => $version,
                            'is_active'       => (bool) ($hData['is_active'] ?? true),
                            'sort_order'      => (int) ($hData['sort_order'] ?? 0),
                        ]
                    );
                    $summary['header_presets']++;

                    if (!empty($version)) {
                        $this->recordVersion(AssetRef::KIND_HEADER, $slug, $version, $hData['changelog'] ?? 'وارد شده از فایل دسته‌ای');
                        $summary['versions']++;
                    }
                }
            }

            // 4. Footer Presets
            if (!empty($payload['footer_presets']) && is_array($payload['footer_presets'])) {
                foreach ($payload['footer_presets'] as $fData) {
                    $slug = trim($fData['slug'] ?? '');
                    if ($slug === '') continue;

                    $version = trim($fData['version'] ?? '1.0.0');
                    CanvasFooterPreset::query()->updateOrCreate(
                        ['slug' => $slug],
                        [
                            'title'           => $fData['title'] ?? $slug,
                            'description'     => $fData['description'] ?? null,
                            'category_label'  => $fData['category_label'] ?? 'فوتر',
                            'preview_image'   => $fData['preview_image'] ?? null,
                            'blocks'          => $fData['blocks'] ?? null,
                            'access_type'     => in_array($fData['access_type'] ?? '', ['free', 'paid'], true) ? $fData['access_type'] : 'free',
                            'price'           => (int) ($fData['price'] ?? 0),
                            'discount_price'  => isset($fData['discount_price']) && $fData['discount_price'] !== null ? (int) $fData['discount_price'] : null,
                            'current_version' => $version,
                            'is_active'       => (bool) ($fData['is_active'] ?? true),
                            'sort_order'      => (int) ($fData['sort_order'] ?? 0),
                        ]
                    );
                    $summary['footer_presets']++;

                    if (!empty($version)) {
                        $this->recordVersion(AssetRef::KIND_FOOTER, $slug, $version, $fData['changelog'] ?? 'وارد شده از فایل دسته‌ای');
                        $summary['versions']++;
                    }
                }
            }

            // 5. Section Presets
            if (!empty($payload['section_presets']) && is_array($payload['section_presets'])) {
                foreach ($payload['section_presets'] as $sData) {
                    $slug = trim($sData['slug'] ?? '');
                    if ($slug === '') continue;

                    $version = trim($sData['version'] ?? '1.0.0');
                    CanvasSectionPreset::query()->updateOrCreate(
                        ['slug' => $slug],
                        [
                            'title'           => $sData['title'] ?? $slug,
                            'description'     => $sData['description'] ?? null,
                            'category_label'  => $sData['category_label'] ?? 'سکشن',
                            'preview_image'   => $sData['preview_image'] ?? null,
                            'blocks'          => $sData['blocks'] ?? null,
                            'access_type'     => in_array($sData['access_type'] ?? '', ['free', 'paid'], true) ? $sData['access_type'] : 'free',
                            'price'           => (int) ($sData['price'] ?? 0),
                            'discount_price'  => isset($sData['discount_price']) && $sData['discount_price'] !== null ? (int) $sData['discount_price'] : null,
                            'current_version' => $version,
                            'is_active'       => (bool) ($sData['is_active'] ?? true),
                            'sort_order'      => (int) ($sData['sort_order'] ?? 0),
                        ]
                    );
                    $summary['section_presets']++;

                    if (!empty($version)) {
                        $this->recordVersion(AssetRef::KIND_SECTION, $slug, $version, $sData['changelog'] ?? 'وارد شده از فایل دسته‌ای');
                        $summary['versions']++;
                    }
                }
            }

            // 6. Motion Graphics
            if (!empty($payload['motion_graphics']) && is_array($payload['motion_graphics']) && class_exists(MotionGraphic::class)) {
                foreach ($payload['motion_graphics'] as $mData) {
                    $slug = trim($mData['slug'] ?? '');
                    if ($slug === '') continue;

                    $version = trim($mData['version'] ?? '1.0.0');
                    MotionGraphic::query()->updateOrCreate(
                        ['slug' => $slug],
                        [
                            'name'            => $mData['name'] ?? $mData['title'] ?? $slug,
                            'description'     => $mData['description'] ?? null,
                            'category'        => $mData['category'] ?? 'عمومی',
                            'preview_image'   => $mData['preview_image'] ?? null,
                            'access_type'     => in_array($mData['access_type'] ?? '', ['free', 'paid'], true) ? $mData['access_type'] : 'free',
                            'price'           => (int) ($mData['price'] ?? 0),
                            'discount_price'  => isset($mData['discount_price']) && $mData['discount_price'] !== null ? (int) $mData['discount_price'] : null,
                            'current_version' => $version,
                            'is_active'       => (bool) ($mData['is_active'] ?? true),
                        ]
                    );
                    $summary['motion_graphics']++;

                    if (!empty($version)) {
                        $this->recordVersion(AssetRef::KIND_MOTION, $slug, $version, $mData['changelog'] ?? 'وارد شده از فایل دسته‌ای');
                        $summary['versions']++;
                    }
                }
            }
        });

        return $summary;
    }

    /**
     * Reads a JSON file from disk and imports it.
     */
    public function importFromFile(string $filePath): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException("فایل در مسیر {$filePath} یافت نشد یا قابل خواندن نیست.");
        }

        $raw = file_get_contents($filePath);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException("فرمت فایل JSON نامعتبر است.");
        }

        return $this->import($data);
    }

    protected function recordVersion(string $kind, string $slug, string $version, ?string $changelog): void
    {
        CanvasAssetVersion::query()->updateOrCreate(
            [
                'asset_kind' => $kind,
                'asset_slug' => $slug,
                'version'    => $version,
            ],
            [
                'changelog'    => $changelog ?: 'انتشار نسخه ' . $version,
                'published_at' => now(),
            ]
        );
    }
}
