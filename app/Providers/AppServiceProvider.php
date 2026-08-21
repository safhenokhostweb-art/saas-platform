<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Site;
use App\Models\SiteLifecycleInvoice;
use App\Models\ThemeBundleOrder;
use App\Models\ThemePurchaseOrder;
use App\Observers\InvoiceLedgerObserver;
use App\Observers\ProductObserver;
use App\Observers\SiteLifecycleInvoiceObserver;
use App\Observers\SiteObserver;
use App\Observers\ThemeBundleOrderObserver;
use App\Observers\ThemePurchaseOrderObserver;
use App\Contracts\Repositories\SupportTicketRepositoryInterface;
use App\Contracts\Repositories\SupportTicketReplyRepositoryInterface;
use App\Contracts\Services\SupportTicketServiceInterface;
use App\Repositories\SupportTicketRepository;
use App\Repositories\SupportTicketReplyRepository;
use App\Services\SupportTicketService;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\CoreSiteNotificationDispatcher;
use App\Services\Sms\SmsGatewayResolver;
use SaaSPlatform\CoreUtils\Contracts\Services\SiteNotificationDispatcherInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Support Ticket Repositories & Services
        $this->app->bind(SupportTicketRepositoryInterface::class, SupportTicketRepository::class);
        $this->app->bind(SupportTicketReplyRepositoryInterface::class, SupportTicketReplyRepository::class);
        $this->app->bind(SupportTicketServiceInterface::class, SupportTicketService::class);

        // Phase 20 — Part 6/7: generic extension point so independent modules
        // (e.g. CRM) can notify a site admin without touching App\Models\*.
        $this->app->bind(SiteNotificationDispatcherInterface::class, fn () => new CoreSiteNotificationDispatcher(SmsGatewayResolver::resolve()));

        // PHASE M3.2 — DOMAIN B: Platform Messaging Runtime
        $this->app->singleton(\App\Services\PlatformMessaging\PlatformParticipantService::class);
        $this->app->singleton(\App\Services\PlatformMessaging\PlatformMessageService::class);
        $this->app->singleton(\App\Services\PlatformMessaging\PlatformConversationService::class);
        $this->app->singleton(\App\Services\PlatformMessaging\PlatformBoundaryValidator::class);

        // 8.3d: Slack alert service — singleton so all callers share one instance
        $this->app->singleton(\App\Services\Infrastructure\SlackAlertService::class);

        // INFRA-6: Remote Agent client
        $this->app->singleton(\App\Services\Infrastructure\RemoteAgentClient::class);

        // TB-3: Theme AI Analyzer (reads ANTHROPIC_API_KEY at resolve time)
        $this->app->singleton(\App\Services\ThemeBuilder\ThemeAiAnalyzerService::class, function () {
            return new \App\Services\ThemeBuilder\ThemeAiAnalyzerService(
                (string) config('services.anthropic.key', env('ANTHROPIC_API_KEY', ''))
            );
        });

        // Payment Gateway
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            $driver = config('payment.default', 'zarinpal');
            $class = config("payment.gateways.{$driver}");
            if (!$class || !class_exists($class)) {
                $class = \App\Services\Payment\ZarinpalGateway::class;
            }
            return $app->make($class);
        });
    }

    public function boot(): void
    {
        // Prevent silent degradation to file-based circuit breakers in production.
        // Redis is required for multi-tenant cache isolation (6 named stores, DB3-DB8).
        if (config('app.env') === 'production' && config('cache.default') === 'file') {
            throw new \RuntimeException('Production requires CACHE_DRIVER=redis — file cache breaks multi-tenant isolation.');
        }

        // Root-cause fix (MediaStorage refactor regression): Flysystem's
        // LocalFilesystemAdapter creates its ROOT DIRECTORY inside its
        // constructor (lazyRootCreation defaults to false), so merely resolving
        // Storage::disk('uploads') throws UnableToCreateDirectory when e.g.
        // public/uploads is missing — turning a read-only avatar URL render on
        // /admin/dashboard into an HTTP 500. Guarantee every local disk root
        // exists at bootstrap using native filesystem calls (never
        // Storage::disk(), which would trigger the same constructor). This is
        // best-effort: a creation failure is logged, never fatal at boot.
        $this->ensureLocalStorageRoots();

        Product::observe(ProductObserver::class);
        Site::observe(SiteObserver::class);
        ThemePurchaseOrder::observe(ThemePurchaseOrderObserver::class);
        ThemeBundleOrder::observe(ThemeBundleOrderObserver::class);
        Invoice::observe(InvoiceLedgerObserver::class);
        SiteLifecycleInvoice::observe(SiteLifecycleInvoiceObserver::class);

        // ── PERSIAN LOCALIZATION GLOBAL STANDARD — Blade Directives ────────────
        // Route ALL formatting through PersianFormatter — never use number_format()
        // or Carbon::format() directly in Blade views.

        // @persianNumber($n) — legacy alias, keep for backward compat
        \Blade::directive('persianNumber', function ($expression) {
            return "<?php echo \\App\\Helpers\\NumberHelper::toPersianNumbers($expression); ?>";
        });

        // @numFa($n) / @numFa($n, 2)  — number with thousand sep + Persian digits
        \Blade::directive('numFa', function ($expression) {
            return "<?php echo \\App\\Services\\PersianFormatter::num($expression); ?>";
        });

        // @priceFa($n) / @priceFa($n, 'ریال')  — formatted price with unit
        \Blade::directive('priceFa', function ($expression) {
            return "<?php echo \\App\\Services\\PersianFormatter::currency($expression); ?>";
        });

        // @dateFa($d)  — Jalali date  ۱۴۰۵/۰۴/۰۴
        \Blade::directive('dateFa', function ($expression) {
            return "<?php echo \\App\\Services\\PersianFormatter::date($expression); ?>";
        });

        // @dateFaLong($d)  — چهارشنبه ۴ تیر ۱۴۰۵
        \Blade::directive('dateFaLong', function ($expression) {
            return "<?php echo \\App\\Services\\PersianFormatter::dateLong($expression); ?>";
        });

        // @dateFaShort($d)  — ۴ تیر ۱۴۰۵
        \Blade::directive('dateFaShort', function ($expression) {
            return "<?php echo \\App\\Services\\PersianFormatter::dateShort($expression); ?>";
        });

        // @datetimeFa($d)  — ۱۴۰۵/۰۴/۰۴ ۱۴:۳۰
        \Blade::directive('datetimeFa', function ($expression) {
            return "<?php echo \\App\\Services\\PersianFormatter::datetime($expression); ?>";
        });

        // @agoFa($d)  — ۳ دقیقه پیش / دیروز / همین الان
        \Blade::directive('agoFa', function ($expression) {
            return "<?php echo \\App\\Services\\PersianFormatter::ago($expression); ?>";
        });

        // @digitsFa($s)  — convert any digits in a string to Persian
        \Blade::directive('digitsFa', function ($expression) {
            return "<?php echo \\App\\Services\\PersianFormatter::digits($expression); ?>";
        });
    }

    /**
     * Guarantee every configured LOCAL filesystem disk root exists before any
     * Storage::disk() is resolved.
     *
     * Why: league/flysystem-local's LocalFilesystemAdapter creates its root
     * inside its CONSTRUCTOR (lazyRootCreation defaults to false in the
     * installed version, and Laravel's createLocalDriver never passes true).
     * The first time any code resolves a local disk — even for a read-only
     * `url()` — the adapter tries `mkdir($root)` and throws
     * UnableToCreateDirectory when the root is missing and not creatable.
     * The MediaStorage refactor routed avatar/theme/sitemap reads through
     * Storage::disk(), which turned this latent behavior into an HTTP 500.
     *
     * Native is_dir()/ensureDirectoryExists() are used here (never
     * Storage::disk(), which would recurse into the same constructor), so a
     * missing or unwritable root is created when possible and otherwise only
     * logged. This is idempotent and safe to run on every request.
     */
    private function ensureLocalStorageRoots(): void
    {
        $files = app(\Illuminate\Filesystem\Filesystem::class);

        foreach ((array) config('filesystems.disks', []) as $name => $disk) {
            if (($disk['driver'] ?? null) !== 'local' || empty($disk['root'])) {
                continue;
            }

            $root = $disk['root'];

            try {
                if (! $files->isDirectory($root)) {
                    $files->ensureDirectoryExists($root, 0775, true);

                    if ($files->isDirectory($root)) {
                        @chmod($root, 0775);
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    "Unable to guarantee filesystem root for disk '{$name}' at {$root}",
                    ['exception' => $e->getMessage()]
                );
            }
        }
    }
}
