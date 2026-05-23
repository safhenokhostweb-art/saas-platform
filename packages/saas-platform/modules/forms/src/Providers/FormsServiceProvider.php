<?php

namespace SaaSPlatform\Modules\Forms\Providers;

use Illuminate\Support\ServiceProvider;
use SaaSPlatform\Modules\Forms\Contracts\FormRepositoryInterface;
use SaaSPlatform\Modules\Forms\Contracts\FormFieldRepositoryInterface;
use SaaSPlatform\Modules\Forms\Contracts\FormSubmissionRepositoryInterface;
use SaaSPlatform\Modules\Forms\Contracts\FormServiceInterface;
use SaaSPlatform\Modules\Forms\Contracts\FormSiteServiceInterface;
use SaaSPlatform\Modules\Forms\Repositories\FormRepository;
use SaaSPlatform\Modules\Forms\Repositories\FormFieldRepository;
use SaaSPlatform\Modules\Forms\Repositories\FormSubmissionRepository;
use SaaSPlatform\Modules\Forms\Services\FormService;
use SaaSPlatform\Modules\Forms\Services\FormSiteService;

class FormsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/forms.php',
            'forms'
        );

        // Bind repositories
        $this->app->bind(FormRepositoryInterface::class, FormRepository::class);
        $this->app->bind(FormFieldRepositoryInterface::class, FormFieldRepository::class);
        $this->app->bind(FormSubmissionRepositoryInterface::class, FormSubmissionRepository::class);

        // Bind services
        $this->app->bind(FormServiceInterface::class, FormService::class);
        $this->app->bind(FormSiteServiceInterface::class, FormSiteService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load views
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'forms');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \SaaSPlatform\Modules\Forms\Console\Commands\InstallFormsModule::class,
            ]);
        }

        // Publish config
        $this->publishes([
            __DIR__ . '/../../config/forms.php' => config_path('forms.php'),
        ], 'forms-config');

        // Publish views (for override)
        $this->publishes([
            __DIR__ . '/../../resources/views' => resource_path('views/vendor/forms'),
        ], 'forms-views');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../../database/migrations' => database_path('migrations'),
        ], 'forms-migrations');
    }
}
