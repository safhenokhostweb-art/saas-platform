<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Repositories\UserRepository;
use App\Repositories\CustomerRepository;

/**
 * @file repository-service-provider.php
 * قانون ۱: معماری لایه‌ها - Binding اینترفیس‌ها به پیاده‌سازی
 */
class RepositoryServiceProvider extends ServiceProvider
{
    public array $bindings = [
        UserRepositoryInterface::class     => UserRepository::class,
        CustomerRepositoryInterface::class => CustomerRepository::class,
    ];

    public function register(): void
    {
        // Bindings are defined in $bindings property above
    }

    public function boot(): void
    {
        //
    }
}