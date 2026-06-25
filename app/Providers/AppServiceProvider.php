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

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Support Ticket Repositories & Services
        $this->app->bind(SupportTicketRepositoryInterface::class, SupportTicketRepository::class);
        $this->app->bind(SupportTicketReplyRepositoryInterface::class, SupportTicketReplyRepository::class);
        $this->app->bind(SupportTicketServiceInterface::class, SupportTicketService::class);

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

        Product::observe(ProductObserver::class);
        Site::observe(SiteObserver::class);
        ThemePurchaseOrder::observe(ThemePurchaseOrderObserver::class);
        ThemeBundleOrder::observe(ThemeBundleOrderObserver::class);
        Invoice::observe(InvoiceLedgerObserver::class);
        SiteLifecycleInvoice::observe(SiteLifecycleInvoiceObserver::class);

        // ثبت directive برای تبدیل اعداد انگلیسی به فارسی در Blade
        \Blade::directive('persianNumber', function ($expression) {
            return "<?php echo \App\Helpers\NumberHelper::toPersianNumbers($expression); ?>";
        });
    }
}
