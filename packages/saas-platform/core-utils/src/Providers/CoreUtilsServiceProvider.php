<?php

namespace SaaSPlatform\CoreUtils\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use SaaSPlatform\CoreUtils\Blade\AssetModuleDirective;
use SaaSPlatform\CoreUtils\Console\Commands\ModuleAssetsCheckCommand;
use SaaSPlatform\CoreUtils\Console\Commands\DriftScanCommand;
use SaaSPlatform\CoreUtils\Console\Commands\DriftBladesCommand;
use SaaSPlatform\CoreUtils\Console\Commands\DriftContractsCommand;
use SaaSPlatform\CoreUtils\Console\Commands\DriftControllersCommand;
use SaaSPlatform\CoreUtils\Console\Commands\DriftOwnershipCommand;
use SaaSPlatform\CoreUtils\Console\Commands\ValidateAuthorityCommand;
use SaaSPlatform\CoreUtils\Console\Commands\DriftAuthorityCommand;
use SaaSPlatform\CoreUtils\Console\Commands\ModuleVersionCommand;
use SaaSPlatform\CoreUtils\Console\Commands\ModuleBuildCommand;
use SaaSPlatform\CoreUtils\Console\Commands\ModuleReleaseCommand;
use SaaSPlatform\CoreUtils\Console\Commands\ModuleListCommand;
use SaaSPlatform\CoreUtils\Support\ModuleVersionRegistry;
use SaaSPlatform\CoreUtils\Support\ReleaseManifest;
use SaaSPlatform\CoreUtils\Services\ModuleUpdateOrchestrator;
use SaaSPlatform\CoreUtils\Services\PlatformCompatibilityService;
use SaaSPlatform\CoreUtils\Services\AssetSymlinkService;
use SaaSPlatform\CoreUtils\Services\AssetVersionService;
use SaaSPlatform\CoreUtils\Services\SupportSlaService;
use SaaSPlatform\CoreUtils\Services\PlatformContext;
use SaaSPlatform\CoreUtils\Services\CapabilityResolver;
use SaaSPlatform\CoreUtils\Services\LayoutResolver;
use SaaSPlatform\CoreUtils\Services\MenuResolver;
use SaaSPlatform\CoreUtils\Services\ModuleRegistryService;
use SaaSPlatform\CoreUtils\Services\SharedLayoutResolver;
use SaaSPlatform\CoreUtils\Services\ModuleNavigationService;
use SaaSPlatform\CoreUtils\Services\OverrideAuthorityValidator;
use SaaSPlatform\CoreUtils\Services\CanonicalFrontendValidationService;
use SaaSPlatform\CoreUtils\Services\CustomerPackageRuntimeValidator;
use SaaSPlatform\CoreUtils\Services\CanonicalUiParityValidator;
use SaaSPlatform\CoreUtils\Console\Commands\ValidateUiAuthorityCommand;
use SaaSPlatform\CoreUtils\Console\Commands\ValidateTenantRuntimeCommand;
use SaaSPlatform\CoreUtils\Console\Commands\ValidateDistributionCommand;
use SaaSPlatform\CoreUtils\Services\PackageDistributionValidationService;
// Phase 8.2 — Deployment services
use SaaSPlatform\CoreUtils\Services\Deployment\DeploymentOrchestrator;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseLockManager;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseManifestManager;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseManifestValidator;
use SaaSPlatform\CoreUtils\Services\Deployment\DeploymentTransactionLogger;
use SaaSPlatform\CoreUtils\Services\Deployment\DeploymentHealthVerifier;
use SaaSPlatform\CoreUtils\Services\Migration\MigrationSafetyService;
use SaaSPlatform\CoreUtils\Services\Migration\SchemaDriftDetector;
use SaaSPlatform\CoreUtils\Services\Migration\TenantMigrationCoordinator;
use SaaSPlatform\CoreUtils\Services\Maintenance\TenantMaintenanceManager;
use SaaSPlatform\CoreUtils\Services\Queue\QueueRuntimeCompatibilityService;
use SaaSPlatform\CoreUtils\Services\Assets\AssetVersionValidator;
use SaaSPlatform\CoreUtils\Services\Health\RuntimeHealthService;
use SaaSPlatform\CoreUtils\Services\Rollback\RollbackCoordinator;
use SaaSPlatform\CoreUtils\Services\Rollback\ReleaseRollbackValidator;
use SaaSPlatform\CoreUtils\Services\Rollback\FailedDeploymentRecoveryService;
use SaaSPlatform\CoreUtils\Services\DeploymentGovernanceValidationService;
// Phase 8.3 — Telemetry
use SaaSPlatform\CoreUtils\Telemetry\TelemetryContext;
use SaaSPlatform\CoreUtils\Telemetry\TelemetrySanitizer;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryPayloadFactory;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryEventBus;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryStorageManager;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use SaaSPlatform\CoreUtils\Telemetry\ObservabilityGovernanceValidationService;
use SaaSPlatform\CoreUtils\Telemetry\Tracing\RequestTraceManager;
use SaaSPlatform\CoreUtils\Telemetry\Tracing\TraceContextResolver;
use SaaSPlatform\CoreUtils\Telemetry\Tracing\RuntimeRequestProfiler;
use SaaSPlatform\CoreUtils\Telemetry\Logging\StructuredLogger;
use SaaSPlatform\CoreUtils\Telemetry\Logging\TenantAwareLogChannel;
use SaaSPlatform\CoreUtils\Telemetry\Logging\RuntimeDiagnosticLogger;
use SaaSPlatform\CoreUtils\Telemetry\Logging\SecurityEventLogger;
use SaaSPlatform\CoreUtils\Telemetry\Logging\DeploymentEventLogger;
use SaaSPlatform\CoreUtils\Telemetry\Anomaly\QueueAnomalyDetector;
use SaaSPlatform\CoreUtils\Telemetry\Anomaly\TenantIsolationAnomalyDetector;
use SaaSPlatform\CoreUtils\Telemetry\Anomaly\DeploymentFailureDetector;
use SaaSPlatform\CoreUtils\Telemetry\Anomaly\SecurityAnomalyDetector;
use SaaSPlatform\CoreUtils\Telemetry\Anomaly\RuntimeAnomalyEngine;
use SaaSPlatform\CoreUtils\Telemetry\Queue\QueueTelemetryService;
use SaaSPlatform\CoreUtils\Telemetry\Queue\WorkerHealthTracker;
use SaaSPlatform\CoreUtils\Telemetry\Queue\FailedJobDiagnosticsService;
use SaaSPlatform\CoreUtils\Telemetry\Queue\QueuePerformanceAnalyzer;
use SaaSPlatform\CoreUtils\Telemetry\Deployment\ReleaseAnalyticsService;
use SaaSPlatform\CoreUtils\Telemetry\Deployment\DeploymentDiagnosticsService;
use SaaSPlatform\CoreUtils\Telemetry\Deployment\ReleaseFailureIntelligence;
use SaaSPlatform\CoreUtils\Telemetry\Deployment\RollbackImpactAnalyzer;
use SaaSPlatform\CoreUtils\Telemetry\Tenant\TenantRuntimeMetrics;
use SaaSPlatform\CoreUtils\Telemetry\Tenant\TenantIncidentTracker;
use SaaSPlatform\CoreUtils\Telemetry\Tenant\TenantUsageTelemetry;
use SaaSPlatform\CoreUtils\Telemetry\Tenant\TenantObservabilityService;
use SaaSPlatform\CoreUtils\Telemetry\Security\AuthFailureTelemetry;
use SaaSPlatform\CoreUtils\Telemetry\Security\TenantBoundaryViolationTelemetry;
use SaaSPlatform\CoreUtils\Telemetry\Security\SensitiveOperationAuditStream;
use SaaSPlatform\CoreUtils\Telemetry\Security\SecurityTelemetryService;
use SaaSPlatform\CoreUtils\Telemetry\Metrics\RuntimeMetricsRepository;
use SaaSPlatform\CoreUtils\Telemetry\Metrics\DeploymentMetricsRepository;
use SaaSPlatform\CoreUtils\Telemetry\Metrics\QueueMetricsRepository;
use SaaSPlatform\CoreUtils\Telemetry\Metrics\TenantMetricsRepository;
use SaaSPlatform\CoreUtils\Telemetry\Metrics\OperationalMetricsAggregator;
use SaaSPlatform\CoreUtils\Telemetry\Incident\RuntimeTimelineBuilder;
use SaaSPlatform\CoreUtils\Telemetry\Incident\TraceReplayService;
use SaaSPlatform\CoreUtils\Telemetry\Incident\IncidentReconstructionService;
// Phase 8.3 — Commands
use SaaSPlatform\CoreUtils\Console\Commands\Telemetry\QueueTelemetryCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Telemetry\QueueDiagnosticsCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Telemetry\ValidateObservabilityCommand;
// Phase 8.4 — Runtime Governance core
use SaaSPlatform\CoreUtils\RuntimeGovernance\GovernanceContext;
use SaaSPlatform\CoreUtils\RuntimeGovernance\GovernancePolicyRegistry;
use SaaSPlatform\CoreUtils\RuntimeGovernance\RuntimeDecisionEngine;
use SaaSPlatform\CoreUtils\RuntimeGovernance\RuntimeStateCoordinator;
use SaaSPlatform\CoreUtils\RuntimeGovernance\GovernanceExecutionPipeline;
use SaaSPlatform\CoreUtils\RuntimeGovernance\RuntimeGovernanceKernel;
use SaaSPlatform\CoreUtils\RuntimeGovernance\GovernanceRuntimeValidationService;
// Phase 8.4 — SelfHealing
use SaaSPlatform\CoreUtils\RuntimeGovernance\SelfHealing\FailureClassificationEngine;
use SaaSPlatform\CoreUtils\RuntimeGovernance\SelfHealing\RecoveryExecutionLog;
use SaaSPlatform\CoreUtils\RuntimeGovernance\SelfHealing\RecoveryStrategyManager;
use SaaSPlatform\CoreUtils\RuntimeGovernance\SelfHealing\RuntimeRecoveryCoordinator;
use SaaSPlatform\CoreUtils\RuntimeGovernance\SelfHealing\SelfHealingEngine;
// Phase 8.4 — CircuitBreaker
use SaaSPlatform\CoreUtils\RuntimeGovernance\CircuitBreaker\CircuitHealthTracker;
use SaaSPlatform\CoreUtils\RuntimeGovernance\CircuitBreaker\CircuitBreakerManager;
use SaaSPlatform\CoreUtils\RuntimeGovernance\CircuitBreaker\TenantCircuitRegistry;
use SaaSPlatform\CoreUtils\RuntimeGovernance\CircuitBreaker\ModuleCircuitRegistry;
// Phase 8.4 — Throttle
use SaaSPlatform\CoreUtils\RuntimeGovernance\Throttle\TenantLoadGovernor;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Throttle\QueuePressureController;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Throttle\RuntimeThrottleManager;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Throttle\RequestRateRegulator;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Throttle\RuntimeBackpressureCoordinator;
// Phase 8.4 — Degradation
use SaaSPlatform\CoreUtils\RuntimeGovernance\Degradation\TenantDegradationManager;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Degradation\TenantIsolationPolicy;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Degradation\RuntimeFeatureDegrader;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Degradation\TenantProtectionCoordinator;
// Phase 8.4 — CascadeProtection
use SaaSPlatform\CoreUtils\RuntimeGovernance\CascadeProtection\DependencyImpactGraph;
use SaaSPlatform\CoreUtils\RuntimeGovernance\CascadeProtection\RuntimeBlastRadiusAnalyzer;
use SaaSPlatform\CoreUtils\RuntimeGovernance\CascadeProtection\FailureContainmentCoordinator;
use SaaSPlatform\CoreUtils\RuntimeGovernance\CascadeProtection\CascadingFailureDetector;
// Phase 8.4 — Queue governance
use SaaSPlatform\CoreUtils\RuntimeGovernance\Queue\QueueStallDetector;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Queue\WorkerCrashAnalyzer;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Queue\DeadLetterGovernanceService;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Queue\QueueRuntimeBalancer;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Queue\QueueAutoRecoveryManager;
// Phase 8.4 — Policy
use SaaSPlatform\CoreUtils\RuntimeGovernance\Policy\PolicyConditionEvaluator;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Policy\GovernanceRuleCompiler;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Policy\RuntimePolicyExecutor;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Policy\RuntimePolicyEngine;
// Phase 8.4 — Incident
use SaaSPlatform\CoreUtils\RuntimeGovernance\Incident\IncidentSeverityClassifier;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Incident\RuntimeIncidentTimeline;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Incident\AutomatedMitigationExecutor;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Incident\IncidentResolutionTracker;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Incident\IncidentResponseCoordinator;
// Phase 8.4 — Governance Metrics
use SaaSPlatform\CoreUtils\RuntimeGovernance\Metrics\RuntimeGovernanceSnapshot;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Metrics\RuntimeGovernanceMetrics;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Metrics\GovernanceStatusAggregator;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Metrics\RuntimeStabilityIndexCalculator;
// Phase 8.4 — Commands
use SaaSPlatform\CoreUtils\Console\Commands\Governance\ValidateRuntimeCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Governance\RuntimeStatusCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Governance\SimulateFailureCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Governance\TestRecoveryCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Governance\ListPoliciesCommand;
// Phase 8.5 — Runtime Isolation
use SaaSPlatform\CoreUtils\RuntimeIsolation\TenantCapacityProfile;
use SaaSPlatform\CoreUtils\RuntimeIsolation\TenantQuotaManager;
use SaaSPlatform\CoreUtils\RuntimeIsolation\TenantRuntimeBudget;
use SaaSPlatform\CoreUtils\RuntimeIsolation\TenantConsumptionTracker;
use SaaSPlatform\CoreUtils\RuntimeIsolation\TenantResourceGovernor;
use SaaSPlatform\CoreUtils\RuntimeIsolation\RuntimeIsolationCoordinator;
use SaaSPlatform\CoreUtils\RuntimeIsolation\EnforcementResult;
// Phase 8.5 — Noisy Neighbor
use SaaSPlatform\CoreUtils\RuntimeIsolation\NoisyNeighbor\TenantPressureAnalyzer;
use SaaSPlatform\CoreUtils\RuntimeIsolation\NoisyNeighbor\NoisyNeighborDetector;
use SaaSPlatform\CoreUtils\RuntimeIsolation\NoisyNeighbor\TenantPenaltyEngine;
use SaaSPlatform\CoreUtils\RuntimeIsolation\NoisyNeighbor\AdaptiveTenantThrottler;
use SaaSPlatform\CoreUtils\RuntimeIsolation\NoisyNeighbor\RuntimeFairnessCoordinator;
// Phase 8.5 — Distributed Queue
use SaaSPlatform\CoreUtils\DistributedQueue\QueuePartitionRegistry;
use SaaSPlatform\CoreUtils\DistributedQueue\TenantQueueAffinityResolver;
use SaaSPlatform\CoreUtils\DistributedQueue\QueueShardManager;
use SaaSPlatform\CoreUtils\DistributedQueue\RuntimeQueueBalancer;
use SaaSPlatform\CoreUtils\DistributedQueue\DistributedQueueCoordinator;
// Phase 8.5 — Capacity Governance
use SaaSPlatform\CoreUtils\CapacityGovernance\CapacityThresholdManager;
use SaaSPlatform\CoreUtils\CapacityGovernance\CapacityForecastEngine;
use SaaSPlatform\CoreUtils\CapacityGovernance\RuntimePressurePredictor;
use SaaSPlatform\CoreUtils\CapacityGovernance\RuntimeScalingAdvisor;
use SaaSPlatform\CoreUtils\CapacityGovernance\RuntimeCapacityPlanner;
// Phase 8.5 — Node Coordination
use SaaSPlatform\CoreUtils\NodeCoordination\NodeRegistry;
use SaaSPlatform\CoreUtils\NodeCoordination\NodeHealthSnapshot;
use SaaSPlatform\CoreUtils\NodeCoordination\NodeCapacityTracker;
use SaaSPlatform\CoreUtils\NodeCoordination\DistributedRuntimeMap;
use SaaSPlatform\CoreUtils\NodeCoordination\RuntimeNodeCoordinator;
// Phase 8.5 — Cache Governance
use SaaSPlatform\CoreUtils\CacheGovernance\TenantCacheNamespaceManager;
use SaaSPlatform\CoreUtils\CacheGovernance\RuntimeCachePartitioner;
use SaaSPlatform\CoreUtils\CacheGovernance\TenantCachePressureMonitor;
use SaaSPlatform\CoreUtils\CacheGovernance\CacheEvictionCoordinator;
use SaaSPlatform\CoreUtils\CacheGovernance\DistributedCacheIsolation;
// Phase 8.5 — Storage Governance
use SaaSPlatform\CoreUtils\StorageGovernance\RuntimeStorageQuotaService;
use SaaSPlatform\CoreUtils\StorageGovernance\AttachmentAccessTelemetry;
use SaaSPlatform\CoreUtils\StorageGovernance\AttachmentIsolationManager;
use SaaSPlatform\CoreUtils\StorageGovernance\StoragePressureAnalyzer;
use SaaSPlatform\CoreUtils\StorageGovernance\TenantStorageGovernor;
// Phase 8.5 — Runtime Scheduling
use SaaSPlatform\CoreUtils\RuntimeScheduling\RuntimeWorkloadClassifier;
use SaaSPlatform\CoreUtils\RuntimeScheduling\TenantPriorityScheduler;
use SaaSPlatform\CoreUtils\RuntimeScheduling\DeferredExecutionCoordinator;
use SaaSPlatform\CoreUtils\RuntimeScheduling\GovernanceAwareDispatcher;
use SaaSPlatform\CoreUtils\RuntimeScheduling\RuntimeScheduler;
// Phase 8.5 — Scalability
use SaaSPlatform\CoreUtils\Scalability\TenantStressSimulator;
use SaaSPlatform\CoreUtils\Scalability\QueuePressureSimulator;
use SaaSPlatform\CoreUtils\Scalability\RuntimeLoadSimulationEngine;
use SaaSPlatform\CoreUtils\Scalability\CapacityValidationReport;
use SaaSPlatform\CoreUtils\Scalability\ScalabilityValidationService;
// Phase 8.5 — Scalability Metrics
use SaaSPlatform\CoreUtils\Scalability\Metrics\RuntimeCapacitySnapshot;
use SaaSPlatform\CoreUtils\Scalability\Metrics\TenantIsolationMetrics;
use SaaSPlatform\CoreUtils\Scalability\Metrics\DistributedRuntimeMetrics;
use SaaSPlatform\CoreUtils\Scalability\Metrics\QueueScalabilityMetrics;
use SaaSPlatform\CoreUtils\Scalability\Metrics\ResourcePressureIndex;
// Phase 8.5 — Commands
use SaaSPlatform\CoreUtils\Console\Commands\Scalability\ValidateScalabilityCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Scalability\SimulateLoadCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Scalability\TestIsolationCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Scalability\ForecastCapacityCommand;
// Phase 8.6 — Plugin Governance
use SaaSPlatform\CoreUtils\PluginGovernance\CapabilityDefinition;
use SaaSPlatform\CoreUtils\PluginGovernance\ModuleCapabilityRegistry;
use SaaSPlatform\CoreUtils\PluginGovernance\CapabilityGrantManager;
use SaaSPlatform\CoreUtils\PluginGovernance\CapabilityResolutionEngine;
use SaaSPlatform\CoreUtils\PluginGovernance\ModulePermissionProfile;
use SaaSPlatform\CoreUtils\PluginGovernance\RuntimeCapabilityEnforcer;
// Phase 8.6 — Sandbox
use SaaSPlatform\CoreUtils\PluginGovernance\Sandbox\SandboxRuntimeManager;
use SaaSPlatform\CoreUtils\PluginGovernance\Sandbox\RuntimeSandboxBoundary;
use SaaSPlatform\CoreUtils\PluginGovernance\Sandbox\ModuleResourceLimiter;
use SaaSPlatform\CoreUtils\PluginGovernance\Sandbox\IsolatedExecutionCoordinator;
use SaaSPlatform\CoreUtils\PluginGovernance\Sandbox\PluginRuntimeContainer;
// Phase 8.6 — Dependency Scanning
use SaaSPlatform\CoreUtils\PluginGovernance\DependencyScanning\PluginDependencyScanner;
use SaaSPlatform\CoreUtils\PluginGovernance\DependencyScanning\RuntimeDependencyValidator;
use SaaSPlatform\CoreUtils\PluginGovernance\DependencyScanning\UnsafeImportDetector;
use SaaSPlatform\CoreUtils\PluginGovernance\DependencyScanning\SandboxCompatibilityAnalyzer;
use SaaSPlatform\CoreUtils\PluginGovernance\DependencyScanning\DependencyRiskClassifier;
// Phase 8.6 — Lifecycle
use SaaSPlatform\CoreUtils\PluginGovernance\Lifecycle\PluginLifecycleManager;
use SaaSPlatform\CoreUtils\PluginGovernance\Lifecycle\PluginActivationCoordinator;
use SaaSPlatform\CoreUtils\PluginGovernance\Lifecycle\PluginHealthMonitor;
use SaaSPlatform\CoreUtils\PluginGovernance\Lifecycle\PluginUpgradeValidator;
use SaaSPlatform\CoreUtils\PluginGovernance\Lifecycle\PluginRollbackCoordinator;
// Phase 8.6 — Trust
use SaaSPlatform\CoreUtils\PluginGovernance\Trust\PluginSignatureVerifier;
use SaaSPlatform\CoreUtils\PluginGovernance\Trust\ModuleTrustRegistry;
use SaaSPlatform\CoreUtils\PluginGovernance\Trust\TrustedPublisherRegistry;
use SaaSPlatform\CoreUtils\PluginGovernance\Trust\PluginIntegrityValidator;
use SaaSPlatform\CoreUtils\PluginGovernance\Trust\ExtensionAuthenticityService;
// Phase 8.6 — Containment
use SaaSPlatform\CoreUtils\PluginGovernance\Containment\PluginContainmentManager;
use SaaSPlatform\CoreUtils\PluginGovernance\Containment\RuntimeViolationDetector;
use SaaSPlatform\CoreUtils\PluginGovernance\Containment\ExtensionQuarantineService;
use SaaSPlatform\CoreUtils\PluginGovernance\Containment\UnsafeExecutionTerminator;
use SaaSPlatform\CoreUtils\PluginGovernance\Containment\SandboxViolationTelemetry;
// Phase 8.6 — Resource Governance
use SaaSPlatform\CoreUtils\PluginGovernance\ResourceGovernance\PluginQuotaManager;
use SaaSPlatform\CoreUtils\PluginGovernance\ResourceGovernance\ExtensionRuntimePressureAnalyzer;
use SaaSPlatform\CoreUtils\PluginGovernance\ResourceGovernance\PluginResourceBudget;
use SaaSPlatform\CoreUtils\PluginGovernance\ResourceGovernance\PluginFairnessCoordinator;
use SaaSPlatform\CoreUtils\PluginGovernance\ResourceGovernance\SandboxLoadBalancer;
// Phase 8.6 — Marketplace
use SaaSPlatform\CoreUtils\PluginGovernance\Marketplace\MarketplaceGovernanceValidator;
use SaaSPlatform\CoreUtils\PluginGovernance\Marketplace\RuntimeMarketplacePolicy;
// Phase 8.6 — Metrics
use SaaSPlatform\CoreUtils\PluginGovernance\Metrics\PluginRuntimeMetrics;
// Phase 8.6 — Commands
use SaaSPlatform\CoreUtils\Console\Commands\Plugins\ValidateSandboxCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Plugins\ScanDependenciesCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Plugins\TestIsolationCommand as PluginTestIsolationCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Plugins\VerifySignaturesCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Plugins\PluginRuntimeStatusCommand;
// Phase 9.4 — Realtime Collaboration
use SaaSPlatform\CoreUtils\Realtime\RealtimePresenceService;
use SaaSPlatform\CoreUtils\Realtime\UserPresenceTracker;
use SaaSPlatform\CoreUtils\Realtime\CollaborativeEditingCoordinator;
use SaaSPlatform\CoreUtils\Realtime\LiveActivityBroadcastService;
use SaaSPlatform\CoreUtils\Realtime\RealtimeNotificationHub;
use SaaSPlatform\CoreUtils\Realtime\ConnectionHealthMonitor;
// Phase 9.4 — AI Layer (base)
use SaaSPlatform\CoreUtils\AI\AiPromptRegistry;
use SaaSPlatform\CoreUtils\AI\AiSuggestionEngine;
use SaaSPlatform\CoreUtils\AI\AiFormBuilderAssistant;
use SaaSPlatform\CoreUtils\AI\AiSupportReplyAssistant;
use SaaSPlatform\CoreUtils\AI\ContextAwareRecommendationService;
use SaaSPlatform\CoreUtils\AI\SmartAutomationAdvisor;
// Phase 9.5-A — AI Provider Runtime
use SaaSPlatform\CoreUtils\AI\Providers\OpenAIProvider;
use SaaSPlatform\CoreUtils\AI\Providers\ClaudeProvider;
use SaaSPlatform\CoreUtils\AI\AiProviderManager;
use SaaSPlatform\CoreUtils\AI\AiRuntimeCoordinator;
use SaaSPlatform\CoreUtils\AI\AiCapabilityRegistry;
use SaaSPlatform\CoreUtils\AI\AiProviderHealthMonitor;
use SaaSPlatform\CoreUtils\AI\AiFallbackCoordinator;
// Phase 9.5-A — Prompt Governance
use SaaSPlatform\CoreUtils\AI\Governance\SensitiveDataRedactor;
use SaaSPlatform\CoreUtils\AI\Governance\PromptInjectionDetector;
use SaaSPlatform\CoreUtils\AI\Governance\AiPromptAuditLogger;
use SaaSPlatform\CoreUtils\AI\Governance\PromptSecuritySanitizer;
use SaaSPlatform\CoreUtils\AI\Governance\TenantSafePromptBuilder;
use SaaSPlatform\CoreUtils\AI\Governance\PromptTemplateRegistry;
use SaaSPlatform\CoreUtils\AI\Governance\TenantAiQuotaManager;
use SaaSPlatform\CoreUtils\AI\Governance\AiRateLimitCoordinator;
use SaaSPlatform\CoreUtils\AI\Governance\AiCostEstimator;
use SaaSPlatform\CoreUtils\AI\Governance\AiRequestBudgetManager;
use SaaSPlatform\CoreUtils\AI\Governance\AiOperationPolicyEngine;
use SaaSPlatform\CoreUtils\AI\Governance\AiUsageGovernor;
use SaaSPlatform\CoreUtils\AI\Governance\AiGovernanceValidationService;
// Phase 9.5-A — AI Observability
use SaaSPlatform\CoreUtils\AI\Observability\AiTelemetryService;
use SaaSPlatform\CoreUtils\AI\Observability\AiInteractionTimeline;
use SaaSPlatform\CoreUtils\AI\Observability\AiDecisionAuditLog;
use SaaSPlatform\CoreUtils\AI\Observability\AiProviderDiagnostics;
use SaaSPlatform\CoreUtils\AI\Observability\AiFailureAnalytics;
use SaaSPlatform\CoreUtils\AI\Observability\AiSuggestionEffectivenessAnalyzer;
// Phase 9.5-A — AI Commands
use SaaSPlatform\CoreUtils\Console\Commands\AI\ValidateAiGovernanceCommand;
use SaaSPlatform\CoreUtils\Console\Commands\AI\TestAiProviderCommand;
use SaaSPlatform\CoreUtils\Console\Commands\AI\AiDiagnosticsCommand;
use SaaSPlatform\CoreUtils\Console\Commands\AI\AiQuotaReportCommand;
// Phase 9.4 — Search & Command Center
use SaaSPlatform\CoreUtils\Search\GlobalSearchEngine;
use SaaSPlatform\CoreUtils\Search\SmartCommandPalette;
use SaaSPlatform\CoreUtils\Search\SearchTelemetryService;
use SaaSPlatform\CoreUtils\Search\ContextualSearchIndex;
use SaaSPlatform\CoreUtils\Search\RuntimeQuickActionsService;
// Phase 9.4 — Enterprise Governance
use SaaSPlatform\CoreUtils\Enterprise\EnterpriseProductExperienceService;
use SaaSPlatform\CoreUtils\Enterprise\EnterpriseRuntimeGovernanceValidator;
// Phase 9.4 — Interaction Recovery
use SaaSPlatform\CoreUtils\InteractionRecovery\InteractionRecoveryEngine;
// Phase 9.4 — Security Advisory
use SaaSPlatform\CoreUtils\Security\SecurityAdvisoryService;
// Phase 9.4 — Analytics
use SaaSPlatform\CoreUtils\Analytics\OperationalDashboardService;
// Phase 9.4 — Commands
use SaaSPlatform\CoreUtils\Console\Commands\Enterprise\ValidateProductExperienceCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Enterprise\ValidateEnterpriseGovernanceCommand;
// Phase 8.2 — Commands
use SaaSPlatform\CoreUtils\Console\Commands\Deployment\DeploymentLockCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Deployment\DeploymentUnlockCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Deployment\DeploymentStatusCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Deployment\DeploymentRollbackCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Deployment\ValidateGovernanceCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Migration\MigrationValidateSafetyCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Migration\MigrationDetectDriftCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Maintenance\TenantDownCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Maintenance\TenantUpCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Maintenance\TenantMaintenanceStatusCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Queue\QueueValidateRuntimeCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Queue\QueueDrainSafeCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Health\PlatformHealthCommand;

class CoreUtilsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register SupportSlaService
        $this->app->singleton(SupportSlaService::class);

        // Register AssetSymlinkService
        $this->app->singleton(AssetSymlinkService::class, function () {
            return new AssetSymlinkService();
        });

        // Register AssetVersionService
        $this->app->singleton(AssetVersionService::class, function () {
            return new AssetVersionService();
        });

        // PHASE 6.95 — TASK 1: Register PlatformContext as singleton
        $this->app->singleton(PlatformContext::class, function ($app) {
            $tenantContext = null;
            if ($app->bound(\SaaSPlatform\CoreUtils\Contracts\TenantContextContract::class)) {
                $tenantContext = $app->make(\SaaSPlatform\CoreUtils\Contracts\TenantContextContract::class);
            }
            return new PlatformContext($tenantContext);
        });

        // PHASE 6.95 — TASK 3: Register CapabilityResolver
        $this->app->singleton(CapabilityResolver::class);

        // PHASE 6.95 — TASK 7: Register LayoutResolver
        $this->app->singleton(LayoutResolver::class);

        // PHASE 6.95 — TASK 8: Register MenuResolver
        $this->app->singleton(MenuResolver::class);

        // PHASE 6.95 — TASK 9: Register ModuleRegistryService
        $this->app->singleton(ModuleRegistryService::class);

        // PHASE 7.15 — TASK 2: SharedLayoutResolver (module-agnostic, replaces LayoutResolver)
        $this->app->singleton(SharedLayoutResolver::class);

        // PHASE 7.15 — TASK 5: ModuleNavigationService
        $this->app->singleton(ModuleNavigationService::class);

        // PHASE 7.15 — TASK 7: OverrideAuthorityValidator
        $this->app->singleton(OverrideAuthorityValidator::class);

        // PHASE 7.15 — TASK 8: CanonicalFrontendValidationService
        $this->app->singleton(CanonicalFrontendValidationService::class);

        // PHASE 7.25 — TASK 3+6: Tenant runtime validators
        $this->app->singleton(CustomerPackageRuntimeValidator::class);
        $this->app->singleton(CanonicalUiParityValidator::class);

        // PHASE 6.98 — TASK 1: Register ModuleVersionRegistry
        $this->app->singleton(ModuleVersionRegistry::class);

        // PHASE 6.98 — TASK 3: Register ReleaseManifest
        $this->app->singleton(ReleaseManifest::class);

        // PHASE 6.98 — TASK 8: Register PlatformCompatibilityService
        $this->app->singleton(PlatformCompatibilityService::class);

        // PHASE 8.1 — TASK 9: Distribution validation service
        $this->app->singleton(PackageDistributionValidationService::class);

        // PHASE 8.2 — Deployment subsystem singletons
        $this->app->singleton(ReleaseLockManager::class);
        $this->app->singleton(ReleaseManifestManager::class);
        $this->app->singleton(ReleaseManifestValidator::class);
        $this->app->singleton(DeploymentTransactionLogger::class);
        $this->app->singleton(DeploymentHealthVerifier::class);
        $this->app->singleton(DeploymentOrchestrator::class);
        $this->app->singleton(MigrationSafetyService::class);
        $this->app->singleton(SchemaDriftDetector::class);
        $this->app->singleton(TenantMigrationCoordinator::class);
        $this->app->singleton(TenantMaintenanceManager::class);
        $this->app->singleton(QueueRuntimeCompatibilityService::class);
        $this->app->singleton(AssetVersionValidator::class);
        $this->app->singleton(RuntimeHealthService::class);
        $this->app->singleton(ReleaseRollbackValidator::class);
        $this->app->singleton(RollbackCoordinator::class);
        $this->app->singleton(FailedDeploymentRecoveryService::class);
        $this->app->singleton(DeploymentGovernanceValidationService::class);

        // PHASE 8.3 — Telemetry core (order matters: Context → Sanitizer → Factory → Bus → Storage → Manager)
        $this->app->singleton(TelemetryContext::class);
        $this->app->singleton(TelemetrySanitizer::class);
        $this->app->singleton(TelemetryEventBus::class);
        $this->app->singleton(TelemetryStorageManager::class);
        $this->app->singleton(TelemetryPayloadFactory::class);
        $this->app->singleton(TelemetryManager::class);

        // PHASE 8.3 — Tracing
        $this->app->singleton(RequestTraceManager::class);
        $this->app->singleton(TraceContextResolver::class);
        $this->app->singleton(RuntimeRequestProfiler::class);

        // PHASE 8.3 — Logging
        $this->app->singleton(StructuredLogger::class);
        $this->app->singleton(TenantAwareLogChannel::class);
        $this->app->singleton(RuntimeDiagnosticLogger::class);
        $this->app->singleton(SecurityEventLogger::class);
        $this->app->singleton(DeploymentEventLogger::class);

        // PHASE 8.3 — Anomaly detectors (leaf classes first, then engine)
        $this->app->singleton(QueueAnomalyDetector::class);
        $this->app->singleton(TenantIsolationAnomalyDetector::class);
        $this->app->singleton(DeploymentFailureDetector::class);
        $this->app->singleton(SecurityAnomalyDetector::class);
        $this->app->singleton(RuntimeAnomalyEngine::class);

        // PHASE 8.3 — Queue observability
        $this->app->singleton(QueueTelemetryService::class);
        $this->app->singleton(WorkerHealthTracker::class);
        $this->app->singleton(FailedJobDiagnosticsService::class);
        $this->app->singleton(QueuePerformanceAnalyzer::class);

        // PHASE 8.3 — Deployment analytics
        $this->app->singleton(ReleaseAnalyticsService::class);
        $this->app->singleton(DeploymentDiagnosticsService::class);
        $this->app->singleton(ReleaseFailureIntelligence::class);
        $this->app->singleton(RollbackImpactAnalyzer::class);

        // PHASE 8.3 — Tenant observability (leaf classes first)
        $this->app->singleton(TenantRuntimeMetrics::class);
        $this->app->singleton(TenantIncidentTracker::class);
        $this->app->singleton(TenantUsageTelemetry::class);
        $this->app->singleton(TenantObservabilityService::class);

        // PHASE 8.3 — Security telemetry (leaf classes first)
        $this->app->singleton(AuthFailureTelemetry::class);
        $this->app->singleton(TenantBoundaryViolationTelemetry::class);
        $this->app->singleton(SensitiveOperationAuditStream::class);
        $this->app->singleton(SecurityTelemetryService::class);

        // PHASE 8.3 — Metrics repositories
        $this->app->singleton(RuntimeMetricsRepository::class);
        $this->app->singleton(DeploymentMetricsRepository::class);
        $this->app->singleton(QueueMetricsRepository::class);
        $this->app->singleton(TenantMetricsRepository::class);
        $this->app->singleton(OperationalMetricsAggregator::class);

        // PHASE 8.3 — Incident reconstruction
        $this->app->singleton(RuntimeTimelineBuilder::class);
        $this->app->singleton(TraceReplayService::class);
        $this->app->singleton(IncidentReconstructionService::class);

        // PHASE 8.3 — Validation
        $this->app->singleton(ObservabilityGovernanceValidationService::class);

        // PHASE 8.4 — Runtime Governance core
        $this->app->singleton(GovernanceContext::class);
        $this->app->singleton(GovernancePolicyRegistry::class);
        $this->app->singleton(RuntimeDecisionEngine::class);
        $this->app->singleton(RuntimeStateCoordinator::class);
        $this->app->singleton(GovernanceExecutionPipeline::class);
        $this->app->singleton(RuntimeGovernanceKernel::class);

        // PHASE 8.4 — SelfHealing
        $this->app->singleton(FailureClassificationEngine::class);
        $this->app->singleton(RecoveryExecutionLog::class);
        $this->app->singleton(RecoveryStrategyManager::class);
        $this->app->singleton(RuntimeRecoveryCoordinator::class);
        $this->app->singleton(SelfHealingEngine::class);

        // PHASE 8.4 — CircuitBreaker
        $this->app->singleton(CircuitHealthTracker::class);
        $this->app->singleton(CircuitBreakerManager::class);
        $this->app->singleton(TenantCircuitRegistry::class);
        $this->app->singleton(ModuleCircuitRegistry::class);

        // PHASE 8.4 — Throttle
        $this->app->singleton(TenantLoadGovernor::class);
        $this->app->singleton(QueuePressureController::class);
        $this->app->singleton(RuntimeThrottleManager::class);
        $this->app->singleton(RequestRateRegulator::class);
        $this->app->singleton(RuntimeBackpressureCoordinator::class);

        // PHASE 8.4 — Degradation
        $this->app->singleton(TenantDegradationManager::class);
        $this->app->singleton(TenantIsolationPolicy::class);
        $this->app->singleton(RuntimeFeatureDegrader::class);
        $this->app->singleton(TenantProtectionCoordinator::class);

        // PHASE 8.4 — CascadeProtection
        $this->app->singleton(DependencyImpactGraph::class);
        $this->app->singleton(RuntimeBlastRadiusAnalyzer::class);
        $this->app->singleton(FailureContainmentCoordinator::class);
        $this->app->singleton(CascadingFailureDetector::class);

        // PHASE 8.4 — Queue governance
        $this->app->singleton(QueueStallDetector::class);
        $this->app->singleton(WorkerCrashAnalyzer::class);
        $this->app->singleton(DeadLetterGovernanceService::class);
        $this->app->singleton(QueueRuntimeBalancer::class);
        $this->app->singleton(QueueAutoRecoveryManager::class);

        // PHASE 8.4 — Policy
        $this->app->singleton(PolicyConditionEvaluator::class);
        $this->app->singleton(GovernanceRuleCompiler::class);
        $this->app->singleton(RuntimePolicyExecutor::class);
        $this->app->singleton(RuntimePolicyEngine::class);

        // PHASE 8.4 — Incident
        $this->app->singleton(IncidentSeverityClassifier::class);
        $this->app->singleton(RuntimeIncidentTimeline::class);
        $this->app->singleton(AutomatedMitigationExecutor::class);
        $this->app->singleton(IncidentResolutionTracker::class);
        $this->app->singleton(IncidentResponseCoordinator::class);

        // PHASE 8.4 — Governance Metrics
        $this->app->singleton(RuntimeGovernanceSnapshot::class);
        $this->app->singleton(RuntimeGovernanceMetrics::class);
        $this->app->singleton(GovernanceStatusAggregator::class);
        $this->app->singleton(RuntimeStabilityIndexCalculator::class);

        // PHASE 8.4 — Validation
        $this->app->singleton(GovernanceRuntimeValidationService::class);

        // PHASE 8.5 — Runtime Isolation
        $this->app->singleton(TenantCapacityProfile::class);
        $this->app->singleton(TenantQuotaManager::class);
        $this->app->singleton(TenantRuntimeBudget::class);
        $this->app->singleton(TenantConsumptionTracker::class);
        $this->app->singleton(TenantResourceGovernor::class);
        $this->app->singleton(RuntimeIsolationCoordinator::class);

        // PHASE 8.5 — Noisy Neighbor
        $this->app->singleton(TenantPressureAnalyzer::class);
        $this->app->singleton(NoisyNeighborDetector::class);
        $this->app->singleton(TenantPenaltyEngine::class);
        $this->app->singleton(AdaptiveTenantThrottler::class);
        $this->app->singleton(RuntimeFairnessCoordinator::class);

        // PHASE 8.5 — Distributed Queue
        $this->app->singleton(QueuePartitionRegistry::class);
        $this->app->singleton(TenantQueueAffinityResolver::class);
        $this->app->singleton(QueueShardManager::class);
        $this->app->singleton(RuntimeQueueBalancer::class);
        $this->app->singleton(DistributedQueueCoordinator::class);

        // PHASE 8.5 — Capacity Governance
        $this->app->singleton(CapacityThresholdManager::class);
        $this->app->singleton(CapacityForecastEngine::class);
        $this->app->singleton(RuntimePressurePredictor::class);
        $this->app->singleton(RuntimeScalingAdvisor::class);
        $this->app->singleton(RuntimeCapacityPlanner::class);

        // PHASE 8.5 — Node Coordination
        $this->app->singleton(NodeRegistry::class);
        $this->app->singleton(NodeCapacityTracker::class);
        $this->app->singleton(DistributedRuntimeMap::class);
        $this->app->singleton(RuntimeNodeCoordinator::class);

        // PHASE 8.5 — Cache Governance
        $this->app->singleton(TenantCacheNamespaceManager::class);
        $this->app->singleton(RuntimeCachePartitioner::class);
        $this->app->singleton(TenantCachePressureMonitor::class);
        $this->app->singleton(CacheEvictionCoordinator::class);
        $this->app->singleton(DistributedCacheIsolation::class);

        // PHASE 8.5 — Storage Governance
        $this->app->singleton(RuntimeStorageQuotaService::class);
        $this->app->singleton(AttachmentAccessTelemetry::class);
        $this->app->singleton(AttachmentIsolationManager::class);
        $this->app->singleton(StoragePressureAnalyzer::class);
        $this->app->singleton(TenantStorageGovernor::class);

        // PHASE 8.5 — Runtime Scheduling
        $this->app->singleton(RuntimeWorkloadClassifier::class);
        $this->app->singleton(TenantPriorityScheduler::class);
        $this->app->singleton(DeferredExecutionCoordinator::class);
        $this->app->singleton(GovernanceAwareDispatcher::class);
        $this->app->singleton(RuntimeScheduler::class);

        // PHASE 8.5 — Scalability
        $this->app->singleton(TenantStressSimulator::class);
        $this->app->singleton(QueuePressureSimulator::class);
        $this->app->singleton(RuntimeLoadSimulationEngine::class);
        $this->app->singleton(CapacityValidationReport::class);
        $this->app->singleton(ScalabilityValidationService::class);

        // PHASE 8.5 — Scalability Metrics
        $this->app->singleton(RuntimeCapacitySnapshot::class);
        $this->app->singleton(TenantIsolationMetrics::class);
        $this->app->singleton(DistributedRuntimeMetrics::class);
        $this->app->singleton(QueueScalabilityMetrics::class);
        $this->app->singleton(ResourcePressureIndex::class);

        // PHASE 8.6 — Plugin Capability Governance (leaf-first)
        $this->app->singleton(ModuleCapabilityRegistry::class);
        $this->app->singleton(CapabilityGrantManager::class);
        $this->app->singleton(CapabilityResolutionEngine::class);
        $this->app->singleton(RuntimeCapabilityEnforcer::class);

        // PHASE 8.6 — Sandbox
        $this->app->singleton(ModuleResourceLimiter::class);
        $this->app->singleton(RuntimeSandboxBoundary::class);
        $this->app->singleton(IsolatedExecutionCoordinator::class);
        $this->app->singleton(SandboxRuntimeManager::class);

        // PHASE 8.6 — Dependency Scanning
        $this->app->singleton(DependencyRiskClassifier::class);
        $this->app->singleton(UnsafeImportDetector::class);
        $this->app->singleton(SandboxCompatibilityAnalyzer::class);
        $this->app->singleton(RuntimeDependencyValidator::class);
        $this->app->singleton(PluginDependencyScanner::class);

        // PHASE 8.6 — Lifecycle
        $this->app->singleton(PluginHealthMonitor::class);
        $this->app->singleton(PluginActivationCoordinator::class);
        $this->app->singleton(PluginUpgradeValidator::class);
        $this->app->singleton(PluginRollbackCoordinator::class);
        $this->app->singleton(PluginLifecycleManager::class);

        // PHASE 8.6 — Trust
        $this->app->singleton(TrustedPublisherRegistry::class);
        $this->app->singleton(ModuleTrustRegistry::class);
        $this->app->singleton(PluginIntegrityValidator::class);
        $this->app->singleton(PluginSignatureVerifier::class);
        $this->app->singleton(ExtensionAuthenticityService::class);

        // PHASE 8.6 — Containment
        $this->app->singleton(SandboxViolationTelemetry::class);
        $this->app->singleton(RuntimeViolationDetector::class);
        $this->app->singleton(ExtensionQuarantineService::class);
        $this->app->singleton(UnsafeExecutionTerminator::class);
        $this->app->singleton(PluginContainmentManager::class);

        // PHASE 8.6 — Resource Governance
        $this->app->singleton(PluginResourceBudget::class);
        $this->app->singleton(ExtensionRuntimePressureAnalyzer::class);
        $this->app->singleton(PluginFairnessCoordinator::class);
        $this->app->singleton(SandboxLoadBalancer::class);
        $this->app->singleton(PluginQuotaManager::class);

        // PHASE 8.6 — Marketplace
        $this->app->singleton(MarketplaceGovernanceValidator::class);
        $this->app->singleton(RuntimeMarketplacePolicy::class);

        // PHASE 8.6 — Metrics
        $this->app->singleton(PluginRuntimeMetrics::class);

        // PHASE 6.98 — TASK 6: Register ModuleUpdateOrchestrator
        $this->app->singleton(ModuleUpdateOrchestrator::class);

        // PHASE 9.4 — Realtime Collaboration
        $this->app->singleton(RealtimePresenceService::class);
        $this->app->singleton(UserPresenceTracker::class);
        $this->app->singleton(CollaborativeEditingCoordinator::class);
        $this->app->singleton(LiveActivityBroadcastService::class);
        $this->app->singleton(RealtimeNotificationHub::class);
        $this->app->singleton(ConnectionHealthMonitor::class);

        // PHASE 9.4 — AI Layer (base prompt registry + suggestion engine)
        $this->app->singleton(AiPromptRegistry::class);
        $this->app->singleton(AiSuggestionEngine::class);
        $this->app->singleton(ContextAwareRecommendationService::class);
        $this->app->singleton(SmartAutomationAdvisor::class);

        // PHASE 9.5-A — Prompt governance (leaf-first)
        $this->app->singleton(SensitiveDataRedactor::class);
        $this->app->singleton(PromptInjectionDetector::class);
        $this->app->singleton(AiPromptAuditLogger::class);
        $this->app->singleton(PromptSecuritySanitizer::class);
        $this->app->singleton(TenantSafePromptBuilder::class);
        $this->app->singleton(PromptTemplateRegistry::class);

        // PHASE 9.5-A — AI Rate/Quota/Budget governance
        $this->app->singleton(TenantAiQuotaManager::class);
        $this->app->singleton(AiRateLimitCoordinator::class);
        $this->app->singleton(AiCostEstimator::class);
        $this->app->singleton(AiRequestBudgetManager::class);
        $this->app->singleton(AiOperationPolicyEngine::class);
        $this->app->singleton(AiUsageGovernor::class);

        // PHASE 9.5-A — AI Observability
        $this->app->singleton(AiTelemetryService::class);
        $this->app->singleton(AiInteractionTimeline::class);
        $this->app->singleton(AiDecisionAuditLog::class);
        $this->app->singleton(AiFailureAnalytics::class);
        $this->app->singleton(AiSuggestionEffectivenessAnalyzer::class);

        // PHASE 9.5-A — Provider runtime (providers registered, then manager, then coordinator)
        $this->app->singleton(AiProviderManager::class, function ($app) {
            $manager = new AiProviderManager();
            $manager->register($app->make(OpenAIProvider::class));
            $manager->register($app->make(ClaudeProvider::class));
            $active = config('ai.provider', 'none');
            if ($active !== 'none' && $manager->getProvider($active)) {
                try { $manager->setActive($active); } catch (\Throwable) {}
            }
            return $manager;
        });
        $this->app->singleton(OpenAIProvider::class);
        $this->app->singleton(ClaudeProvider::class);
        $this->app->singleton(AiCapabilityRegistry::class);
        $this->app->singleton(AiProviderHealthMonitor::class);
        $this->app->singleton(AiFallbackCoordinator::class);
        $this->app->singleton(AiProviderDiagnostics::class);
        $this->app->singleton(AiGovernanceValidationService::class);

        // PHASE 9.5-A — AI Runtime Coordinator (orchestrates all governance + providers)
        $this->app->singleton(AiRuntimeCoordinator::class);

        // PHASE 9.5-A — Upgraded AI Assistants (now use coordinator + templates)
        $this->app->singleton(AiFormBuilderAssistant::class);
        $this->app->singleton(AiSupportReplyAssistant::class);

        // PHASE 9.5-A — AI config
        $this->mergeConfigFrom(__DIR__ . '/../../config/ai.php', 'ai');

        // PHASE 10.0 — Frontend Authority System
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\FrontendAuthorityValidationService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\FrontendRuntimeRecoveryService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\DesignTokenGovernanceService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\CanonicalComponentRegistry::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\FrontendDriftDetector::class);

        // PHASE 10.1 — Enterprise Frontend Governance
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\FrontendComponentGovernanceService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\FrontendStateGovernanceService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\ResponsiveExperienceValidationService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\FrontendNormalizationRecoveryService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\EnterpriseFrontendGovernanceValidationService::class);

        // PHASE 10.2 — Zero-Drift Canonical Frontend Enforcement
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\CanonicalComponentAuthorityService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\DesignTokenEnforcementService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\ResponsiveGovernanceValidationService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\AccessibilityGovernanceValidationService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\FrontendDriftAutoRecoveryService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\ModuleFrontendInheritanceService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\ZeroDriftFrontendValidationService::class);

        // PHASE 10.2.5 — Cross-Project CI Enforcement & Frontend Governance Lockdown
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\FrontendCrossRepositoryGovernanceValidator::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\FrontendStaticGovernanceScanner::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\FrontendDriftLockdownService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\ModuleFrontendInheritanceBootstrapper::class);

        // PHASE 10.2.8 — Tailwind v4 Runtime Migration & Frontend Engine Unification
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\TailwindRuntimeGovernanceService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Frontend\FrontendRuntimeUnificationRecoveryService::class);

        // PHASE 9.5-B — AI Safety
        $this->app->singleton(\SaaSPlatform\CoreUtils\AI\Safety\AiRuntimeSafetyEngine::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\AI\Safety\AiResponseRiskAnalyzer::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\AI\Safety\AiTenantBoundaryValidator::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\AI\Safety\AiEmergencyCircuitBreaker::class);

        // PHASE 9.5-B — Realtime Runtime
        $this->app->singleton(\SaaSPlatform\CoreUtils\Realtime\GracefulPollingFallback::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Realtime\ReconnectionEngine::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Realtime\HeartbeatMonitor::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Realtime\TenantChannelManager::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Realtime\WebSocketAbstractionLayer::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Realtime\RealtimeRuntimeCoordinator::class);

        // PHASE 9.5-B — Notifications
        $this->app->singleton(\SaaSPlatform\CoreUtils\Notifications\NotificationDeliveryTracker::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Notifications\NotificationDigestService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Notifications\NotificationPreferenceService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Notifications\NotificationChannelManager::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Notifications\EscalationRuleEngine::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Notifications\UnifiedNotificationService::class);

        // PHASE 9.5-B — Frontend Parity
        $this->app->singleton(\SaaSPlatform\CoreUtils\Services\FrontendCanonicalParityEnforcer::class);

        // PHASE 9.5-B — Runtime Recovery
        $this->app->singleton(\SaaSPlatform\CoreUtils\RuntimeRecovery\FrontendSelfHealingEngine::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\RuntimeRecovery\RealtimeRecoveryCoordinator::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\RuntimeRecovery\RuntimeRecoveryOrchestrator::class);

        // PHASE 9.5-B — Analytics Expansion
        $this->app->singleton(\SaaSPlatform\CoreUtils\Analytics\AiUsageAnalyticsService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\Analytics\UxInteractionTelemetryService::class);

        // PHASE 9.5-B — Security Patch Dashboard
        $this->app->singleton(\SaaSPlatform\CoreUtils\Security\SecurityPatchDashboardService::class);

        // PHASE 9.4 — Search & Command Center
        $this->app->singleton(GlobalSearchEngine::class, function ($app) {
            $engine = new GlobalSearchEngine();
            // Register tenant-aware search drivers
            $engine->registerDriver('tickets', function (string $query, string $tenantId, int $limit) {
                if (! class_exists(\SaaSPlatform\Modules\Tickets\Models\Ticket::class)) return [];
                try {
                    return \SaaSPlatform\Modules\Tickets\Models\Ticket::where('site_id', $tenantId)
                        ->where(fn ($q) => $q->where('subject', 'like', "%{$query}%")->orWhere('ticket_number', 'like', "%{$query}%"))
                        ->limit($limit)->get(['id', 'subject', 'ticket_number', 'status'])
                        ->map(fn ($t) => ['id' => $t->id, 'label' => $t->subject, 'meta' => '#'.$t->ticket_number, 'url' => '/admin/tickets/'.$t->id, 'relevance' => 5])->toArray();
                } catch (\Throwable) { return []; }
            });
            $engine->registerDriver('forms', function (string $query, string $tenantId, int $limit) {
                if (! class_exists(\SaaSPlatform\Modules\Forms\Models\Form::class)) return [];
                try {
                    return \SaaSPlatform\Modules\Forms\Models\Form::where('site_id', $tenantId)
                        ->where('title', 'like', "%{$query}%")
                        ->limit($limit)->get(['id', 'title', 'slug'])
                        ->map(fn ($f) => ['id' => $f->id, 'label' => $f->title, 'meta' => $f->slug, 'url' => '/admin/forms/'.$f->id, 'relevance' => 4])->toArray();
                } catch (\Throwable) { return []; }
            });
            return $engine;
        });
        $this->app->singleton(SmartCommandPalette::class, function () {
            $palette = new SmartCommandPalette();
            $palette->loadDefaults();
            return $palette;
        });
        $this->app->singleton(SearchTelemetryService::class);
        $this->app->singleton(ContextualSearchIndex::class);
        $this->app->singleton(RuntimeQuickActionsService::class, function () {
            $service = new RuntimeQuickActionsService();
            $service->loadDefaults();
            return $service;
        });

        // PHASE 9.4 — Enterprise Governance
        $this->app->singleton(EnterpriseProductExperienceService::class);
        $this->app->singleton(EnterpriseRuntimeGovernanceValidator::class);

        // PHASE 9.4 — Interaction Recovery
        $this->app->singleton(InteractionRecoveryEngine::class);

        // PHASE 9.4 — Security Advisory
        $this->app->singleton(SecurityAdvisoryService::class);

        // PHASE 9.4 — Analytics
        $this->app->singleton(OperationalDashboardService::class);

        // PHASE M1.0 — Self-Hosted Mobile Communication Runtime Foundation
        // Governance core
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Governance\MobileRuntimeRegistry::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Governance\MobileFeatureFlagService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Governance\MobileRuntimeCompatibilityEngine::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Governance\MobileRuntimeValidationService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Governance\MobileRuntimeGovernanceService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Governance\MobileRuntimeManifestBuilder::class);
        // Versioning
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Versioning\MobileVersionPolicyManager::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Versioning\MobileUpdateChannelManager::class);
        // Capabilities
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Capabilities\MobileCapabilityRegistry::class);
        // Security
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Security\MobileSecurityPatchCoordinator::class);
        // Realtime
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Realtime\MobileRealtimeCoordinator::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Realtime\ConversationChannelRegistry::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Realtime\TypingIndicatorRuntime::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Realtime\RealtimeConnectionTelemetry::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Realtime\RuntimeSocketAuthorizationService::class);
        // Presence
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Presence\PresenceRuntimeManager::class);
        // Communication
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Communication\ConversationService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Communication\ConversationRepository::class);
        // Device Management
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement\MobileDeviceRegistry::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement\DeviceTrustManager::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement\MobileSessionCoordinator::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement\MobilePushTokenRegistry::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement\DeviceSecurityValidator::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement\DeviceCapabilityResolver::class);
        // OTA
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\OTA\MobileUpdateManifestBuilder::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\OTA\MobileFeatureBundleRegistry::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\OTA\MobileUiSchemaRegistry::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\OTA\MobileThemeRuntime::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\OTA\MobileVersionEnforcementService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\OTA\MobileMandatoryUpdatePolicy::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\OTA\MobileSecurityPatchRuntime::class);
        // Notifications
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Notifications\MobileNotificationCoordinator::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Notifications\PushDeliveryRuntime::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Notifications\NotificationPriorityEngine::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Notifications\NotificationRoutingService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Notifications\MobileNotificationPolicyManager::class);
        // Telemetry
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Telemetry\MobileRuntimeTelemetryService::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Telemetry\MobileConnectionMetrics::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Telemetry\MobileCrashTelemetry::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Telemetry\MobileDeliveryAnalytics::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Telemetry\MobileRealtimeDiagnostics::class);
        // PHASE M1.0.2 — Production Runtime Enablement
        // Infrastructure validators
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Infrastructure\RedisRuntimeValidator::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Infrastructure\PresenceStorageValidator::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Infrastructure\QueueHealthInspector::class);
        // Realtime health
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Realtime\ReverbRuntimeValidator::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Realtime\RealtimeConfigurationInspector::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Realtime\MobileRealtimeHealthService::class);
        // Security patch governance
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Security\SecurityPatchRegistry::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Security\MobilePatchRegistryAdapter::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Security\PatchDistributionValidator::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Security\PatchGovernanceHealthService::class);
        // Push governance
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Notifications\PushTransportValidator::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Notifications\NotificationDeliveryHealthService::class);
        // Unified validation
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Validation\MobileEnvironmentValidator::class);
        $this->app->singleton(\SaaSPlatform\CoreUtils\MobileRuntime\Validation\MobileProductionReadinessService::class);

        // Mobile config
        $this->mergeConfigFrom(__DIR__ . '/../../config/mobile.php', 'mobile');

        // Merge configs
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/saas-platform.php',
            'saas-platform'
        );

        $this->mergeConfigFrom(
            __DIR__ . '/../../config/feature-flags.php',
            'feature-flags'
        );

        $this->mergeConfigFrom(
            __DIR__ . '/../../config/module-assets.php',
            'module-assets'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // PHASE 7.15 — TASK 4: Register core-ui shared Blade components
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'core-ui');

        // PHASE 10.0 — Canonical core-ui anonymous component namespace.
        // Registered FIRST in this provider so core-utils components have authority priority.
        // FormsServiceProvider also calls anonymousComponentPath for the same prefix — Blade
        // resolves both directories in registration order, so core-utils components win on conflict.
        Blade::anonymousComponentPath(__DIR__ . '/../../resources/views/components', 'core-ui');

        // PHASE 10.2 — Auto-register all discovered module view namespaces at boot
        $this->app->make(\SaaSPlatform\CoreUtils\Frontend\ModuleFrontendInheritanceService::class)
                  ->bootInheritance();

        // PHASE M1.0.2 — Bootstrap mobile security patch registry (idempotent, Cache-backed)
        $this->app->make(\SaaSPlatform\CoreUtils\MobileRuntime\Security\MobilePatchRegistryAdapter::class);

        // Publish shared components for consuming projects
        $this->publishes([
            __DIR__ . '/../../resources/views' => resource_path('views/vendor/core-ui'),
        ], 'core-ui-views');

        // Publish canonical CSS tokens
        $this->publishes([
            __DIR__ . '/../../resources/css/design-tokens.css' => public_path('vendor/core-ui/css/design-tokens.css'),
        ], 'core-ui-tokens');

        // Register Blade directive @asset_module
        AssetModuleDirective::register();

        // PHASE 6.95 — TASK 2: Register Blade Authority Directives
        $this->registerBladeDirectives();

        // Register Artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleAssetsCheckCommand::class,
                DriftScanCommand::class,
                DriftBladesCommand::class,
                DriftControllersCommand::class,
                DriftContractsCommand::class,
                DriftOwnershipCommand::class,
                // PHASE 6.95 — TASK 11: Authority validation command
                ValidateAuthorityCommand::class,
                // PHASE 6.95 — TASK 12: Drift authority command
                DriftAuthorityCommand::class,
                // PHASE 6.98 — TASK 2: Module version management commands
                ModuleVersionCommand::class,
                ModuleBuildCommand::class,
                ModuleReleaseCommand::class,
                ModuleListCommand::class,
                // PHASE 7.15 — TASK 8: UI authority validation command
                ValidateUiAuthorityCommand::class,
                // PHASE 7.25 — TASK 9: Tenant runtime authority command
                ValidateTenantRuntimeCommand::class,
                // PHASE 8.1 — TASK 9: Package distribution validation command
                ValidateDistributionCommand::class,
                // PHASE 8.2 — Deployment commands
                DeploymentLockCommand::class,
                DeploymentUnlockCommand::class,
                DeploymentStatusCommand::class,
                DeploymentRollbackCommand::class,
                ValidateGovernanceCommand::class,
                MigrationValidateSafetyCommand::class,
                MigrationDetectDriftCommand::class,
                TenantDownCommand::class,
                TenantUpCommand::class,
                TenantMaintenanceStatusCommand::class,
                QueueValidateRuntimeCommand::class,
                QueueDrainSafeCommand::class,
                PlatformHealthCommand::class,
                // PHASE 8.3 — Telemetry & observability commands
                QueueTelemetryCommand::class,
                QueueDiagnosticsCommand::class,
                ValidateObservabilityCommand::class,
                // PHASE 8.4 — Governance commands
                ValidateRuntimeCommand::class,
                RuntimeStatusCommand::class,
                SimulateFailureCommand::class,
                TestRecoveryCommand::class,
                ListPoliciesCommand::class,
                // PHASE 8.5 — Scalability commands
                ValidateScalabilityCommand::class,
                SimulateLoadCommand::class,
                TestIsolationCommand::class,
                ForecastCapacityCommand::class,
                // PHASE 8.6 — Plugin governance commands
                ValidateSandboxCommand::class,
                ScanDependenciesCommand::class,
                PluginTestIsolationCommand::class,
                VerifySignaturesCommand::class,
                PluginRuntimeStatusCommand::class,
                // PHASE 9.4 — Enterprise commands
                ValidateProductExperienceCommand::class,
                ValidateEnterpriseGovernanceCommand::class,
                // PHASE 9.5-A — AI commands
                ValidateAiGovernanceCommand::class,
                TestAiProviderCommand::class,
                AiDiagnosticsCommand::class,
                AiQuotaReportCommand::class,
                // PHASE 9.5-B — AI runtime, frontend parity, realtime, enterprise, UX commands
                \SaaSPlatform\CoreUtils\Console\Commands\AI\ValidateAiRuntimeCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\Frontend\ValidateCanonicalParityCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\Frontend\EnforceParityCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\Realtime\ValidateRealtimeRuntimeCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\Enterprise\ValidateRuntimeHealthCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\Enterprise\ValidateUxReadinessCommand::class,
                // PHASE 10.0 — Frontend Authority System
                \SaaSPlatform\CoreUtils\Console\Commands\Frontend\ValidateFrontendAuthorityCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\Frontend\FrontendRecoveryCommand::class,
                // PHASE 10.1 — Enterprise Frontend Governance
                \SaaSPlatform\CoreUtils\Console\Commands\Frontend\ValidateComponentsCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\Frontend\ValidateResponsiveCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\Frontend\RecoverNormalizationCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\Frontend\ValidateEnterpriseGovernanceCommand::class,
                // PHASE 10.2 — Zero-Drift Canonical Frontend Enforcement
                \SaaSPlatform\CoreUtils\Console\Commands\ValidateComponentAuthorityCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\ValidateResponsiveGovernanceCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\ValidateFrontendParityCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\FrontendAutoHealCommand::class,
                // PHASE 10.2.5 — Cross-Project CI Enforcement & Frontend Governance Lockdown
                \SaaSPlatform\CoreUtils\Console\Commands\Frontend\ValidateCrossRepositoryGovernanceCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\Frontend\FrontendLockdownReportCommand::class,
                // PHASE 10.2.8 — Tailwind v4 Runtime Migration & Frontend Engine Unification
                \SaaSPlatform\CoreUtils\Console\Commands\Frontend\ValidateTailwindRuntimeCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\Frontend\RecoverTailwindRuntimeCommand::class,
                // PHASE M1.0.2 — Production Runtime Enablement
                \SaaSPlatform\CoreUtils\Console\Commands\Mobile\ValidateProductionReadinessCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\Mobile\ValidateEnvironmentCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\Mobile\ValidatePatchDistributionCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\Mobile\RealtimeHealthCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\Mobile\PushHealthCommand::class,
                // PHASE M1.0 — Self-Hosted Mobile Communication Runtime Foundation
                \SaaSPlatform\CoreUtils\Console\Commands\Mobile\ValidateRuntimeGovernanceCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\Mobile\ValidateRealtimeCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\Mobile\RuntimeStatusCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\Mobile\ValidateOtaCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\Mobile\ValidateSecurityCommand::class,
                \SaaSPlatform\CoreUtils\Console\Commands\Mobile\DiagnosticsCommand::class,
            ]);
        }

        // Publish configs
        $this->publishes([
            __DIR__ . '/../../config/saas-platform.php' => config_path('saas-platform.php'),
        ], 'core-utils-config');

        $this->publishes([
            __DIR__ . '/../../config/feature-flags.php' => config_path('feature-flags.php'),
        ], 'core-utils-config');

        $this->publishes([
            __DIR__ . '/../../config/module-assets.php' => config_path('module-assets.php'),
        ], 'core-utils-config');
    }

    /**
     * Register Blade authority directives.
     *
     * PHASE 6.95 — TASK 2:
     * - @platform / @endplatform: platform-only content
     * - @tenant / @endtenant: tenant-only content
     * - @central / @endcentral: central admin only
     * - @tenantadmin / @endtenantadmin: tenant admin only
     * - @platformfeature('key') / @endplatformfeature: feature-gated content
     */
    protected function registerBladeDirectives(): void
    {
        // @platform / @endplatform
        Blade::if('platform', function () {
            return app(PlatformContext::class)->isCentralAdmin();
        });

        // @tenant / @endtenant
        Blade::if('tenant', function () {
            return app(PlatformContext::class)->isTenant();
        });

        // @central / @endcentral
        Blade::if('central', function () {
            return app(PlatformContext::class)->isCentralAdmin();
        });

        // @tenantadmin / @endtenantadmin
        Blade::if('tenantadmin', function () {
            return app(PlatformContext::class)->isTenantAdmin();
        });

        // @platformfeature('feature-key') / @endplatformfeature
        Blade::if('platformfeature', function (string $featureKey) {
            return app(CapabilityResolver::class)->canAccess($featureKey);
        });
    }
}
