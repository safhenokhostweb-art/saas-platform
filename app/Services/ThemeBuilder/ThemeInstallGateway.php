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
                'header_blocks'        => $theme->header_blocks ?? [],
                'footer_blocks'        => $theme->footer_blocks ?? [],
                // THEME-03: Theme-owned selectable Header/Footer variants —
                // additive alongside the flat header_blocks/footer_blocks
                // above (kept for backward compatibility with any consumer
                // still reading them directly), always non-empty (see
                // Theme::getHeaderVariants()'s single-variant synthesis).
                'header_variants'      => $theme->getHeaderVariants(),
                'footer_variants'      => $theme->getFooterVariants(),
                'pages'                => $theme->getPageList(),
                'global_styles'        => $theme->global_styles ?? [],
                // Manifest-declared Extension CSS/JS, projected to absolute
                // saas-platform URLs — see ExtensionThemeRuntimeAssetProjector's
                // own doc comment for why this is an absolute URL, not a copy.
                // Absent/empty for every Theme that selects no Extension.
                'extension_assets'     => ExtensionThemeRuntimeAssetProjector::project($theme),
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
}
