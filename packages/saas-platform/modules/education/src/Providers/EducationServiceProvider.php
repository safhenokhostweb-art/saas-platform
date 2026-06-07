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
// EDU-14A — AI Education Engine contracts
use SaaSPlatform\Modules\Education\Contracts\Services\UserLearningProfileServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\KnowledgeGapServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\AIRecommendationScoreServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\ContentReadinessServiceInterface;
// EDU-14B — Intelligence Orchestration Layer contracts
use SaaSPlatform\Modules\Education\Contracts\Services\RecommendationRefreshServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\KnowledgeGapQueueServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\ContentDeficitQueueServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationAnalyticsServiceInterface;
// EDU-15 — AI Content Generation contracts
use SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationAiContentGenerationServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationDraftPersistenceServiceInterface;
// EDU-16 — Human Review Workflow contracts + implementations
use SaaSPlatform\Modules\Education\Contracts\Services\EducationDraftReviewServiceInterface;
use SaaSPlatform\Modules\Education\Services\Education\EducationDraftReviewService;

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
// EDU-14A — AI Education Engine implementations
use SaaSPlatform\Modules\Education\Services\Education\UserLearningProfileService;
use SaaSPlatform\Modules\Education\Services\Education\KnowledgeGapService;
use SaaSPlatform\Modules\Education\Services\Education\AIRecommendationScoreService;
use SaaSPlatform\Modules\Education\Services\Education\ContentReadinessService;
// EDU-14B — Intelligence Orchestration Layer implementations
use SaaSPlatform\Modules\Education\Services\Education\RecommendationRefreshService;
use SaaSPlatform\Modules\Education\Services\Education\KnowledgeGapQueueService;
use SaaSPlatform\Modules\Education\Services\Education\ContentDeficitQueueService;
use SaaSPlatform\Modules\Education\Services\Education\EducationAnalyticsService;
// EDU-15 — AI Content Generation implementations
use SaaSPlatform\Modules\Education\Ai\Providers\NullAiContentProvider;
use SaaSPlatform\Modules\Education\Ai\Providers\ClaudeAiContentProvider;
use SaaSPlatform\Modules\Education\Ai\Providers\OpenAiContentProvider;
// EDU-17 — Circuit-breaker decorator + Persian prompt builder + quota
use SaaSPlatform\CoreUtils\AI\Governance\TenantAiQuotaManager;
use SaaSPlatform\Modules\Education\Ai\Providers\CircuitBreakerAiContentProvider;
use SaaSPlatform\Modules\Education\Ai\Prompts\PersianEducationPromptBuilder;
use SaaSPlatform\Modules\Education\Services\Education\EducationAiContentGenerationService;
use SaaSPlatform\Modules\Education\Services\Education\GeneratedContentValidator;
use SaaSPlatform\Modules\Education\Services\Education\EducationDraftPersistenceService;

// Models & policies
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationLearningPath;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;
use SaaSPlatform\Modules\Education\Policies\EducationArticlePolicy;
use SaaSPlatform\Modules\Education\Policies\EducationLearningPathPolicy;
use SaaSPlatform\Modules\Education\Policies\EducationWalkthroughPolicy;

// Commands (EDU-14B)
use SaaSPlatform\Modules\Education\Console\Commands\RefreshRecommendationsCommand;
use SaaSPlatform\Modules\Education\Console\Commands\BuildGapQueueCommand;
use SaaSPlatform\Modules\Education\Console\Commands\BuildContentDeficitQueueCommand;
// Commands (EDU-15)
use SaaSPlatform\Modules\Education\Console\Commands\GenerateContentCommand;
use SaaSPlatform\Modules\Education\Console\Commands\GenerateGapContentCommand;
use SaaSPlatform\Modules\Education\Console\Commands\GenerateArticlesCommand;
use SaaSPlatform\Modules\Education\Console\Commands\GenerateWalkthroughsCommand;
// Generators (EDU-15)
use SaaSPlatform\Modules\Education\Generation\ArticleDraftGenerator;
use SaaSPlatform\Modules\Education\Generation\WalkthroughDraftGenerator;
use SaaSPlatform\Modules\Education\Generation\LearningPathDraftGenerator;
// Commands
use SaaSPlatform\Modules\Education\Console\Commands\MigrateKbToEducationCommand;
use SaaSPlatform\Modules\Education\Console\Commands\ValidateEducationCommand;
use SaaSPlatform\Modules\Education\Console\Commands\EducationCoverageReportCommand;
use SaaSPlatform\Modules\Education\Console\Commands\DiscoverFeaturesCommand;
use SaaSPlatform\Modules\Education\Console\Commands\GenerateDocumentationCommand;
use SaaSPlatform\Modules\Education\Console\Commands\GenerateRecommendationsCommand;
use SaaSPlatform\Modules\Education\Console\Commands\AdoptionReportCommand;
// Phase EDU-12 commands
use SaaSPlatform\Modules\Education\Console\Commands\SyncCustomerEducationCommand;
use SaaSPlatform\Modules\Education\Console\Commands\SyncAllCustomersEducationCommand;
use SaaSPlatform\Modules\Education\Console\Commands\VerifyEducationSyncCommand;
use SaaSPlatform\Modules\Education\Console\Commands\ListSitesCommand;

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

        // --- Service singletons (Phase EDU-12) ---
        $this->app->singleton(\SaaSPlatform\Modules\Education\Services\EducationWalkthroughGeneratorService::class);
        $this->app->singleton(\SaaSPlatform\Modules\Education\Services\EducationLearningPathGeneratorService::class);
        $this->app->singleton(\SaaSPlatform\Modules\Education\Services\EducationChangeDetectorService::class);
        $this->app->singleton(\SaaSPlatform\Modules\Education\Services\EducationSyncService::class);

        // --- Service singletons (Phase EDU-14A) ---
        $this->app->singleton(UserLearningProfileService::class);
        $this->app->singleton(KnowledgeGapService::class);
        $this->app->singleton(AIRecommendationScoreService::class);
        $this->app->singleton(ContentReadinessService::class);

        // --- Service singletons (Phase EDU-14B) ---
        $this->app->singleton(RecommendationRefreshService::class);
        $this->app->singleton(KnowledgeGapQueueService::class);
        $this->app->singleton(ContentDeficitQueueService::class);
        $this->app->singleton(EducationAnalyticsService::class);

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

        // --- Interface → concrete bindings (Phase EDU-14A) ---
        $this->app->bind(UserLearningProfileServiceInterface::class,  UserLearningProfileService::class);
        $this->app->bind(KnowledgeGapServiceInterface::class,          KnowledgeGapService::class);
        $this->app->bind(AIRecommendationScoreServiceInterface::class, AIRecommendationScoreService::class);
        $this->app->bind(ContentReadinessServiceInterface::class,      ContentReadinessService::class);

        // --- Interface → concrete bindings (Phase EDU-14B) ---
        $this->app->bind(RecommendationRefreshServiceInterface::class, RecommendationRefreshService::class);
        $this->app->bind(KnowledgeGapQueueServiceInterface::class,     KnowledgeGapQueueService::class);
        $this->app->bind(ContentDeficitQueueServiceInterface::class,   ContentDeficitQueueService::class);
        $this->app->bind(EducationAnalyticsServiceInterface::class,    EducationAnalyticsService::class);

        // --- EDU-17: Persian prompt builder ---
        $this->app->singleton(PersianEducationPromptBuilder::class);

        // --- EDU-17: AI Content Generation — provider + circuit-breaker wiring ---
        // Primary config key: education.ai.provider (EDU-17).
        // Falls back to legacy education.ai_provider key for backward compatibility.
        // Every provider is wrapped in CircuitBreakerAiContentProvider.
        $this->app->singleton(AiContentProviderInterface::class, function ($app) {
            $providerName  = config('education.ai.provider')
                          ?? config('education.ai_provider', 'null');
            $cfg           = config('education.ai_generation', []);
            $timeout       = (int) config('education.ai.timeout_seconds', 60);
            $promptBuilder = $app->make(PersianEducationPromptBuilder::class);

            $inner = match ($providerName) {
                'claude' => new ClaudeAiContentProvider(
                    apiKey:         $cfg['claude_api_key'] ?? '',
                    promptBuilder:  $promptBuilder,
                    model:          $cfg['claude_model']   ?? 'claude-haiku-4-5-20251001',
                    maxTokens:      (int) ($cfg['max_tokens'] ?? 2048),
                    timeoutSeconds: $timeout,
                ),
                'openai' => new OpenAiContentProvider(
                    apiKey:    $cfg['openai_api_key'] ?? '',
                    model:     $cfg['openai_model']   ?? 'gpt-4o',
                    maxTokens: (int) ($cfg['max_tokens'] ?? 2048),
                ),
                default => new NullAiContentProvider(),
            };

            $quotaManager = config('education.ai.quota.enabled', false)
                ? $app->make(TenantAiQuotaManager::class)
                : null;

            return new CircuitBreakerAiContentProvider($inner, $quotaManager);
        });

        $this->app->singleton(GeneratedContentValidator::class);
        $this->app->singleton(EducationAiContentGenerationService::class);
        $this->app->singleton(EducationDraftPersistenceService::class);

        $this->app->bind(EducationAiContentGenerationServiceInterface::class, EducationAiContentGenerationService::class);
        $this->app->bind(EducationDraftPersistenceServiceInterface::class,    EducationDraftPersistenceService::class);

        // --- EDU-16: Human Review Workflow ---
        $this->app->bind(EducationDraftReviewServiceInterface::class, EducationDraftReviewService::class);

        // --- EDU-15: Typed draft generators (each resolved with the shared provider) ---
        $this->app->singleton(ArticleDraftGenerator::class, function ($app) {
            return new ArticleDraftGenerator(
                $app->make(AiContentProviderInterface::class),
                $app->make(GeneratedContentValidator::class),
            );
        });
        $this->app->singleton(WalkthroughDraftGenerator::class, function ($app) {
            return new WalkthroughDraftGenerator(
                $app->make(AiContentProviderInterface::class),
                $app->make(GeneratedContentValidator::class),
            );
        });
        $this->app->singleton(LearningPathDraftGenerator::class, function ($app) {
            return new LearningPathDraftGenerator(
                $app->make(AiContentProviderInterface::class),
                $app->make(GeneratedContentValidator::class),
            );
        });
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
        Gate::policy(EducationWalkthrough::class,  EducationWalkthroughPolicy::class);
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
                // Phase EDU-12
                ListSitesCommand::class,
                SyncCustomerEducationCommand::class,
                SyncAllCustomersEducationCommand::class,
                VerifyEducationSyncCommand::class,
                // Phase EDU-14B — Intelligence Orchestration Layer
                RefreshRecommendationsCommand::class,
                BuildGapQueueCommand::class,
                BuildContentDeficitQueueCommand::class,
                // Phase EDU-15 — AI Content Generation
                GenerateContentCommand::class,
                GenerateGapContentCommand::class,
                GenerateArticlesCommand::class,
                GenerateWalkthroughsCommand::class,
            ]);
        }
    }

    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];
        // F-13: Register as a named alias only — applied explicitly on admin routes,
        // not pushed to the global 'web' group (which would run it on every public request).
        $router->aliasMiddleware('education.track', TrackFeatureUsageMiddleware::class);
    }

    protected function registerViewComposers(): void
    {
        // Inject $eduGuides, $walkthroughTriggers, $eduRecommendationCount into every admin.* view
        // AND into the layout so the walkthrough runner + recommendation badge render at layout level.
        View::composer(['admin.*', 'layouts.admin'], EducationGuideComposer::class);
    }
}
