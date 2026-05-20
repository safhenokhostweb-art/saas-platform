<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Product;
use App\Observers\ProductObserver;
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
        Product::observe(ProductObserver::class);

        // ثبت directive برای تبدیل اعداد انگلیسی به فارسی در Blade
        \Blade::directive('persianNumber', function ($expression) {
            return "<?php echo \App\Helpers\NumberHelper::toPersianNumbers($expression); ?>";
        });
    }
}
