<?php

namespace SaaSPlatform\Modules\Crm\Providers;

use Illuminate\Support\ServiceProvider;

class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'crm');
    }
}
