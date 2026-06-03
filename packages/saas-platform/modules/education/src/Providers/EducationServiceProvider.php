<?php

namespace SaaSPlatform\Modules\Education\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Routing\Router;

use SaaSPlatform\Modules\Education\Http\ViewComposers\EducationGuideComposer;
use SaaSPlatform\Modules\Education\Http\Middleware\TrackFeatureUsageMiddleware;

// Contracts — repositories
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationArticleRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationCategoryRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationWalkthroughRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationLearningPathRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationProgressRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationFeatureRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationFeatureUsageRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationRecommendationRepositoryInterface;

// Contracts — services
use SaaSPlatform\Modules\Education\Contracts\Services\EducationServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationSearchServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationContextServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationAiServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\FeatureDiscoveryServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\DocumentationGeneratorServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\FeatureUsageServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationRecommendationServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\WalkthroughTriggerServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationAiContentGeneratorInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationAiRecommendationInterface;

// Implementations — repositories
use SaaSPlatform\Modules\Education\Repositories\EducationArticleRepository;
use SaaSPlatform\Modules\Education\Repositories\EducationCategoryRepository;
use SaaSPlatform\Modules\Education\Repositories\EducationWalkthroughRepository;
use SaaSPlatform\Modules\Education\Repositories\EducationLearningPathRepository;
use SaaSPlatform\Modules\Education\Repositories\EducationProgressRepository;
use SaaSPlatform\Modules\Education\Repositories\EducationFeatureRepository;
use SaaSPlatform\Modules\Education\Repositories\EducationFeatureUsageRepository;
use SaaSPlatform\Modules\Education\Repositories\EducationRecommendationRepository;

// Implementations — services
use SaaSPlatform\Modules\Education\Services\EducationArticleService;
use SaaSPlatform\Modules\Education\Services\EducationCategoryService;
use SaaSPlatform\Modules\Education\Services\EducationSearchService;
use SaaSPlatform\Modules\Education\Services\EducationContextService;
use SaaSPlatform\Modules\Education\Services\EducationWalkthroughService;
use SaaSPlatform\Modules\Education\Services\EducationLearningPathService;
use SaaSPlatform\Modules\Education\Services\EducationProgressService;
use SaaSPlatform\Modules\Education\Services\EducationMediaService;
use SaaSPlatform\Modules\Education\Services\EducationReleaseNoteService;
use SaaSPlatform\Modules\Education\Services\EducationMigrationService;
use SaaSPlatform\Modules\Education\Services\EducationCoverageTracker;
use SaaSPlatform\Modules\Education\Services\EducationAiServiceStub;
use SaaSPlatform\Modules\Education\Services\FeatureDiscoveryService;
use SaaSPlatform\Modules\Education\Services\DocumentationGeneratorService;
use SaaSPlatform\Modules\Education\Services\FeatureUsageService;
use SaaSPlatform\Modules\Education\Services\EducationRecommendationService;
use SaaSPlatform\Modules\Education\Services\WalkthroughTriggerService;
use SaaSPlatform\Modules\Education\Services\EducationAiContentGeneratorStub;
use SaaSPlatform\Modules\Education\Services\EducationAiRecommendationStub;

// Models & policies
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationLearningPath;
use SaaSPlatform\Modules\Education\Policies\EducationArticlePolicy;
use SaaSPlatform\Modules\Education\Policies\EducationLearningPathPolicy;

// Commands
use SaaSPlatform\Modules\Education\Console\Commands\MigrateKbToEducationCommand;
use SaaSPlatform\Modules\Education\Console\Commands\ValidateEducationCommand;
use SaaSPlatform\Modules\Education\Console\Commands\EducationCoverageReportCommand;
use SaaSPlatform\Modules\Education\Console\Commands\DiscoverFeaturesCommand;
use SaaSPlatform\Modules\Education\Console\Commands\GenerateDocumentationCommand;
use SaaSPlatform\Modules\Education\Console\Commands\GenerateRecommendationsCommand;
use SaaSPlatform\Modules\Education\Console\Commands\AdoptionReportCommand;

class EducationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/education.php', 'education');
        $this->mergeConfigFrom(__DIR__ . '/../../config/module-manifest.php', 'module-manifest-education');

        // --- Repository contracts ---
        $this->app->bind(EducationArticleRepositoryInterface::class,       EducationArticleRepository::class);
        $this->app->bind(EducationCategoryRepositoryInterface::class,      EducationCategoryRepository::class);
        $this->app->bind(EducationWalkthroughRepositoryInterface::class,   EducationWalkthroughRepository::class);
        $this->app->bind(EducationLearningPathRepositoryInterface::class,  EducationLearningPathRepository::class);
        $this->app->bind(EducationProgressRepositoryInterface::class,      EducationProgressRepository::class);
        $this->app->bind(EducationFeatureRepositoryInterface::class,       EducationFeatureRepository::class);
        $this->app->bind(EducationFeatureUsageRepositoryInterface::class,  EducationFeatureUsageRepository::class);
        $this->app->bind(EducationRecommendationRepositoryInterface::class, EducationRecommendationRepository::class);

        // --- Legacy AI stub (Phase 1) ---
        $this->app->bind(EducationAiServiceInterface::class, EducationAiServiceStub::class);

        // --- AI-ready interfaces (Phase EDU-11 stubs) ---
        $this->app->bind(EducationAiContentGeneratorInterface::class, EducationAiContentGeneratorStub::class);
        $this->app->bind(EducationAiRecommendationInterface::class,   EducationAiRecommendationStub::class);

        // --- Service singletons (original) ---
        $this->app->singleton(EducationArticleService::class);
        $this->app->singleton(EducationCategoryService::class);
        $this->app->singleton(EducationSearchService::class);
        $this->app->singleton(EducationContextService::class);
        $this->app->singleton(EducationWalkthroughService::class);
        $this->app->singleton(EducationLearningPathService::class);
        $this->app->singleton(EducationProgressService::class);
        $this->app->singleton(EducationMediaService::class);
        $this->app->singleton(EducationReleaseNoteService::class);
        $this->app->singleton(EducationMigrationService::class);
        $this->app->singleton(EducationCoverageTracker::class);

        // --- Service singletons (Phase EDU-11) ---
        $this->app->singleton(FeatureDiscoveryService::class);
        $this->app->singleton(DocumentationGeneratorService::class);
        $this->app->singleton(FeatureUsageService::class);
        $this->app->singleton(EducationRecommendationService::class);
        $this->app->singleton(WalkthroughTriggerService::class);

        // --- Interface → concrete bindings (original) ---
        $this->app->bind(EducationServiceInterface::class,       EducationArticleService::class);
        $this->app->bind(EducationSearchServiceInterface::class,  EducationSearchService::class);
        $this->app->bind(EducationContextServiceInterface::class, EducationContextService::class);

        // --- Interface → concrete bindings (Phase EDU-11) ---
        $this->app->bind(FeatureDiscoveryServiceInterface::class,        FeatureDiscoveryService::class);
        $this->app->bind(DocumentationGeneratorServiceInterface::class,  DocumentationGeneratorService::class);
        $this->app->bind(FeatureUsageServiceInterface::class,            FeatureUsageService::class);
        $this->app->bind(EducationRecommendationServiceInterface::class, EducationRecommendationService::class);
        $this->app->bind(WalkthroughTriggerServiceInterface::class,      WalkthroughTriggerService::class);
    }

    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerViews();
        $this->registerMigrations();
        $this->registerPolicies();
        $this->registerPublishables();
        $this->registerModuleAssets();
        $this->registerCommands();
        $this->registerMiddleware();
        $this->registerViewComposers();
    }

    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../Routes/admin.php');
        $this->loadRoutesFrom(__DIR__ . '/../../Routes/api.php');
        if (config('education.routes.mobile_enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../../Routes/mobile.php');
        }
    }

    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../Resources/views', 'education');
    }

    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../Database/Migrations');
    }

    protected function registerPolicies(): void
    {
        Gate::policy(EducationArticle::class,      EducationArticlePolicy::class);
        Gate::policy(EducationLearningPath::class, EducationLearningPathPolicy::class);
    }

    protected function registerPublishables(): void
    {
        $this->publishes([__DIR__ . '/../../config/education.php'        => config_path('education.php')],                'education-config');
        $this->publishes([__DIR__ . '/../../config/module-manifest.php'  => config_path('module-manifest-education.php')],'education-manifest');
        $this->publishes([__DIR__ . '/../../Resources/views'             => resource_path('views/vendor/education')],     'education-views');
        $this->publishes([__DIR__ . '/../../Database/Migrations'         => database_path('migrations')],                 'education-migrations');
        $this->publishes([__DIR__ . '/../../Resources/assets'            => public_path('vendor/education')],             'education-assets');
    }

    protected function registerModuleAssets(): void
    {
        $existing = config('module-assets.modules', []);
        foreach ($existing as $module) {
            if (($module['name'] ?? '') === 'education') {
                return;
            }
        }
        $existing[] = ['name' => 'education', 'source' => __DIR__ . '/../../Resources/assets'];
        config(['module-assets.modules' => $existing]);
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Original
                MigrateKbToEducationCommand::class,
                ValidateEducationCommand::class,
                EducationCoverageReportCommand::class,
                // Phase EDU-11
                DiscoverFeaturesCommand::class,
                GenerateDocumentationCommand::class,
                GenerateRecommendationsCommand::class,
                AdoptionReportCommand::class,
            ]);
        }
    }

    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('education.track', TrackFeatureUsageMiddleware::class);

        // Attach to all admin web routes so every page visit is tracked automatically
        $router->pushMiddlewareToGroup('web', TrackFeatureUsageMiddleware::class);
    }

    protected function registerViewComposers(): void
    {
        // Inject $eduGuides, $walkthroughTriggers, $eduRecommendationCount into every admin.* view
        View::composer('admin.*', EducationGuideComposer::class);
    }
}
