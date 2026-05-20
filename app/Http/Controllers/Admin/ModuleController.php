<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Product;
use App\Models\Site;
use App\Services\ActivityLogger;
use App\Services\EntitlementLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * مدیریت ماژول‌ها (کاتالوگ سراسری، قیمت، مارکت‌پلیس، آمار).
 */
class ModuleController extends Controller
{
    public function index()
    {
        $modules = Module::orderBy('slug')->get();
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

        return view('admin.modules.index', compact('modules', 'sitesCount', 'moduleSiteCounts', 'licensedCounts', 'productByModuleId'));
    }

    public function edit(Module $module)
    {
        $productInStore = \App\Models\Product::where('type', \App\Models\Product::TYPE_MODULE_ADDON)
            ->where('module_id', $module->id)
            ->first();
        return view('admin.modules.edit', compact('module', 'productInStore'));
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

        // Phase 5 / Wave 3 / P1-3: snapshot فیلدهای watch قبل از تغییر برای audit log.
        $watchFields = [
            'type', 'title', 'short_description', 'price_irr', 'price_promo_irr',
            'currency', 'license_type_default', 'marketplace_visible',
            'free_assignable', 'license_required', 'category',
        ];
        $oldSnapshot = collect($watchFields)->mapWithKeys(fn ($f) => [$f => $module->getOriginal($f) ?? $module->{$f}])->all();

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

        // Phase 5 / Wave 3 / P1-3: side effect tracking (تعداد site_modules که در صورت سوئیچ به internal غیرفعال خواهند شد).
        // Phase 5 / Wave 5.2 / P0-1 (revised): per-site granular confirmation. هیچ revoke خودکار اتفاق نمی‌افتد:
        // اگر سایت فعالی روی این ماژول وجود دارد، اولین POST به‌جای انجام عملیات، یک صفحهٔ تأیید
        // می‌سازد که در آن ادمین برای هر سایت می‌تواند «نگه‌دار فعال» را انتخاب/لغو انتخاب کند.
        // فقط در دومین POST (با __confirm_step=apply) عملیات revoke اعمال می‌شود — و فقط برای
        // سایت‌هایی که به‌صراحت کاربر آن‌ها را UNCHECKED گذاشته. سایت‌های دارای خرید یا لایسنس
        // فعال به‌طور پیش‌فرض pre-checked می‌شوند تا entitlement مشتری به‌طور اشتباه از دست نرود
        // (global-rules §۴ Immutable Customer Entitlement Protection + §۵ Manual Revocation Only).
        $affectedSiteIds = [];
        $isDestructiveTypeSwitch = ($newType === Module::TYPE_INTERNAL && $oldType !== Module::TYPE_INTERNAL);
        if ($isDestructiveTypeSwitch && \Illuminate\Support\Facades\Schema::hasTable('site_modules')) {
            $affectedSiteIds = \Illuminate\Support\Facades\DB::table('site_modules')
                ->where('module_id', $module->id)
                ->where('is_active', true)
                ->pluck('site_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        $confirmStep = (string) $request->input('__confirm_step', '');
        $isConfirmApply = ($confirmStep === 'apply');

        // مرحلهٔ ۱: اگر تغییر مخرب است و سایت فعالی وجود دارد و هنوز apply نشده ⇒ نمایش صفحهٔ تأیید.
        if ($isDestructiveTypeSwitch && !empty($affectedSiteIds) && !$isConfirmApply) {
            return $this->renderConfirmDestructive(
                module: $module,
                affectedSiteIds: $affectedSiteIds,
                pendingFormData: $request->except(['_token', '_method', '__confirm_step']),
                oldRevokeReason: (string) $request->input('revoke_reason', ''),
            );
        }

        // مرحلهٔ ۲: apply — ولیداسیون فیلدهای destructive و سپس انجام revoke فقط برای سایت‌های unchecked.
        $keepActiveSiteIds = [];
        $revokeReason = '';
        if ($isDestructiveTypeSwitch && !empty($affectedSiteIds) && $isConfirmApply) {
            $request->validate([
                'revoke_reason' => 'required|string|min:5|max:1000',
                'confirmed_destructive' => 'required|accepted',
                'keep_active_site_ids' => 'nullable|array',
                'keep_active_site_ids.*' => 'integer',
            ], [
                'revoke_reason.required' => 'برای تبدیل نوع به «داخلی» با وجود سایت‌های فعال، ذکر دلیل صریح اجباری است.',
                'revoke_reason.min' => 'دلیل revoke باید حداقل ۵ نویسه باشد.',
                'confirmed_destructive.required' => 'برای ادامه باید چک‌باکس تأیید عملیات مخرب را علامت بزنید.',
                'confirmed_destructive.accepted' => 'برای ادامه باید چک‌باکس تأیید عملیات مخرب را علامت بزنید.',
            ]);
            $revokeReason = (string) $request->input('revoke_reason');
            // ادمین فقط سایت‌هایی را می‌تواند keep کند که در فهرست affected واقعاً هستند.
            $keepActiveSiteIds = array_values(array_intersect(
                array_map('intval', (array) $request->input('keep_active_site_ids', [])),
                $affectedSiteIds
            ));
        }

        // سایت‌هایی که واقعاً revoke می‌شوند = affected − keep.
        $sitesToRevoke = array_values(array_diff($affectedSiteIds, $keepActiveSiteIds));
        $adminId = Auth::id();

        DB::transaction(function () use ($module, $data, $oldSnapshot, $watchFields, $isDestructiveTypeSwitch, $affectedSiteIds, $keepActiveSiteIds, $sitesToRevoke, $revokeReason, $adminId) {
            $module->update($data);

            // تغییر نوع به internal: فقط site_modules سایت‌هایی که ادمین صریحاً «نگه‌دار فعال» را
            // برای آن‌ها UNCHECK کرده، فلیپ به is_active=0 می‌شوند. سایت‌های kept دست‌نخورده باقی می‌مانند
            // (global-rules §۴: هیچ revoke خودکار). هیچ ردیفی از site_modules حذف نمی‌شود.
            if ($isDestructiveTypeSwitch && !empty($sitesToRevoke)
                && \Illuminate\Support\Facades\Schema::hasTable('site_modules')) {
                \Illuminate\Support\Facades\DB::table('site_modules')
                    ->where('module_id', $module->id)
                    ->whereIn('site_id', $sitesToRevoke)
                    ->update(['is_active' => false]);

                // ledger revoke صریح برای هر site×module pair affected (per-site granularity).
                $ledger = app(EntitlementLedger::class);
                $reason = 'Module switched to internal via admin update. Reason: ' . $revokeReason;
                foreach ($sitesToRevoke as $siteId) {
                    try {
                        $ledger->revokeBySiteModule((int) $siteId, (int) $module->id, $adminId, $reason);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('moduleController.ledger.revoke_failed', [
                            'site_id' => $siteId,
                            'module_id' => $module->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // جابه‌جایی بین core (license) و paid:
            // - هیچ تغییری روی site_modules انجام نمی‌دهیم تا سایت‌هایی که هم‌اکنون دسترسی دارند، آن را از دست ندهند.

            // Phase 5 / Wave 3 / P1-3 + Wave 5.2 / P0-1: audit log enriched.
            $changes = [];
            foreach ($watchFields as $f) {
                $newVal = $module->{$f};
                $oldVal = $oldSnapshot[$f] ?? null;
                if ($oldVal != $newVal) {
                    $changes[$f] = ['old' => $oldVal, 'new' => $newVal];
                }
            }
            if (!empty($changes)) {
                $props = ['changes' => $changes];
                if ($isDestructiveTypeSwitch) {
                    $props['side_effects'] = [
                        'site_modules_disabled_count' => count($sitesToRevoke),
                        'reason' => 'type_switched_to_internal',
                        // Wave 5.2 / P0-1: per-site decisions — هم revoked هم kept لاگ می‌شوند تا audit کامل باشد.
                        'revoke_reason' => $revokeReason !== '' ? $revokeReason : null,
                        'affected_site_ids' => $affectedSiteIds,
                        'revoked_site_ids' => $sitesToRevoke,
                        'kept_site_ids' => $keepActiveSiteIds,
                    ];
                }
                ActivityLogger::log(
                    'module.updated',
                    'ویرایش ماژول: ' . ($module->title ?: $module->slug),
                    $module,
                    $props
                );
            }
        });

        $successMsg = 'ماژول به‌روزرسانی شد.';
        if ($isDestructiveTypeSwitch) {
            $successMsg .= ' (' . count($sitesToRevoke) . ' سایت revoke شد، ' . count($keepActiveSiteIds) . ' سایت با درخواست شما فعال نگه داشته شد.)';
        }
        return redirect()->route('admin.modules.index')->with('success', $successMsg);
    }

    /**
     * Wave 5.2 / P0-1: نمایش صفحهٔ تأیید per-site قبل از revoke هیچ سایتی.
     *
     * @param  array<int, int>  $affectedSiteIds
     * @param  array<string, mixed>  $pendingFormData
     */
    protected function renderConfirmDestructive(
        Module $module,
        array $affectedSiteIds,
        array $pendingFormData,
        string $oldRevokeReason = ''
    ) {
        $sites = Site::with('customer')
            ->whereIn('id', $affectedSiteIds)
            ->orderBy('id')
            ->get();

        // سایت‌هایی که حداقل یک «اتکای تجاری» دارند (purchase موفق یا module_license فعال) ⇒ pre-checked Keep Active.
        // این list معیار default تصمیم است؛ ادمین می‌تواند override کند.
        $commerciallyAttachedSiteIds = $this->sitesWithCommercialAttachmentForModule((int) $module->id, $affectedSiteIds);

        return view('admin.modules.confirm-destructive', [
            'module' => $module,
            'sites' => $sites,
            'affectedSiteIds' => $affectedSiteIds,
            'commerciallyAttachedSiteIds' => $commerciallyAttachedSiteIds,
            'pendingFormData' => $pendingFormData,
            'oldRevokeReason' => $oldRevokeReason,
        ]);
    }

    /**
     * Wave 5.2 / P0-1: سایت‌هایی که برای این ماژول یک «اتکای تجاری» فعال دارند:
     *   • هر ردیف site_module_purchases (هر خرید موفق ⇒ commercial attachment)
     *   • یا هر module_licenses فعال (status=active، revoked_at=null)
     *
     * این سایت‌ها در صفحهٔ تأیید به‌طور پیش‌فرض pre-checked Keep Active می‌شوند تا entitlement
     * مشتری به‌اشتباه از دست نرود (global-rules §۴).
     *
     * @param  array<int, int>  $candidateSiteIds
     * @return array<int, int>
     */
    protected function sitesWithCommercialAttachmentForModule(int $moduleId, array $candidateSiteIds): array
    {
        if (empty($candidateSiteIds)) {
            return [];
        }
        $result = [];

        if (\Illuminate\Support\Facades\Schema::hasTable('site_module_purchases')) {
            $purchased = \Illuminate\Support\Facades\DB::table('site_module_purchases')
                ->where('module_id', $moduleId)
                ->whereIn('site_id', $candidateSiteIds)
                ->pluck('site_id')
                ->map(fn ($v) => (int) $v)
                ->all();
            $result = array_merge($result, $purchased);
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('module_licenses')) {
            $licensed = \Illuminate\Support\Facades\DB::table('module_licenses')
                ->where('module_id', $moduleId)
                ->whereIn('site_id', $candidateSiteIds)
                ->where('status', 'active')
                ->whereNull('revoked_at')
                ->pluck('site_id')
                ->map(fn ($v) => (int) $v)
                ->all();
            $result = array_merge($result, $licensed);
        }

        return array_values(array_unique($result));
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

        // Phase 5 / Wave 5.2 / P0-1 (revised): per-(site,module) granular confirmation.
        // برای bulk، صفحهٔ تأیید لیست تمام جفت‌های (site,module) که به‌خاطر این عملیات تحت‌تأثیر
        // قرار می‌گیرند را نشان می‌دهد. ادمین می‌تواند هر جفت را به‌صورت مستقل «نگه‌دار فعال» کند.
        // فقط جفت‌های UNCHECKED revoke می‌شوند (global-rules §۴).
        $isBulkDestructive = ($action === 'set_type_internal');

        // محاسبهٔ affected pairs برای ماژول‌هایی که در حال حاضر internal نیستند.
        $affectedPairs = []; // [['site_id' => ..., 'module_id' => ...], ...]
        $affectedPairKey = fn (int $siteId, int $moduleId): string => $siteId . ':' . $moduleId;
        if ($isBulkDestructive && \Illuminate\Support\Facades\Schema::hasTable('site_modules')) {
            $eligibleModuleIds = $modules->where('type', '!=', Module::TYPE_INTERNAL)->pluck('id')->all();
            if (!empty($eligibleModuleIds)) {
                $rows = \Illuminate\Support\Facades\DB::table('site_modules')
                    ->whereIn('module_id', $eligibleModuleIds)
                    ->where('is_active', true)
                    ->select('site_id', 'module_id')
                    ->get();
                foreach ($rows as $r) {
                    $affectedPairs[] = ['site_id' => (int) $r->site_id, 'module_id' => (int) $r->module_id];
                }
            }
        }

        $confirmStep = (string) $request->input('__confirm_step', '');
        $isConfirmApply = ($confirmStep === 'apply');

        // مرحلهٔ ۱: اگر تخریب‌گر است و pair affected وجود دارد و هنوز apply نشده ⇒ نمایش صفحهٔ تأیید.
        if ($isBulkDestructive && !empty($affectedPairs) && !$isConfirmApply) {
            return $this->renderConfirmBulkDestructive(
                modules: $modules,
                affectedPairs: $affectedPairs,
                action: $action,
                moduleIds: $ids,
                oldRevokeReason: (string) $request->input('revoke_reason', ''),
            );
        }

        // مرحلهٔ ۲: apply — ولیداسیون فیلدهای destructive سپس revoke فقط برای جفت‌های UNCHECKED.
        $bulkRevokeReason = '';
        $keepActivePairKeys = [];
        if ($isBulkDestructive && !empty($affectedPairs) && $isConfirmApply) {
            $request->validate([
                'revoke_reason' => 'required|string|min:5|max:1000',
                'confirmed_destructive' => 'required|accepted',
                'keep_active_pair_keys' => 'nullable|array',
                'keep_active_pair_keys.*' => 'string',
            ], [
                'revoke_reason.required' => 'برای تبدیل گروهی نوع به «داخلی» با وجود سایت‌های فعال، ذکر دلیل صریح اجباری است.',
                'revoke_reason.min' => 'دلیل revoke باید حداقل ۵ نویسه باشد.',
                'confirmed_destructive.required' => 'برای ادامه باید چک‌باکس تأیید عملیات مخرب را علامت بزنید.',
                'confirmed_destructive.accepted' => 'برای ادامه باید چک‌باکس تأیید عملیات مخرب را علامت بزنید.',
            ]);
            $bulkRevokeReason = (string) $request->input('revoke_reason');
            $submittedKeys = array_map('strval', (array) $request->input('keep_active_pair_keys', []));
            $allValidKeys = array_map(fn ($p) => $affectedPairKey($p['site_id'], $p['module_id']), $affectedPairs);
            $keepActivePairKeys = array_values(array_intersect($submittedKeys, $allValidKeys));
        }
        $bulkAdminId = Auth::id();

        // Phase 5 / Wave 3 / P1-3: aggregate counters برای audit log عملیات گروهی.
        $bulkChanged = 0;
        $bulkSideEffectModules = 0;
        $bulkSideEffectSiteModules = 0;
        $bulkKeptSiteModules = 0;
        // برای lookup سریع keep‌ها در حین حلقه:
        $keepLookup = array_flip($keepActivePairKeys);

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

            // P1-3 + P0-1 (per-site granular):
            // برای bulk destructive، فقط جفت‌های (site,module) که در keep لیست نیستند را revoke می‌کنیم.
            $perModuleSitesToRevoke = [];
            $perModuleSitesKept = [];
            $isPerModuleDestructive = (($updates['type'] ?? null) === Module::TYPE_INTERNAL && $oldType !== Module::TYPE_INTERNAL);
            if ($isPerModuleDestructive && \Illuminate\Support\Facades\Schema::hasTable('site_modules')) {
                $perModuleAffectedSiteIds = \Illuminate\Support\Facades\DB::table('site_modules')
                    ->where('module_id', $module->id)
                    ->where('is_active', true)
                    ->pluck('site_id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
                foreach ($perModuleAffectedSiteIds as $sid) {
                    $key = $sid . ':' . $module->id;
                    if (isset($keepLookup[$key])) {
                        $perModuleSitesKept[] = $sid;
                    } else {
                        $perModuleSitesToRevoke[] = $sid;
                    }
                }
            }

            DB::transaction(function () use ($module, $updates, $isPerModuleDestructive, $perModuleSitesToRevoke, $bulkRevokeReason, $bulkAdminId) {
                $module->update($updates);

                // Wave 5.2 / P0-1: فقط site_modules که در keep نیستند فلیپ می‌شوند.
                if ($isPerModuleDestructive && !empty($perModuleSitesToRevoke)
                    && \Illuminate\Support\Facades\Schema::hasTable('site_modules')) {
                    \Illuminate\Support\Facades\DB::table('site_modules')
                        ->where('module_id', $module->id)
                        ->whereIn('site_id', $perModuleSitesToRevoke)
                        ->update(['is_active' => false]);

                    $ledger = app(EntitlementLedger::class);
                    $reason = 'Bulk: module switched to internal via admin bulk action. Reason: ' . $bulkRevokeReason;
                    foreach ($perModuleSitesToRevoke as $siteId) {
                        try {
                            $ledger->revokeBySiteModule((int) $siteId, (int) $module->id, $bulkAdminId, $reason);
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning('moduleController.bulk.ledger.revoke_failed', [
                                'site_id' => $siteId,
                                'module_id' => $module->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            });

            $bulkChanged++;
            if (!empty($perModuleSitesToRevoke) || !empty($perModuleSitesKept)) {
                $bulkSideEffectModules++;
                $bulkSideEffectSiteModules += count($perModuleSitesToRevoke);
                $bulkKeptSiteModules += count($perModuleSitesKept);
            }
        }

        // Phase 5 / Wave 3 / P1-3 + Wave 5.2 / P0-1: ثبت یک audit log کلی برای کل عملیات گروهی.
        if ($bulkChanged > 0) {
            $props = [
                'bulk_action' => $action,
                'module_ids' => $ids,
                'count' => $bulkChanged,
            ];
            if ($isBulkDestructive && ($bulkSideEffectSiteModules > 0 || $bulkKeptSiteModules > 0)) {
                $props['side_effects'] = [
                    'modules_switched_to_internal' => $bulkSideEffectModules,
                    'site_modules_disabled_count' => $bulkSideEffectSiteModules,
                    'site_modules_kept_active_count' => $bulkKeptSiteModules,
                    'revoke_reason' => $bulkRevokeReason !== '' ? $bulkRevokeReason : null,
                    'affected_pairs_count' => count($affectedPairs),
                ];
            }
            ActivityLogger::log(
                'module.bulk_updated',
                'بروزرسانی گروهی ماژول‌ها (' . $action . '): ' . $bulkChanged . ' مورد',
                null,
                $props
            );
        }

        $msg = 'تغییرات گروهی ماژول‌ها اعمال شد.';
        if ($isBulkDestructive && ($bulkSideEffectSiteModules > 0 || $bulkKeptSiteModules > 0)) {
            $msg .= ' (' . $bulkSideEffectSiteModules . ' جفت revoke، ' . $bulkKeptSiteModules . ' جفت با درخواست شما فعال نگه داشته شد.)';
        }
        return redirect()->route('admin.modules.index')->with('success', $msg);
    }

    /**
     * Wave 5.2 / P0-1: نمایش صفحهٔ تأیید per-pair قبل از revoke هیچ سایتی در bulk.
     *
     * @param  \Illuminate\Support\Collection<int, Module>  $modules
     * @param  array<int, array{site_id:int,module_id:int}>  $affectedPairs
     * @param  array<int, int>  $moduleIds
     */
    protected function renderConfirmBulkDestructive(
        $modules,
        array $affectedPairs,
        string $action,
        array $moduleIds,
        string $oldRevokeReason = ''
    ) {
        $siteIds = array_unique(array_map(fn ($p) => $p['site_id'], $affectedPairs));
        $sites = Site::with('customer')
            ->whereIn('id', $siteIds)
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        // برای هر ماژول، لیست سایت‌های دارای commercial attachment.
        $commercialAttachmentsByModule = [];
        $byModule = [];
        foreach ($affectedPairs as $p) {
            $byModule[$p['module_id']][] = $p['site_id'];
        }
        foreach ($byModule as $moduleId => $siteIdsForModule) {
            $commercialAttachmentsByModule[$moduleId] = $this->sitesWithCommercialAttachmentForModule(
                (int) $moduleId,
                array_values(array_unique($siteIdsForModule))
            );
        }

        return view('admin.modules.confirm-bulk-destructive', [
            'modules' => $modules->keyBy('id'),
            'affectedPairs' => $affectedPairs,
            'sites' => $sites,
            'commercialAttachmentsByModule' => $commercialAttachmentsByModule,
            'action' => $action,
            'moduleIds' => $moduleIds,
            'oldRevokeReason' => $oldRevokeReason,
        ]);
    }
}
