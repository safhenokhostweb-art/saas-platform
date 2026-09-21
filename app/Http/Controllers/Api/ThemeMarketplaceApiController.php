<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeLicense;
use App\Models\ThemePurchaseOrder;
use App\Models\ThemeRelease;
use App\Services\Payment\PaymentGatewayResolver;
use App\Services\PublishedThemeAssignmentService;
use App\Models\ThemeAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API فروشگاه قالب — برای customer-site (auth: tenant.api / license_key + domain)
 *
 * Phase 1f cutover decision (ADR-021, Commercial Product Architecture
 * Contract §9.4/§11): a theme with `dependency_status = 'unresolved'` (see
 * dependencyStatus()) is NOT excluded from listing/purchase here — it stays
 * listable exactly as before, gated only by `is_marketplace` and (Phase 2d,
 * ADR-025) `Theme::isCommerciallyEligible()`. Excluding it would be a
 * commercial/listing-policy change (what customers can buy) that this
 * technical cutover does not make unilaterally. Instead the response makes
 * the gap explicit and honest so a storefront can choose to render a
 * warning; whether to actually exclude such themes from "commercial-ready"
 * listings is a product decision left for an explicit follow-up, not
 * decided here.
 */
class ThemeMarketplaceApiController extends Controller
{
    public function __construct(
        protected PaymentGatewayResolver $gatewayResolver,
        protected PublishedThemeAssignmentService $assignments,
    ) {}

    // GET /api/theme-marketplace
    public function index(Request $request): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        // Phase 2d (ADR-025): catalog visibility (is_marketplace + status)
        // is a separate concern from commercial eligibility — a theme the
        // storefront lists as a product must actually be currently sellable,
        // never merely "marked published". Filtered in PHP after the query:
        // the catalog is small (dozens, not millions, of rows) and this
        // avoids a fragile correlated-subquery duplicate of
        // Theme::isCommerciallyEligible()'s own logic.
        $themes = Theme::with(['marketplaceMeta', 'currentRelease.structuredDependencies', 'currentRelease.qualityReports'])
            ->marketplace()
            ->orderByDesc(fn ($q) => $q->from('theme_marketplace')->whereColumn('theme_id', 'themes.id')->select('is_featured'))
            ->orderBy('name')
            ->get()
            ->filter(fn (Theme $t) => $t->isCommerciallyEligible())
            ->map(fn ($t) => $this->formatTheme($t, $site->id));

        return response()->json(['success' => true, 'themes' => $themes->values()->all()]);
    }

    // GET /api/theme-marketplace/{theme}
    public function show(Request $request, Theme $theme): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        if (!$theme->is_marketplace || !$theme->isCommerciallyEligible()) {
            return response()->json(['success' => false, 'error' => 'theme_not_found'], 404);
        }

        return response()->json([
            'success' => true,
            'theme'   => $this->formatTheme($theme->load(['marketplaceMeta', 'currentRelease.structuredDependencies']), $site->id),
        ]);
    }

    // GET /api/theme-marketplace/my-licenses
    public function myLicenses(Request $request): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        $licenses = ThemeLicense::with(['theme.marketplaceMeta', 'theme.currentRelease', 'installedRelease'])
            ->where('site_id', $site->id)
            ->orderByDesc('purchased_at')
            ->get()
            ->map(fn ($lic) => [
                'license_key'             => $lic->license_key,
                'purchased_at'            => $lic->purchased_at?->toDateString(),
                'is_permanent'            => $lic->is_permanent,
                // GAP-L01: expose expiry for non-permanent licenses (1 year from purchase)
                'expires_at'              => $lic->is_permanent
                    ? null
                    : $lic->purchased_at?->addYear()->toDateString(),
                // ThemeRelease-authoritative — the sole commercial version
                // state (Phase 2b: legacy installed_theme_version/is_outdated
                // retired; no active-customer compatibility reason remains).
                // null until this license has an installed_release_id (a
                // theme with no release yet) — never guessed.
                'installed_version'       => $lic->installedRelease?->version,
                'is_outdated_by_ledger'   => $lic->isOutdatedByLedger(),
                'theme' => $lic->theme ? [
                    'id'              => $lic->theme->id,
                    'name'            => $lic->theme->name,
                    'slug'            => $lic->theme->slug,
                    'thumbnail'       => $lic->theme->thumbnail,
                    'category'        => $lic->theme->category,
                    'version_notes'   => $lic->theme->marketplaceMeta?->version_notes,
                    // ThemeRelease-authoritative current commercial version.
                    // Null when the theme has never been through the real
                    // publish flow yet.
                    'current_version' => $lic->theme->currentRelease?->version,
                ] : null,
            ]);

        return response()->json(['success' => true, 'licenses' => $licenses->values()->all()]);
    }

    // POST /api/theme-marketplace/{theme}/initiate-purchase
    public function initiatePurchase(Request $request, Theme $theme): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        if (!$theme->is_marketplace || !$theme->isCommerciallyEligible()) {
            return response()->json(['success' => false, 'error' => 'theme_not_available'], 404);
        }

        if ($theme->hasLicenseForSite($site->id)) {
            return response()->json(['success' => false, 'error' => 'already_purchased'], 422);
        }

        $data = $request->validate([
            'redirect_back_url' => 'required|string|url|max:500',
        ]);

        if ($theme->marketplace_price === 0) {
            $assignment = $this->assignments->assign(
                $site,
                $theme,
                ThemeAssignment::SOURCE_MARKETPLACE,
                selectForSite: false,
            );
            return response()->json([
                'success'     => true,
                'free'        => true,
                'license_key' => $assignment->themeLicense->license_key,
                'release_id'  => $assignment->release->id,
            ]);
        }

        // Pending order already exists?
        $existing = ThemePurchaseOrder::where('site_id', $site->id)
            ->where('theme_id', $theme->id)
            ->where('status', ThemePurchaseOrder::STATUS_PENDING)
            ->latest()
            ->first();

        if ($existing && $existing->created_at->gt(now()->subMinutes(30))) {
            return $this->buildPaymentResponse($existing);
        }

        $order = ThemePurchaseOrder::create([
            'site_id'           => $site->id,
            'theme_id'          => $theme->id,
            'amount_rial'       => $theme->marketplace_price,
            'status'            => ThemePurchaseOrder::STATUS_PENDING,
            'redirect_back_url' => $data['redirect_back_url'],
        ]);

        try {
            $gateway    = $this->gatewayResolver->resolveForSite($site->id);
            $callbackUrl = route('theme-marketplace.payment-callback', ['order' => $order->id]);
            $desc        = 'خرید قالب: ' . $theme->name;

            $result = $gateway->request($theme->marketplace_price, $callbackUrl, $desc, (string) $order->id);

            if (empty($result['success'])) {
                $order->update(['status' => ThemePurchaseOrder::STATUS_FAILED]);
                return response()->json(['success' => false, 'error' => $result['error'] ?? 'gateway_error'], 502);
            }

            $order->update([
                'gateway'           => $gateway->name(),
                'gateway_authority' => $result['authority'] ?? null,
            ]);

            return response()->json([
                'success'      => true,
                'free'         => false,
                'redirect_url' => $result['redirect_url'],
                'order_id'     => $order->id,
            ]);

        } catch (\Throwable $e) {
            $order->update(['status' => ThemePurchaseOrder::STATUS_FAILED]);
            return response()->json(['success' => false, 'error' => 'gateway_exception'], 502);
        }
    }

    // GET /api/theme-marketplace/{theme}/download
    public function download(Request $request, Theme $theme): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        if (!$theme->hasLicenseForSite($site->id)) {
            return response()->json(['success' => false, 'error' => 'no_license'], 403);
        }

        $theme->loadMissing('currentRelease.structuredDependencies');

        // Phase 2d (ADR-025): an already-licensed site is still not entitled
        // to install/redownload a theme that is not currently commercially
        // eligible — e.g. a theme rolled back to an unapproved state after
        // the license was granted. Fail closed rather than silently serving
        // draft/unapproved content as if it were the commercial current
        // release.
        if (!$theme->isCommerciallyEligible()) {
            return response()->json(['success' => false, 'error' => 'theme_not_commercially_eligible'], 409);
        }

        // Record install: increment installs_count, stamp installed_release_id
        // so the ledger-aware isOutdatedByLedger() comparison stays accurate
        // across re-downloads/updates, not just at initial grant() time.
        $theme->increment('installs_count');
        ThemeLicense::where('site_id', $site->id)->where('theme_id', $theme->id)
            ->update(['installed_release_id' => $theme->current_release_id]);
        // Auto-promote to bestseller
        if ($theme->installs_count >= 10) {
            \App\Models\ThemeMarketplace::where('theme_id', $theme->id)
                ->where('is_bestseller', false)
                ->update(['is_bestseller' => true, 'is_new_release' => false]);
        }

        return response()->json([
            'success'      => true,
            'theme_id'     => $theme->id,
            'theme_name'   => $theme->name,
            'theme_slug'   => $theme->slug,
            'header_blocks'=> $theme->header_blocks ?? [],
            'footer_blocks'=> $theme->footer_blocks ?? [],
            'pages'        => $theme->pages ?? [],
            'global_styles'=> $theme->global_styles ?? [],
            'settings'     => $theme->settings ?? [],
            // ThemeRelease-authoritative identity of exactly what was just
            // downloaded (Phase 1h — customer-site needs this to record real
            // lineage in theme_instance_revisions instead of leaving it
            // permanently null). Null when the theme has no release yet —
            // never fabricated from the legacy int.
            'release_version' => $theme->currentRelease?->version,
            'release_id'       => $theme->currentRelease?->id,
            // Phase 3E-A: this endpoint previously omitted the release's
            // structured dependencies entirely — the marketplace *listing*
            // (formatTheme(), used by index()/show()) already exposed them
            // pre-purchase, but install()/update() (the only caller of this
            // endpoint) had no way to know what a release actually requires
            // at the moment it matters most: right before applying it.
            // Reuses the exact same, already-tested helpers as the listing
            // — never a second, divergent shape — tied to this exact
            // release (never customer/import-supplied data).
            'dependencies'      => $this->formatDependencies($theme->currentRelease),
            'dependency_status' => $this->dependencyStatus($theme->currentRelease),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * ADR-021 / THEME_PRODUCTIZATION_ARCHITECTURE.md Phase 6 cutover: the
     * commercial-release fields below (current_version, changelog,
     * release_notes, released_at, compatibility, dependencies,
     * dependency_status) are read live from Theme.currentRelease +
     * ThemeRelease.structuredDependencies every request — never cached or
     * duplicated into a new stored column. `requirements`/`version_notes`
     * (legacy, human-authored) are kept unchanged alongside them: this is a
     * transition (Phase 1f), not a removal — see class docblock note below
     * and ADR-021. Do not drop them without a follow-up ADR.
     */
    private function formatTheme(Theme $theme, int $siteId): array
    {
        $meta = $theme->marketplaceMeta;
        $release = $theme->currentRelease;

        return [
            // Core
            'id'             => $theme->id,
            'name'           => $theme->name,
            'slug'           => $theme->slug,
            'description'    => $meta?->marketplace_description ?? $theme->description,
            'thumbnail'      => $theme->thumbnail,
            'preview_image'  => $theme->preview_image,
            // Pricing (Sprint 10) — existing commerce layer, untouched.
            'price_rial'     => $theme->marketplace_price,
            'annual_price'   => $meta?->annual_price,
            'lifetime_price' => $meta?->lifetime_price,
            'is_free'        => $theme->marketplace_price === 0,
            'license_type'   => $theme->license_type ?? 'free',
            // Taxonomy (Sprint 10)
            'category'       => $theme->category,
            // Badges (Sprint 10)
            'is_featured'    => (bool) $meta?->is_featured,
            'is_bestseller'  => (bool) $meta?->is_bestseller,
            'is_new_release' => (bool) $meta?->is_new_release,
            'installs_count' => (int) $theme->installs_count,
            // Quality (Sprint 10)
            'quality_score'  => $meta?->getOverallScore() ?? 0,
            'quality_badge'  => $meta?->getScoreBadge() ?? null,
            'preview_url'    => $meta?->preview_url,
            'screenshots'    => $meta?->screenshots ?? [],
            // @deprecated `requirements` (free-text, never version data) and
            // `version_notes` are functionally superseded by `dependencies`/
            // `dependency_status`/`release_notes` below, but are deliberately
            // NOT removed yet (Phase 2b audit): the admin publish UI has no
            // way to author changelog/release_notes at release time, so
            // removing these now would leave admins with no way to enter
            // this content at all. Retire once that UI exists — see
            // DECISIONS.md. Never authoritative; do not consume in new code.
            'requirements'   => $meta?->requirements ?? [],
            'version_notes'  => $meta?->version_notes,
            'sales_count'    => $meta?->sales_count ?? 0,
            'has_license'    => ThemeLicense::where('site_id', $siteId)->where('theme_id', $theme->id)->exists(),
            // ThemeRelease-authoritative commercial product data (Phase 1f).
            'release_status'    => $release ? 'released' : 'unreleased',
            'current_version'   => $release?->version,
            'changelog'         => $release?->changelog,
            'release_notes'     => $release?->release_notes,
            'released_at'       => $release?->published_at?->toDateString(),
            'compatibility'     => $release?->compatibility,
            'dependencies'      => $this->formatDependencies($release),
            'dependency_status' => $this->dependencyStatus($release),
        ];
    }

    /**
     * @return array<int, array{type: string, identifier: string, is_required: bool, version_constraint: ?string, metadata: array}>
     */
    private function formatDependencies(?ThemeRelease $release): array
    {
        if (!$release) {
            return [];
        }

        return $release->structuredDependencies->map(fn ($dependency) => [
            'type'               => $dependency->dependency_type,
            'identifier'         => $dependency->dependency_identifier,
            'is_required'        => $dependency->is_required,
            // null is a real, valid state (unconstrained) — never coerced to a
            // fabricated default; the storefront must render it as "any version".
            'version_constraint' => $dependency->version_constraint,
            'metadata'           => $dependency->metadata ?? [],
        ])->all();
    }

    /**
     * 'unresolved' — not 'no dependencies' — for any released theme with zero
     * structured dependency rows. Absence of dependency rows is not proof a
     * theme has no dependencies; it may only mean extraction was never run
     * for it (true today for the 2 AI-generated themes with no
     * settings.supported_modules key at all — see
     * ThemeReleaseDependencyBackfillCommand). Marketplace/storefront code
     * must never render this as a positive "no dependencies" claim.
     *
     * Known, documented limitation: a genuinely dependency-free theme
     * authored after this phase (an explicit, intentional `dependencies: []`
     * passed to ThemeReleaseService::release()) would also read as
     * 'unresolved' here, since the two cases are indistinguishable with
     * today's schema. This is the deliberately conservative choice — a false
     * "needs review" is safer than a false "verified complete" (Commercial
     * Product Architecture Contract §11). Disambiguating them is future work
     * (e.g. a stored resolution marker), not done in this phase.
     */
    private function dependencyStatus(?ThemeRelease $release): string
    {
        if (!$release) {
            return 'unknown';
        }

        return $release->structuredDependencies->isNotEmpty() ? 'resolved' : 'unresolved';
    }

    private function buildPaymentResponse(ThemePurchaseOrder $order): JsonResponse
    {
        // Rebuild redirect_url from stored authority if gateway supports it
        $gateway    = $this->gatewayResolver->resolveForSite($order->site_id);
        $callbackUrl = route('theme-marketplace.payment-callback', ['order' => $order->id]);
        $result = $gateway->request($order->amount_rial, $callbackUrl, 'خرید قالب', (string) $order->id);

        if (empty($result['success'])) {
            return response()->json(['success' => false, 'error' => 'gateway_error'], 502);
        }

        $order->update([
            'gateway_authority' => $result['authority'] ?? $order->gateway_authority,
        ]);

        return response()->json([
            'success'      => true,
            'free'         => false,
            'redirect_url' => $result['redirect_url'],
            'order_id'     => $order->id,
        ]);
    }

    /**
     * GAP-H06 — GET /api/theme-marketplace/licenses/{licenseKey}/invoice-data
     *
     * Returns invoice data for a specific theme purchase — used by customer-site to generate PDF.
     */
    public function licenseInvoiceData(Request $request, string $licenseKey): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        $license = ThemeLicense::with('theme')
            ->where('site_id', $site->id)
            ->where('license_key', $licenseKey)
            ->first();

        if (! $license) {
            return response()->json(['success' => false, 'error' => 'license_not_found'], 404);
        }

        $order = $license->purchase_order_id
            ? ThemePurchaseOrder::find($license->purchase_order_id)
            : null;

        $customer = $site->customer;

        return response()->json([
            'success'        => true,
            'invoice_number' => 'THEME-' . str_pad((string) ($license->id), 6, '0', STR_PAD_LEFT),
            'license_key'    => $license->license_key,
            'theme_name'     => $license->theme?->name ?? 'قالب',
            'theme_slug'     => $license->theme?->slug ?? '',
            'purchased_at'   => $license->purchased_at?->toDateString(),
            'amount_rial'    => $order?->amount_rial ?? 0,
            'gateway_ref'    => $order?->gateway_ref ?? '',
            'status'         => $order ? $order->status : 'paid',
            'site_title'     => $site->title,
            'site_domain'    => $site->domain ?? '',
            'customer_name'  => $customer?->name ?? $site->title,
            'customer_mobile' => $customer?->mobile ?? '',
        ]);
    }
}
