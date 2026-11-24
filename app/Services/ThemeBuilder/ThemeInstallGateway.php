<?php

namespace App\Services\ThemeBuilder;

use App\Models\Site;
use App\Models\Theme;
use App\Services\ThemeBuilder\Extension\ExtensionThemeRuntimeAssetProjector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SP-01 / SP-02 (TASK 2-SEC): THE single Theme install/page-provision
 * authority. Every caller that needs to push a Theme's current
 * pages/header/footer/global-styles state onto a customer site — manual
 * "Install to site" and "Sync pages" (ThemeCanvasController, previously two
 * copies of this HTTP call; previously a raw cross-server MySQL page-insert
 * for "sync") and marketplace purchase fulfillment (ThemePurchaseOrderObserver,
 * previously its own bare Page::create() call via a separate
 * /builder/api/theme/provision webhook and ThemePageProvisionService) MUST
 * come through here.
 *
 * This makes exactly one authenticated HTTP call to the customer-site's own
 * canonical /builder/api/theme/install endpoint
 * (PageBuilderController::installTheme()), which writes through
 * ThemeInstanceRevisionService::record() on the customer-site side —
 * CanvasReservedSlugs collision guard, immutable ThemeInstanceRevision
 * history, the same write path a customer's own publish/rollback/import
 * use. There is intentionally no other way for Theme pages to reach a
 * customer site's `pages` table from this codebase
 * (THEME_INSTALL_MUTATION_AUTHORITIES = 1). Each call is safe to repeat: it
 * records one more real ThemeInstanceRevision, never a raw overwrite or a
 * duplicate-page collision — so this is idempotent under retried/duplicate
 * fulfillment by construction, not by a second, bespoke idempotency check.
 */
class ThemeInstallGateway
{
    public function install(Theme $theme, Site $site): ThemeInstallResult
    {
        $domain = $site->getDomain();
        if (!$domain) {
            return ThemeInstallResult::noDomain(
                'دامنه سایت "' . $site->title . '" تنظیم نشده است. از منوی سایت‌ها → ویرایش سایت، فیلد دامنه را پر کنید.'
            );
        }

        $baseUrl  = str_starts_with($domain, 'http') ? rtrim($domain, '/') : 'https://' . rtrim($domain, '/');
        $endpoint = $baseUrl . '/builder/api/theme/install';
        $secret   = config('services.customer_provision_secret', '');

        $currentRelease = $theme->currentRelease;
        $snapshot = is_array($currentRelease?->snapshot) ? $currentRelease->snapshot : null;

        $headerBlocks = $snapshot['header_blocks'] ?? ($theme->header_blocks ?? []);
        $footerBlocks = $snapshot['footer_blocks'] ?? ($theme->footer_blocks ?? []);
        $pages = $snapshot['pages'] ?? null;
        if (!is_array($pages) || empty($pages)) {
            $pages = $theme->getPageList();
        }
        $globalStyles = $snapshot['global_styles'] ?? ($theme->global_styles ?? []);
        $settings = $snapshot['settings'] ?? ($theme->settings ?? []);
        $headerVariants = $snapshot['header_variants'] ?? $theme->getHeaderVariants();
        $footerVariants = $snapshot['footer_variants'] ?? $theme->getFooterVariants();

        try {
            $response = Http::withHeaders([
                'X-Provision-Secret' => $secret,
                'Accept'             => 'application/json',
            ])->timeout(15)->post($endpoint, [
                'theme_id'             => $theme->slug,
                'theme_name'           => $theme->name,
                'source_theme_id'      => (string) $theme->id,
                'source_theme_version' => $currentRelease?->version,
                'source_release_id'    => $currentRelease?->id,
                'header_blocks'        => $headerBlocks,
                'footer_blocks'        => $footerBlocks,
                // THEME-03: Theme-owned selectable Header/Footer variants —
                // additive alongside the flat header_blocks/footer_blocks
                // above (kept for backward compatibility with any consumer
                // still reading them directly), always non-empty (see
                // Theme::getHeaderVariants()'s single-variant synthesis).
                'header_variants'      => $headerVariants,
                'footer_variants'      => $footerVariants,
                'pages'                => $pages,
                'global_styles'        => $globalStyles,
                // Manifest-declared Extension CSS/JS, projected to absolute
                // saas-platform URLs — see ExtensionThemeRuntimeAssetProjector's
                // own doc comment for why this is an absolute URL, not a copy.
                // Absent/empty for every Theme that selects no Extension.
                'extension_assets'     => ExtensionThemeRuntimeAssetProjector::project($theme),
                // Phase 3 — structured theme-global asset manifest (Phase 2D
                // contract, unchanged): ordered css/js/file entries with pinned
                // sha256, authored by the Theme itself. Source of truth is
                // Theme.settings['theme_assets_manifest']; absent/empty for
                // every Theme that declares no global assets, so existing
                // themes are unaffected.
                'theme_assets'         => $settings['theme_assets_manifest'] ?? [],
            ]);
        } catch (\Throwable $e) {
            Log::error('ThemeInstall request failed', [
                'theme_id' => $theme->id,
                'site_id'  => $site->id,
                'error'    => $e->getMessage(),
            ]);
            return ThemeInstallResult::requestFailed(
                'اتصال به سایت "' . $site->title . '" ناموفق بود: ' . $e->getMessage()
            );
        }

        if ($response->status() === 409) {
            $body = $response->json() ?? [];
            return ThemeInstallResult::collision(
                'عملیات روی سایت "' . $site->title . '" به دلیل تداخل نام‌گذاری صفحات متوقف شد: '
                . ($body['message'] ?? 'یکی از صفحه‌های این قالب با صفحه‌ای موجود در سایت تداخل دارد.')
            );
        }

        if (!$response->successful()) {
            Log::error('ThemeInstall failed', [
                'theme_id' => $theme->id,
                'site_id'  => $site->id,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
            return ThemeInstallResult::httpFailed(
                'عملیات روی سایت "' . $site->title . '" ناموفق بود (کد ' . $response->status() . ').',
                $response->status()
            );
        }

        return ThemeInstallResult::ok($response->json() ?? []);
    }

    /**
     * Non-destructive 3-Way Reconciled Theme Upgrade authority.
     * Calls customer-site POST /builder/api/theme/upgrade using X-Provision-Secret.
     */
    public function upgrade(Site $site, \App\Models\ThemeRelease $release): ThemeInstallResult
    {
        $domain = $site->getDomain();
        if (!$domain) {
            return ThemeInstallResult::noDomain(
                'دامنه سایت "' . $site->title . '" تنظیم نشده است.'
            );
        }

        $theme = $release->theme;
        if (!$theme) {
            return ThemeInstallResult::requestFailed('قالب مرتبط با این نسخه یافت نشد.');
        }

        $baseUrl  = str_starts_with($domain, 'http') ? rtrim($domain, '/') : 'https://' . rtrim($domain, '/');
        $endpoint = $baseUrl . '/builder/api/theme/upgrade';
        $secret   = config('services.customer_provision_secret', '');

        $snapshot = $release->snapshot ?? [];
        $payload = [
            'theme_id'              => $theme->slug,
            'theme_name'            => $theme->name,
            'source_theme_id'       => (string) $theme->id,
            'source_theme_version'  => $release->version,
            'source_release_id'     => $release->id,
            'remote_snapshot'       => [
                'name'             => $theme->name,
                'slug'             => $theme->slug,
                'header_blocks'    => $snapshot['header_blocks'] ?? $theme->header_blocks ?? [],
                'footer_blocks'    => $snapshot['footer_blocks'] ?? $theme->footer_blocks ?? [],
                'header_variants'  => $snapshot['header_variants'] ?? $theme->getHeaderVariants(),
                'footer_variants'  => $snapshot['footer_variants'] ?? $theme->getFooterVariants(),
                'pages'            => $snapshot['pages'] ?? $theme->getPageList(),
                'global_styles'    => $snapshot['global_styles'] ?? $theme->global_styles ?? [],
                'extension_assets' => $snapshot['extension_assets'] ?? ExtensionThemeRuntimeAssetProjector::project($theme),
                'theme_assets'     => $snapshot['theme_assets'] ?? ($theme->settings['theme_assets_manifest'] ?? []),
            ],
        ];

        try {
            $response = Http::withHeaders([
                'X-Provision-Secret' => $secret,
                'Accept'             => 'application/json',
            ])->timeout(25)->post($endpoint, $payload);
        } catch (\Throwable $e) {
            Log::error('ThemeUpgrade request failed', [
                'theme_id'   => $theme->id,
                'release_id' => $release->id,
                'site_id'    => $site->id,
                'error'      => $e->getMessage(),
            ]);
            return ThemeInstallResult::requestFailed(
                'اتصال به سایت "' . $site->title . '" جهت ارتقا ناموفق بود: ' . $e->getMessage()
            );
        }

        if ($response->status() === 409) {
            $body = $response->json() ?? [];
            return ThemeInstallResult::collision(
                'ارتقای قالب روی سایت "' . $site->title . '" به دلیل تداخل متوقف شد: '
                . ($body['message'] ?? 'تداخل ساختاری در ارتقای قالب گزارش شد.')
            );
        }

        if (!$response->successful()) {
            Log::error('ThemeUpgrade failed', [
                'theme_id'   => $theme->id,
                'release_id' => $release->id,
                'site_id'    => $site->id,
                'status'     => $response->status(),
                'body'       => $response->body(),
            ]);
            return ThemeInstallResult::httpFailed(
                'ارتقای قالب روی سایت "' . $site->title . '" ناموفق بود (کد ' . $response->status() . ').',
                $response->status()
            );
        }

        return ThemeInstallResult::ok($response->json() ?? []);
    }

    /**
     * Server-to-server theme rollback authority.
     * Calls customer-site POST /builder/api/theme/rollback using X-Provision-Secret.
     */
    public function rollback(Site $site, array $payload = []): ThemeInstallResult
    {
        $domain = $site->getDomain();
        if (!$domain) {
            return ThemeInstallResult::noDomain(
                'دامنه سایت "' . $site->title . '" تنظیم نشده است.'
            );
        }

        $baseUrl  = str_starts_with($domain, 'http') ? rtrim($domain, '/') : 'https://' . rtrim($domain, '/');
        $endpoint = $baseUrl . '/builder/api/theme/rollback';
        $secret   = config('services.customer_provision_secret', '');

        try {
            $response = Http::withHeaders([
                'X-Provision-Secret' => $secret,
                'Accept'             => 'application/json',
            ])->timeout(20)->post($endpoint, $payload);
        } catch (\Throwable $e) {
            Log::error('ThemeRollback request failed', [
                'site_id' => $site->id,
                'error'   => $e->getMessage(),
            ]);
            return ThemeInstallResult::requestFailed(
                'اتصال به سایت "' . $site->title . '" جهت بازگشت به نسخه قبل ناموفق بود: ' . $e->getMessage()
            );
        }

        if (!$response->successful()) {
            Log::error('ThemeRollback failed', [
                'site_id' => $site->id,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);
            return ThemeInstallResult::httpFailed(
                'بازگشت به نسخه قبل روی سایت "' . $site->title . '" ناموفق بود (کد ' . $response->status() . ').',
                $response->status()
            );
        }

        return ThemeInstallResult::ok($response->json() ?? []);
    }
}
