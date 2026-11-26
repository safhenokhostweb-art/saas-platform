<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CanvasAssetVersion;
use App\Models\CanvasButtonPack;
use App\Models\CanvasButtonPreset;
use App\Models\CanvasHeaderPreset;
use App\Models\CanvasIconItem;
use App\Models\CanvasIconPack;
use App\Models\Site;
use App\Models\SiteAssetGrant;
use App\Services\AssetProvisioning\CanvasAssetProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\ThemeRuntime\Access\AssetRef;
use SaaSPlatform\ThemeRuntime\Contracts\AssetVersionMetadata;

/**
 * Unified Admin Controller for Canvas Assets:
 * 1. Motion Graphics (individual licensing & pricing)
 * 2. Header Presets (individual & pack licensing & pricing)
 * 3. Button Libraries (pack/batch licensing & pricing)
 * 4. Icon Libraries (pack/batch licensing & pricing)
 */
class CanvasAssetAdminController extends Controller
{
    public function __construct(
        protected CanvasAssetProvisioningService $provisioningService,
    ) {}

    public function index(Request $request): View
    {
        $activeTab = $request->query('tab', 'buttons');

        $buttonPacks = CanvasButtonPack::query()
            ->withCount('presets')
            ->with(['presets' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $iconPacks = CanvasIconPack::query()
            ->withCount('items')
            ->with(['items' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $headerPresets = CanvasHeaderPreset::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $motions = class_exists(MotionGraphic::class)
            ? MotionGraphic::query()->whereNull('archived_at')->orderBy('id')->get()
            : collect();

        $sites = Site::query()->orderBy('title')->get(['id', 'title', 'domain']);

        $grantCounts = SiteAssetGrant::query()->active()
            ->selectRaw('asset_kind, asset_key, COUNT(*) as aggregate')
            ->groupBy('asset_kind', 'asset_key')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->asset_kind . ':' . $row->asset_key => (int) $row->aggregate]);

        // Versions for quick lookup
        $versions = CanvasAssetVersion::query()
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn ($v) => $v->asset_kind . ':' . ($v->asset_slug ?? $v->asset_key));

        return view('admin.canvas-assets.index', [
            'activeTab' => $activeTab,
            'buttonPacks' => $buttonPacks,
            'iconPacks' => $iconPacks,
            'headerPresets' => $headerPresets,
            'motions' => $motions,
            'sites' => $sites,
            'grantCounts' => $grantCounts,
            'versions' => $versions,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // BUTTON PACKS & PRESETS
    // ─────────────────────────────────────────────────────────────

    public function storeButtonPack(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'slug' => 'nullable|string|max:100|unique:canvas_button_packs,slug',
            'category_label' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'preview_image' => 'nullable|string|max:255',
            'access_type' => ['required', Rule::in(['free', 'paid'])],
            'price' => 'nullable|required_if:access_type,paid|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'current_version' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $data['slug'] = !empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['title']);
        $data['current_version'] = ($data['current_version'] ?? null) ?: '1.0.0';
        $data['is_active'] = $request->has('is_active');
        $data['price'] = $data['price'] ?? 0;

        $pack = CanvasButtonPack::create($data);

        // Record initial version
        CanvasAssetVersion::firstOrCreate(
            [
                'asset_kind' => AssetRef::KIND_BUTTON_PACK,
                'asset_slug' => $pack->slug,
                'version' => $pack->current_version,
            ],
            [
                'changelog' => 'نسخه اولیه کتابخانه دکمه',
                'published_by_admin_id' => Auth::id(),
                'published_at' => now(),
            ]
        );

        return redirect()->route('admin.canvas-assets.index', ['tab' => 'buttons'])
            ->with('success', "پک دکمه «{$pack->title}» با موفقیت ایجاد شد.");
    }

    public function updateButtonPack(Request $request, CanvasButtonPack $pack): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'slug' => ['required', 'string', 'max:100', Rule::unique('canvas_button_packs', 'slug')->ignore($pack->id)],
            'category_label' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'preview_image' => 'nullable|string|max:255',
            'access_type' => ['required', Rule::in(['free', 'paid'])],
            'price' => 'nullable|required_if:access_type,paid|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $data['slug'] = Str::slug($data['slug']);
        $data['is_active'] = $request->has('is_active');
        $data['price'] = $data['price'] ?? 0;

        $pack->update($data);

        return redirect()->route('admin.canvas-assets.index', ['tab' => 'buttons'])
            ->with('success', "پک دکمه «{$pack->title}» بروزرسانی شد.");
    }

    public function destroyButtonPack(CanvasButtonPack $pack): RedirectResponse
    {
        $title = $pack->title;
        $pack->delete();

        return redirect()->route('admin.canvas-assets.index', ['tab' => 'buttons'])
            ->with('success', "پک دکمه «{$title}» حذف شد.");
    }

    public function storeButtonPreset(Request $request, CanvasButtonPack $pack): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'slug' => 'nullable|string|max:100',
            'css_tokens' => 'required',
            'preview_html' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        $data['pack_id'] = $pack->id;
        $data['slug'] = !empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['title']);

        if (is_string($data['css_tokens'])) {
            $decoded = json_decode($data['css_tokens'], true);
            $data['css_tokens'] = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        $preset = CanvasButtonPreset::create($data);

        return redirect()->route('admin.canvas-assets.index', ['tab' => 'buttons'])
            ->with('success', "پریست «{$preset->title}» به پک افزوده شد.");
    }

    public function updateButtonPreset(Request $request, CanvasButtonPack $pack, CanvasButtonPreset $preset): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'slug' => 'required|string|max:100',
            'css_tokens' => 'required',
            'preview_html' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        $data['slug'] = Str::slug($data['slug']);

        if (is_string($data['css_tokens'])) {
            $decoded = json_decode($data['css_tokens'], true);
            $data['css_tokens'] = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        $preset->update($data);

        return redirect()->route('admin.canvas-assets.index', ['tab' => 'buttons'])
            ->with('success', "پریست «{$preset->title}» ویرایش شد.");
    }

    public function destroyButtonPreset(CanvasButtonPreset $preset): RedirectResponse
    {
        $title = $preset->title;
        $preset->delete();

        return redirect()->route('admin.canvas-assets.index', ['tab' => 'buttons'])
            ->with('success', "پریست «{$title}» حذف شد.");
    }

    public function publishButtonPackVersion(Request $request, CanvasButtonPack $pack): RedirectResponse
    {
        $data = $request->validate([
            'version' => 'required|string|max:20|regex:/^\d+\.\d+\.\d+$/',
            'changelog' => 'required|string|max:2000',
        ]);

        $newVer = $data['version'];
        $current = $pack->current_version;

        if (version_compare($newVer, $current, '<=')) {
            return redirect()->back()
                ->withErrors(['version' => "نسخه جدید ({$newVer}) باید بزرگتر از نسخه فعلی ({$current}) باشد."]);
        }

        CanvasAssetVersion::create([
            'asset_kind' => AssetRef::KIND_BUTTON_PACK,
            'asset_slug' => $pack->slug,
            'version' => $newVer,
            'changelog' => $data['changelog'],
            'published_by_admin_id' => Auth::id(),
            'published_at' => now(),
        ]);

        $pack->update(['current_version' => $newVer]);

        return redirect()->route('admin.canvas-assets.index', ['tab' => 'buttons'])
            ->with('success', "نسخه {$newVer} پک «{$pack->title}» با موفقیت منتشر شد.");
    }

    // ─────────────────────────────────────────────────────────────
    // ICON PACKS & ITEMS
    // ─────────────────────────────────────────────────────────────

    public function storeIconPack(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'slug' => 'nullable|string|max:100|unique:canvas_icon_packs,slug',
            'category_label' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'preview_image' => 'nullable|string|max:255',
            'access_type' => ['required', Rule::in(['free', 'paid'])],
            'price' => 'nullable|required_if:access_type,paid|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'current_version' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $data['slug'] = !empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['title']);
        $data['current_version'] = ($data['current_version'] ?? null) ?: '1.0.0';
        $data['is_active'] = $request->has('is_active');
        $data['price'] = $data['price'] ?? 0;

        $pack = CanvasIconPack::create($data);

        // Record initial version
        CanvasAssetVersion::firstOrCreate(
            [
                'asset_kind' => AssetRef::KIND_ICON_PACK,
                'asset_slug' => $pack->slug,
                'version' => $pack->current_version,
            ],
            [
                'changelog' => 'نسخه اولیه کتابخانه آیکن',
                'published_by_admin_id' => Auth::id(),
                'published_at' => now(),
            ]
        );

        return redirect()->route('admin.canvas-assets.index', ['tab' => 'icons'])
            ->with('success', "پک آیکن «{$pack->title}» با موفقیت ایجاد شد.");
    }

    public function updateIconPack(Request $request, CanvasIconPack $pack): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'slug' => ['required', 'string', 'max:100', Rule::unique('canvas_icon_packs', 'slug')->ignore($pack->id)],
            'category_label' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'preview_image' => 'nullable|string|max:255',
            'access_type' => ['required', Rule::in(['free', 'paid'])],
            'price' => 'nullable|required_if:access_type,paid|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $data['slug'] = Str::slug($data['slug']);
        $data['is_active'] = $request->has('is_active');
        $data['price'] = $data['price'] ?? 0;

        $pack->update($data);

        return redirect()->route('admin.canvas-assets.index', ['tab' => 'icons'])
            ->with('success', "پک آیکن «{$pack->title}» بروزرسانی شد.");
    }

    public function destroyIconPack(CanvasIconPack $pack): RedirectResponse
    {
        $title = $pack->title;
        $pack->delete();

        return redirect()->route('admin.canvas-assets.index', ['tab' => 'icons'])
            ->with('success', "پک آیکن «{$title}» حذف شد.");
    }

    public function storeIconItem(Request $request, CanvasIconPack $pack): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'slug' => 'nullable|string|max:100',
            'keywords' => 'nullable|string|max:255',
            'svg_content' => 'required|string',
            'view_box' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
        ]);

        $data['pack_id'] = $pack->id;
        $data['slug'] = !empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['title']);
        $data['view_box'] = $data['view_box'] ?: '0 0 24 24';

        // Sanitize SVG (strip malicious scripts / event handlers)
        $cleanSvg = $this->sanitizeSvg($data['svg_content']);
        if (empty($cleanSvg)) {
            return redirect()->back()
                ->withErrors(['svg_content' => 'محتوای SVG معتبر نیست یا شامل تگ‌های غیرمجاز است.']);
        }
        $data['svg_content'] = $cleanSvg;

        $item = CanvasIconItem::create($data);

        return redirect()->route('admin.canvas-assets.index', ['tab' => 'icons'])
            ->with('success', "آیکن «{$item->title}» به پک افزوده شد.");
    }

    public function destroyIconItem(CanvasIconItem $item): RedirectResponse
    {
        $title = $item->title;
        $item->delete();

        return redirect()->route('admin.canvas-assets.index', ['tab' => 'icons'])
            ->with('success', "آیکن «{$title}» حذف شد.");
    }

    public function publishIconPackVersion(Request $request, CanvasIconPack $pack): RedirectResponse
    {
        $data = $request->validate([
            'version' => 'required|string|max:20|regex:/^\d+\.\d+\.\d+$/',
            'changelog' => 'required|string|max:2000',
        ]);

        $newVer = $data['version'];
        $current = $pack->current_version;

        if (version_compare($newVer, $current, '<=')) {
            return redirect()->back()
                ->withErrors(['version' => "نسخه جدید ({$newVer}) باید بزرگتر از نسخه فعلی ({$current}) باشد."]);
        }

        CanvasAssetVersion::create([
            'asset_kind' => AssetRef::KIND_ICON_PACK,
            'asset_slug' => $pack->slug,
            'version' => $newVer,
            'changelog' => $data['changelog'],
            'published_by_admin_id' => Auth::id(),
            'published_at' => now(),
        ]);

        $pack->update(['current_version' => $newVer]);

        return redirect()->route('admin.canvas-assets.index', ['tab' => 'icons'])
            ->with('success', "نسخه {$newVer} پک «{$pack->title}» با موفقیت منتشر شد.");
    }

    // ─────────────────────────────────────────────────────────────
    // HEADER PRESETS
    // ─────────────────────────────────────────────────────────────

    public function storeHeaderPreset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'slug' => 'required|string|max:100|unique:canvas_header_presets,slug',
            'category_label' => 'nullable|string|max:100',
            'preview_image' => 'nullable|string|max:255',
            'access_type' => ['required', Rule::in(['free', 'paid'])],
            'price' => 'nullable|required_if:access_type,paid|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'component_alias' => 'required|string|max:100',
            'current_version' => 'nullable|string|max:20',
            'default_config' => 'nullable',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $data['slug'] = Str::slug($data['slug']);
        $data['current_version'] = ($data['current_version'] ?? null) ?: '1.0.0';
        $data['is_active'] = $request->has('is_active');
        $data['price'] = $data['price'] ?? 0;

        if (isset($data['default_config']) && is_string($data['default_config'])) {
            $decoded = json_decode($data['default_config'], true);
            $data['default_config'] = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        $preset = CanvasHeaderPreset::create($data);

        // Record initial version
        CanvasAssetVersion::firstOrCreate(
            [
                'asset_kind' => AssetRef::KIND_HEADER,
                'asset_slug' => $preset->slug,
                'version' => $preset->current_version,
            ],
            [
                'changelog' => 'نسخه اولیه قالب هدر',
                'published_by_admin_id' => Auth::id(),
                'published_at' => now(),
            ]
        );

        return redirect()->route('admin.canvas-assets.index', ['tab' => 'headers'])
            ->with('success', "قالب هدر «{$preset->title}» ایجاد شد.");
    }

    public function updateHeaderPreset(Request $request, CanvasHeaderPreset $preset): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'slug' => ['required', 'string', 'max:100', Rule::unique('canvas_header_presets', 'slug')->ignore($preset->id)],
            'category_label' => 'nullable|string|max:100',
            'preview_image' => 'nullable|string|max:255',
            'access_type' => ['required', Rule::in(['free', 'paid'])],
            'price' => 'nullable|required_if:access_type,paid|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'component_alias' => 'required|string|max:100',
            'default_config' => 'nullable',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $data['slug'] = Str::slug($data['slug']);
        $data['is_active'] = $request->has('is_active');
        $data['price'] = $data['price'] ?? 0;

        if (isset($data['default_config']) && is_string($data['default_config'])) {
            $decoded = json_decode($data['default_config'], true);
            $data['default_config'] = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        $preset->update($data);

        return redirect()->route('admin.canvas-assets.index', ['tab' => 'headers'])
            ->with('success', "قالب هدر «{$preset->title}» بروزرسانی شد.");
    }

    public function destroyHeaderPreset(CanvasHeaderPreset $preset): RedirectResponse
    {
        $title = $preset->title;
        $preset->delete();

        return redirect()->route('admin.canvas-assets.index', ['tab' => 'headers'])
            ->with('success', "قالب هدر «{$title}» حذف شد.");
    }

    public function publishHeaderPresetVersion(Request $request, CanvasHeaderPreset $preset): RedirectResponse
    {
        $data = $request->validate([
            'version' => 'required|string|max:20|regex:/^\d+\.\d+\.\d+$/',
            'changelog' => 'required|string|max:2000',
        ]);

        $newVer = $data['version'];
        $current = $preset->current_version;

        if (version_compare($newVer, $current, '<=')) {
            return redirect()->back()
                ->withErrors(['version' => "نسخه جدید ({$newVer}) باید بزرگتر از نسخه فعلی ({$current}) باشد."]);
        }

        CanvasAssetVersion::create([
            'asset_kind' => AssetRef::KIND_HEADER,
            'asset_slug' => $preset->slug,
            'version' => $newVer,
            'changelog' => $data['changelog'],
            'published_by_admin_id' => Auth::id(),
            'published_at' => now(),
        ]);

        $preset->update(['current_version' => $newVer]);

        return redirect()->route('admin.canvas-assets.index', ['tab' => 'headers'])
            ->with('success', "نسخه {$newVer} هدر «{$preset->title}» منتشر شد.");
    }

    // ─────────────────────────────────────────────────────────────
    // MOTION GRAPHICS COMMERCIAL & VERSIONS
    // ─────────────────────────────────────────────────────────────

    public function updateMotionCommercial(Request $request, int $id): RedirectResponse
    {
        $motion = MotionGraphic::findOrFail($id);

        $data = $request->validate([
            'access_type' => ['required', Rule::in(['free', 'paid'])],
            'price' => 'nullable|required_if:access_type,paid|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'current_version' => 'nullable|string|max:20',
        ]);

        $data['current_version'] = ($data['current_version'] ?? null) ?: ($motion->current_version ?: '1.0.0');
        $data['price'] = $data['price'] ?? 0;

        $motion->update($data);

        return redirect()->route('admin.canvas-assets.index', ['tab' => 'motions'])
            ->with('success', "تنظیمات تجاری موشن «{$motion->name}» ذخیره شد.");
    }

    public function publishMotionVersion(Request $request, int $id): RedirectResponse
    {
        $motion = MotionGraphic::findOrFail($id);

        $data = $request->validate([
            'version' => 'required|string|max:20|regex:/^\d+\.\d+\.\d+$/',
            'changelog' => 'required|string|max:2000',
        ]);

        $newVer = $data['version'];
        $current = $motion->current_version ?: '1.0.0';

        if (version_compare($newVer, $current, '<=')) {
            return redirect()->back()
                ->withErrors(['version' => "نسخه جدید ({$newVer}) باید بزرگتر از نسخه فعلی ({$current}) باشد."]);
        }

        CanvasAssetVersion::create([
            'asset_kind' => AssetRef::KIND_MOTION,
            'asset_slug' => $motion->slug,
            'version' => $newVer,
            'changelog' => $data['changelog'],
            'published_by_admin_id' => Auth::id(),
            'published_at' => now(),
        ]);

        $motion->update(['current_version' => $newVer]);

        return redirect()->route('admin.canvas-assets.index', ['tab' => 'motions'])
            ->with('success', "نسخه {$newVer} موشن «{$motion->name}» منتشر شد.");
    }

    // ─────────────────────────────────────────────────────────────
    // GRANT & REVOKE TO SITES (SINGLE & BULK)
    // ─────────────────────────────────────────────────────────────

    public function grantAsset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'asset_kind' => ['required', Rule::in(AssetRef::KINDS)],
            'asset_key' => 'required|string|max:100',
            'site_ids' => 'required|array|min:1',
            'site_ids.*' => 'integer|exists:sites,id',
            'version' => 'nullable|string|max:20',
            'reference' => 'nullable|string|max:191',
        ]);

        $siteIds = array_map('intval', $data['site_ids']);
        $count = $this->provisioningService->grantToMultipleSites(
            $siteIds,
            $data['asset_kind'],
            $data['asset_key'],
            $data['version'] ?? null,
            SiteAssetGrant::SOURCE_ADMIN_GRANT,
            $data['reference'] ?? null,
            Auth::id()
        );

        return redirect()->back()
            ->with('success', "دسترسی دارایی «{$data['asset_key']}» برای {$count} سایت با موفقیت فعال شد.");
    }

    public function revokeAsset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'asset_kind' => ['required', Rule::in(AssetRef::KINDS)],
            'asset_key' => 'required|string|max:100',
            'site_ids' => 'required|array|min:1',
            'site_ids.*' => 'integer|exists:sites,id',
        ]);

        $siteIds = array_map('intval', $data['site_ids']);
        $count = $this->provisioningService->revokeFromMultipleSites(
            $siteIds,
            $data['asset_kind'],
            $data['asset_key'],
            Auth::id()
        );

        return redirect()->back()
            ->with('success', "دسترسی دارایی «{$data['asset_key']}» از {$count} سایت لغو شد.");
    }

    // ─────────────────────────────────────────────────────────────
    // BULK JSON IMPORT
    // ─────────────────────────────────────────────────────────────

    public function importJson(Request $request, \App\Services\AssetProvisioning\CanvasAssetImportService $importer): RedirectResponse
    {
        $request->validate([
            'json_file' => 'nullable|file|max:10240',
            'json_text' => 'nullable|string',
        ]);

        $raw = null;
        if ($request->hasFile('json_file')) {
            $raw = file_get_contents($request->file('json_file')->getRealPath());
        } elseif (!empty($request->input('json_text'))) {
            $raw = $request->input('json_text');
        }

        if (empty($raw)) {
            return redirect()->back()->with('error', 'لطفاً فایل JSON را انتخاب کرده یا متن JSON را وارد کنید.');
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return redirect()->back()->with('error', 'فرمت فایل یا متن JSON نامعتبر است.');
        }

        try {
            $summary = $importer->import($data);
            $msg = sprintf(
                'ورود دارایی‌ها با موفقیت انجام شد: %d پک دکمه (%d پریست)، %d پک آیکن (%d آیکن)، %d قالب هدر، %d موشن گرافیک (%d نسخه ثبت شد).',
                $summary['button_packs'],
                $summary['button_presets'],
                $summary['icon_packs'],
                $summary['icon_items'],
                $summary['header_presets'],
                $summary['motion_graphics'],
                $summary['versions']
            );
            return redirect()->back()->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'خطا در ورود اطلاعات: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // HELPER: SVG SANITIZATION
    // ─────────────────────────────────────────────────────────────

    protected function sanitizeSvg(string $svg): ?string
    {
        $svg = trim($svg);
        if (!str_contains($svg, '<svg')) {
            return null;
        }

        // Remove <script> tags and contents
        $svg = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $svg);

        // Remove inline javascript event attributes (onload, onclick, onerror, etc.)
        $svg = preg_replace('#\son[a-zA-Z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $svg);

        // Remove dangerous href/xlink with javascript:
        $svg = preg_replace('#(href|xlink:href)\s*=\s*("|\')\s*javascript:[^"\']*("|\')#i', '', $svg);

        return $svg;
    }
}
