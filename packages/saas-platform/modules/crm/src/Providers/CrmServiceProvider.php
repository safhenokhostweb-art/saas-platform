<?php

namespace SaaSPlatform\Modules\Crm\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SaaSPlatform\Modules\Crm\Console\Commands\SendCrmTaskRemindersCommand;
use SaaSPlatform\Modules\Crm\Console\Commands\ValidateCrmIsolationCommand;
use SaaSPlatform\Modules\Crm\Listeners\CrmFormSubmissionListener;
use SaaSPlatform\Modules\Crm\Listeners\CrmTicketCreatedListener;

class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/crm.php', 'crm');
        $this->mergeConfigFrom(__DIR__ . '/../../config/module-manifest.php', 'module-manifest-crm-suite');
    }

    public function boot(): void
    {
        if (config('crm.routes.web', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        }
        $this->loadRoutesFrom(__DIR__ . '/../../routes/site.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'crm');

        if ($this->app->runningInConsole()) {
            $this->commands([ValidateCrmIsolationCommand::class, SendCrmTaskRemindersCommand::class]);
        }

        $this->registerCrossModuleListeners();
        $this->registerModuleManifest();

        $this->publishes([
            __DIR__ . '/../../config/crm.php' => config_path('crm.php'),
        ], 'crm-config');
        $this->publishes([
            __DIR__ . '/../../config/module-manifest.php' => config_path('module-manifest-crm-suite.php'),
        ], 'crm-manifest');
        $this->publishes([
            __DIR__ . '/../../resources/views' => resource_path('views/vendor/crm'),
        ], 'crm-views');
        $this->publishes([
            __DIR__ . '/../../database/migrations' => database_path('migrations'),
        ], 'crm-migrations');
    }

    /**
     * Phase X — self-registers this module's manifest slug into a shared,
     * host-app-agnostic registry, mirroring TicketsServiceProvider's
     * registerModuleAssets() pattern. Lets AdminMenu (customer-site)
     * discover this module's config('module-manifest-crm-suite') without
     * ever hardcoding the 'crm-suite' slug itself.
     */
    private function registerModuleManifest(): void
    {
        $installed = config('module-manifests.installed', []);
        if (!in_array('crm-suite', $installed, true)) {
            $installed[] = 'crm-suite';
        }
        config(['module-manifests.installed' => $installed]);
    }

    /**
     * Phase 20 — Part 7: listens for sibling modules' own events by
     * fully-qualified string name — `::class` resolves to a string at
     * compile time regardless of whether the class is actually
     * autoloadable, so this never creates a hard dependency on Forms or
     * Tickets (module.json's dependencies.modules stays []). The
     * class_exists() guard is purely a "don't bother registering a
     * listener that can never fire" optimization, not a safety
     * requirement.
     */
    private function registerCrossModuleListeners(): void
    {
        if (class_exists(\SaaSPlatform\Modules\Forms\Events\FormSubmissionCreated::class)) {
            Event::listen(\SaaSPlatform\Modules\Forms\Events\FormSubmissionCreated::class, CrmFormSubmissionListener::class);
        }

        if (class_exists(\SaaSPlatform\Modules\Tickets\Events\TicketCreated::class)) {
            Event::listen(\SaaSPlatform\Modules\Tickets\Events\TicketCreated::class, CrmTicketCreatedListener::class);
        }
    }
}
