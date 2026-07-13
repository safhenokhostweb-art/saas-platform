<?php

namespace App\Providers;

use App\Console\Commands\GenerateMasterKeyCommand;
use App\Console\Commands\RegisterInstallPackageCommand;
use App\Console\Commands\RegisterLocalInstallPackageCommand;
use App\Console\Commands\CustomerInstallAcceptanceCommand;
use App\Services\CustomerInstallation\Contracts\CustomerPackageInterface;
use App\Services\CustomerInstallation\Contracts\InstallationStateInterface;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\CustomerInstallationOrchestrator;
use App\Services\CustomerInstallation\CustomerInstallationRollbackService;
use App\Services\CustomerInstallation\CustomerReleasePackageService;
use App\Services\CustomerInstallation\DriverFactory;
use App\Services\CustomerInstallation\InstallationStateManager;
use App\Services\CustomerInstallation\PostInstallValidationService;
use Illuminate\Support\ServiceProvider;

class CustomerInstallationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InstallationStateInterface::class, InstallationStateManager::class);
        $this->app->singleton(InstallationStateManager::class);
        $this->app->singleton(CredentialVault::class);
        $this->app->singleton(DriverFactory::class);
        $this->app->singleton(CustomerInstallationOrchestrator::class);
        $this->app->singleton(CustomerInstallationRollbackService::class);
        $this->app->singleton(PostInstallValidationService::class);
        $this->app->bind(CustomerPackageInterface::class, CustomerReleasePackageService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateMasterKeyCommand::class,
                RegisterInstallPackageCommand::class,
                RegisterLocalInstallPackageCommand::class,
                CustomerInstallAcceptanceCommand::class,
            ]);
        }
    }
}
