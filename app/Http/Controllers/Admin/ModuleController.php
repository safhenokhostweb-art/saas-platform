<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Product;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * مدیریت ماژول‌ها (کاتالوگ سراسری، قیمت، مارکت‌پلیس، آمار).
 */
class ModuleController extends Controller
{
    private const SEMVER_REGEX = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z\-\.]+)?(?:\+[0-9A-Za-z\-\.]+)?$/';

    public function releasesIndex()
    {
        $releases = ModuleRelease::query()
            ->with('module:id,slug,title')
            ->orderByDesc('distributed_at')
            ->orderByDesc('id')
            ->paginate(50);

        return view('admin.modules.releases-index', compact('releases'));
    }

    public function index()
    {
        $modules = Module::orderBy('slug')->get();
        $latestReleases = ModuleRelease::query()
            ->where('is_active', true)
            ->orderByDesc('distributed_at')
            ->orderByDesc('id')
            ->get()
            ->unique('module_id')
            ->keyBy('module_id');
        $sitesCount = Site::count();
        $moduleSiteCounts = DB::table('site_modules')
            ->where('is_active', true)
            ->selectRaw('module_id, count(*) as cnt')
            ->groupBy('module_id')
            ->pluck('cnt', 'module_id');

        $licensedCounts = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('module_licenses')) {
            $licensedCounts = DB::table('module_licenses')
                ->where('status', 'active')
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->whereNull('revoked_at')
                ->selectRaw('module_id, count(*) as cnt')
                ->groupBy('module_id')
                ->pluck('cnt', 'module_id')
                ->all();
        }

        $productByModuleId = Product::where('type', Product::TYPE_MODULE_ADDON)
            ->whereNotNull('module_id')
            ->get()
            ->keyBy('module_id');

        return view('admin.modules.index', compact('modules', 'latestReleases', 'sitesCount', 'moduleSiteCounts', 'licensedCounts', 'productByModuleId'));
    }

    public function edit(Module $module)
    {
        $productInStore = \App\Models\Product::where('type', \App\Models\Product::TYPE_MODULE_ADDON)
            ->where('module_id', $module->id)
            ->first();
        return view('admin.modules.edit', compact('module', 'productInStore'));
    }

    /**
     * مدیریت نسخه‌ها و changelog یک ماژول.
     */
    public function releases(Module $module)
    {
        $releases = $module->releases()
            ->orderByDesc('distributed_at')
            ->orderByDesc('id')
            ->paginate(30);

        $latestVersion = (string) ($module->version ?: '1.0.0');
        $suggestedVersions = [
            'patch' => $this->bumpSemver($latestVersion, ModuleRelease::TYPE_PATCH),
            'minor' => $this->bumpSemver($latestVersion, ModuleRelease::TYPE_MINOR),
            'major' => $this->bumpSemver($latestVersion, ModuleRelease::TYPE_MAJOR),
        ];

        return view('admin.modules.releases', compact('module', 'releases', 'suggestedVersions'));
    }

    public function storeRelease(Request $request, Module $module)
    {
        $data = $request->validate([
            'version' => ['required', 'string', 'max:32', 'regex:' . self::SEMVER_REGEX],
            'release_type' => 'required|in:patch,minor,major',
            'changelog' => 'required|string|min:20',
            'is_active' => 'sometimes|boolean',
        ]);

        $latestVersion = ModuleRelease::getLatestVersionForModule($module);
        if (version_compare($data['version'], $latestVersion, '<=')) {
            return back()
                ->withInput()
                ->withErrors([
                    'version' => 'نسخهٔ انتشار باید بزرگ‌تر از آخرین نسخه باشد. آخرین نسخه: ' . $latestVersion,
                ]);
        }

        if (! $this->isReleaseTypeAligned($latestVersion, $data['version'], $data['release_type'])) {
            return back()
                ->withInput()
                ->withErrors([
                    'release_type' => 'نوع انتشار با نسخهٔ انتخابی هم‌خوانی ندارد. برای این نسخه از نوع صحیح (patch/minor/major) استفاده کنید.',
                ]);
        }

        $release = ModuleRelease::updateOrCreate(
            ['module_id' => $module->id, 'version' => $data['version']],
            [
                'release_type' => $data['release_type'],
                'changelog' => trim($data['changelog']),
                'distributed_at' => now(),
                'is_active' => $request->boolean('is_active', true),
            ]
        );

        if (version_compare($data['version'], (string) ($module->version ?? '0'), '>')) {
            $module->update(['version' => $data['version']]);
        }

        return redirect()
            ->route('admin.modules.releases', $module)
            ->with('success', 'نسخهٔ ماژول منتشر شد: ' . $release->version);
    }

    private function bumpSemver(string $baseVersion, string $releaseType): string
    {
        $parts = array_map('intval', explode('.', explode('-', explode('+', $baseVersion)[0])[0]));
        $major = $parts[0] ?? 1;
        $minor = $parts[1] ?? 0;
        $patch = $parts[2] ?? 0;

        return match ($releaseType) {
            ModuleRelease::TYPE_MAJOR => ($major + 1) . '.0.0',
            ModuleRelease::TYPE_MINOR => $major . '.' . ($minor + 1) . '.0',
            default => $major . '.' . $minor . '.' . ($patch + 1),
        };
    }

    private function isReleaseTypeAligned(string $fromVersion, string $toVersion, string $releaseType): bool
    {
        $normalize = static function (string $version): array {
            $clean = explode('-', explode('+', $version)[0])[0];
            $parts = array_map('intval', explode('.', $clean));
            return [
                $parts[0] ?? 0,
                $parts[1] ?? 0,
                $parts[2] ?? 0,
            ];
        };

        [$fromMajor, $fromMinor, $fromPatch] = $normalize($fromVersion);
        [$toMajor, $toMinor, $toPatch] = $normalize($toVersion);

        return match ($releaseType) {
            ModuleRelease::TYPE_MAJOR => $toMajor > $fromMajor,
            ModuleRelease::TYPE_MINOR => $toMajor === $fromMajor && $toMinor > $fromMinor,
            ModuleRelease::TYPE_PATCH => $toMajor === $fromMajor && $toMinor === $fromMinor && $toPatch > $fromPatch,
            default => false,
        };
    }

    public function update(Request $request, Module $module)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'price_irr' => 'nullable|integer|min:0',
            'price_promo_irr' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|max:8',
            'license_type_default' => 'required|in:one_time,subscription',
            'marketplace_visible' => 'boolean',
            'free_assignable' => 'boolean',
            'license_required' => 'boolean',
            'category' => 'nullable|string|max:64',
            'screenshots' => 'nullable|string', // JSON or comma URLs
        ]);

        $request->validate([
            'module_type' => 'required|in:internal,license,paid',
        ]);

        $oldType = $module->type;

        $data = $request->only([
            'title', 'description', 'short_description',
            'price_irr', 'price_promo_irr', 'currency', 'license_type_default',
            'marketplace_visible', 'free_assignable', 'license_required', 'category',
        ]);
        $data['marketplace_visible'] = $request->boolean('marketplace_visible', true);
        $data['free_assignable'] = $request->boolean('free_assignable', false);
        $data['license_required'] = $request->boolean('license_required', false);
        $data['price_irr'] = $request->filled('price_irr') ? (int) $request->price_irr : null;
        $data['price_promo_irr'] = $request->filled('price_promo_irr') ? (int) $request->price_promo_irr : null;

        if ($request->filled('screenshots')) {
            $urls = array_filter(array_map('trim', explode("\n", $request->screenshots)));
            $data['screenshots'] = $urls;
        } else {
            $data['screenshots'] = null;
        }

        // نگاشت نوع نمایشی ماژول (داخلی / مبتنی بر لایسنس / پولی) به مقدار ذخیره‌شده در دیتابیس.
        $uiType = $request->input('module_type');
        $newType = match ($uiType) {
            'internal' => Module::TYPE_INTERNAL,
            'paid' => Module::TYPE_PAID,
            'license' => Module::TYPE_CORE, // ماژول‌های «لایسنس‌محور» در دیتابیس به‌صورت core ذخیره می‌شوند.
            default => $module->type,
        };
        $data['type'] = $newType;

        DB::transaction(function () use ($module, $data, $oldType, $newType) {
            $module->update($data);

            // منطق امن تغییر نوع:
            // - internal → سایر حالات: هیچ سایتی خودکار ماژول را دریافت نمی‌کند (site_modules دست‌نخورده می‌ماند).
            // - هر حالت → internal: دسترسی همهٔ سایت‌ها به‌صورت نرم غیرفعال می‌شود (بدون حذف داده).
            if ($newType === Module::TYPE_INTERNAL && $oldType !== Module::TYPE_INTERNAL) {
                if (\Illuminate\Support\Facades\Schema::hasTable('site_modules')) {
                    \Illuminate\Support\Facades\DB::table('site_modules')
                        ->where('module_id', $module->id)
                        ->update(['is_active' => false]);
                }
            }

            // جابه‌جایی بین core (license) و paid:
            // - هیچ تغییری روی site_modules انجام نمی‌دهیم تا سایت‌هایی که هم‌اکنون دسترسی دارند، آن را از دست ندهند.
            // - قوانین جدید توزیع از این پس از روی تنظیمات قیمت/لایسنس اعمال می‌شود.
        });

        return redirect()->route('admin.modules.index')->with('success', 'ماژول به‌روزرسانی شد.');
    }

    /**
     * بروزرسانی گروهی ماژول‌ها (نوع ماژول و فلگ‌های توزیع).
     */
    public function bulkUpdate(Request $request)
    {
        $data = $request->validate([
            'module_ids' => 'required|array',
            'module_ids.*' => 'integer|exists:modules,id',
            'bulk_action' => 'required|string|in:' . implode(',', [
                'set_type_internal',
                'set_type_license',
                'set_type_paid',
                'marketplace_on',
                'marketplace_off',
                'free_assignable_on',
                'free_assignable_off',
                'license_required_on',
                'license_required_off',
            ]),
        ]);

        $ids = $data['module_ids'];
        $action = $data['bulk_action'];

        $modules = Module::whereIn('id', $ids)->get();

        foreach ($modules as $module) {
            $oldType = $module->type;
            $updates = [];

            switch ($action) {
                case 'set_type_internal':
                    $newType = Module::TYPE_INTERNAL;
                    $updates['type'] = $newType;
                    break;
                case 'set_type_license':
                    $newType = Module::TYPE_CORE;
                    $updates['type'] = $newType;
                    break;
                case 'set_type_paid':
                    $newType = Module::TYPE_PAID;
                    $updates['type'] = $newType;
                    break;
                case 'marketplace_on':
                    $newType = $oldType;
                    $updates['marketplace_visible'] = true;
                    break;
                case 'marketplace_off':
                    $newType = $oldType;
                    $updates['marketplace_visible'] = false;
                    break;
                case 'free_assignable_on':
                    $newType = $oldType;
                    $updates['free_assignable'] = true;
                    break;
                case 'free_assignable_off':
                    $newType = $oldType;
                    $updates['free_assignable'] = false;
                    break;
                case 'license_required_on':
                    $newType = $oldType;
                    $updates['license_required'] = true;
                    break;
                case 'license_required_off':
                    $newType = $oldType;
                    $updates['license_required'] = false;
                    break;
                default:
                    $newType = $oldType;
                    break;
            }

            if (empty($updates)) {
                continue;
            }

            DB::transaction(function () use ($module, $updates, $oldType, $newType) {
                $module->update($updates);

                // همان منطق ایمن تغییر نوع برای حالت گروهی هم اعمال می‌شود.
                if (($updates['type'] ?? null) === Module::TYPE_INTERNAL && $oldType !== Module::TYPE_INTERNAL) {
                    if (\Illuminate\Support\Facades\Schema::hasTable('site_modules')) {
                        \Illuminate\Support\Facades\DB::table('site_modules')
                            ->where('module_id', $module->id)
                            ->update(['is_active' => false]);
                    }
                }
            });
        }

        return redirect()->route('admin.modules.index')->with('success', 'تغییرات گروهی ماژول‌ها اعمال شد.');
    }
}
