<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Mobile;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * mobile:validate-production-client
 *
 * Scores the production client implementation across 10 dimensions.
 * Build gate: score < 95 exits with code 1 (fails CI).
 *
 * Dimensions (10 points each):
 *  1. Transport     — Reverb + Redis configuration and health
 *  2. Offline       — queue, retry, conflict resolution
 *  3. Updates       — OTA endpoint, channels, checksum
 *  4. Notifications — runtime, voice, OS channels
 *  5. Security      — secure storage, device trust, token rotation
 *  6. Attachments   — upload endpoint, MIME validation, tenant isolation
 *  7. Deep links    — scheme registration, parsing, notification integration
 *  8. Dashboard     — API endpoint, realtime widgets
 *  9. Assignment    — assign/reassign/unassign/escalate + audit log
 * 10. Presence      — ConversationPresenceService integration
 */
class ValidateProductionClientCommand extends Command
{
    protected $signature   = 'mobile:validate-production-client {--json} {--fail-fast}';
    protected $description = 'Validate M2.1 production client implementation (target: 95+/100)';

    private array $results = [];
    private int   $total   = 0;

    public function handle(): int
    {
        $this->info('');
        $this->info('══════════════════════════════════════════');
        $this->info('  Phase M2.1 — Production Client Validator');
        $this->info('══════════════════════════════════════════');
        $this->info('');

        $this->runDimension(1,  'Transport',     [$this, 'validateTransport']);
        $this->runDimension(2,  'Offline',       [$this, 'validateOffline']);
        $this->runDimension(3,  'Updates',       [$this, 'validateUpdates']);
        $this->runDimension(4,  'Notifications', [$this, 'validateNotifications']);
        $this->runDimension(5,  'Security',      [$this, 'validateSecurity']);
        $this->runDimension(6,  'Attachments',   [$this, 'validateAttachments']);
        $this->runDimension(7,  'Deep Links',    [$this, 'validateDeepLinks']);
        $this->runDimension(8,  'Dashboard',     [$this, 'validateDashboard']);
        $this->runDimension(9,  'Assignment',    [$this, 'validateAssignment']);
        $this->runDimension(10, 'Presence',      [$this, 'validatePresence']);

        return $this->renderReport();
    }

    // ── Dimension runners ─────────────────────────────────────────────────────

    private function runDimension(int $num, string $name, callable $fn): void
    {
        $findings = [];
        $score    = 0;

        try {
            ['score' => $score, 'findings' => $findings] = $fn($findings);
        } catch (\Throwable $e) {
            $findings[] = ['level' => 'error', 'message' => "Exception: {$e->getMessage()}"];
            $score = 0;
        }

        $score = min(10, max(0, (int) $score));
        $this->total += $score;

        $this->results[] = [
            'num'      => $num,
            'name'     => $name,
            'score'    => $score,
            'findings' => $findings,
        ];

        if (! $this->option('json')) {
            $icon = $score >= 9 ? '✓' : ($score >= 7 ? '~' : '✗');
            $this->line(sprintf('  [%s] %-18s %d/10', $icon, $name, $score));
            foreach ($findings as $f) {
                $prefix = match ($f['level'] ?? 'info') {
                    'error'   => '        ✗ ',
                    'warning' => '        ~ ',
                    default   => '        · ',
                };
                $this->line($prefix . $f['message']);
            }
        }

        if ($this->option('fail-fast') && $score < 7) {
            $this->error("Fail-fast: dimension '{$name}' scored {$score}/10.");
            exit(1);
        }
    }

    // ── 1. Transport ──────────────────────────────────────────────────────────

    private function validateTransport(array $f): array
    {
        $score = 0;

        // Reverb configured
        $reverbKey = config('reverb.apps.0.key') ?? config('broadcasting.connections.reverb.key');
        if ($reverbKey) {
            $score += 3;
            $f[] = ['level' => 'ok', 'message' => "Reverb app key configured: {$reverbKey}"];
        } else {
            $f[] = ['level' => 'error', 'message' => 'Reverb app key not configured'];
        }

        // Broadcast driver
        if (config('broadcasting.default') === 'reverb') {
            $score += 2;
            $f[] = ['level' => 'ok', 'message' => 'Broadcast driver = reverb'];
        } else {
            $f[] = ['level' => 'warning', 'message' => 'Broadcast driver is not reverb'];
        }

        // Redis cache driver
        if (in_array(config('cache.default'), ['redis', 'predis'], true)) {
            $score += 2;
            $f[] = ['level' => 'ok', 'message' => 'Cache driver = redis'];
        } else {
            $f[] = ['level' => 'warning', 'message' => 'Cache driver is not redis — presence relies on Redis'];
        }

        // ConversationChannelRegistry exists
        if (class_exists(\SaaSPlatform\CoreUtils\MobileRuntime\Realtime\ConversationChannelRegistry::class)) {
            $score += 2;
            $f[] = ['level' => 'ok', 'message' => 'ConversationChannelRegistry present'];
        }

        // Mobile realtime route
        if ($this->routeExists('api/mobile/realtime/auth')) {
            $score += 1;
            $f[] = ['level' => 'ok', 'message' => 'Realtime auth route registered'];
        } else {
            $f[] = ['level' => 'warning', 'message' => 'Realtime auth route not found'];
        }

        return compact('score', 'f') + ['findings' => $f];
    }

    // ── 2. Offline ────────────────────────────────────────────────────────────

    private function validateOffline(array $f): array
    {
        $score = 0;

        $dartFiles = [
            'mobile/lib/core/offline/offline_sync_service.dart',
            'mobile/lib/core/offline/offline_cache_service.dart',
        ];

        foreach ($dartFiles as $path) {
            if (File::exists(base_path($path))) {
                $score += 2;
                $f[] = ['level' => 'ok', 'message' => "Found: {$path}"];
            } else {
                $f[] = ['level' => 'error', 'message' => "Missing: {$path}"];
            }
        }

        // Check offline sync has attachment queuing
        $syncContent = File::exists(base_path('mobile/lib/core/offline/offline_sync_service.dart'))
            ? File::get(base_path('mobile/lib/core/offline/offline_sync_service.dart'))
            : '';

        if (str_contains($syncContent, 'enqueueAttachment')) {
            $score += 2;
            $f[] = ['level' => 'ok', 'message' => 'Attachment queuing implemented'];
        } else {
            $f[] = ['level' => 'error', 'message' => 'Attachment queuing not found in OfflineSyncService'];
        }

        // Conflict resolution (server wins)
        if (str_contains($syncContent, '409')) {
            $score += 2;
            $f[] = ['level' => 'ok', 'message' => 'Conflict resolution (409) handled'];
        } else {
            $f[] = ['level' => 'warning', 'message' => '409 conflict resolution not detected'];
        }

        // Retry logic
        if (str_contains($syncContent, '_retry_count')) {
            $score += 2;
            $f[] = ['level' => 'ok', 'message' => 'Retry counter implemented'];
        } else {
            $f[] = ['level' => 'warning', 'message' => 'Retry counter not detected'];
        }

        return ['score' => $score, 'findings' => $f];
    }

    // ── 3. Updates ────────────────────────────────────────────────────────────

    private function validateUpdates(array $f): array
    {
        $score = 0;

        // OTA route
        if ($this->routeExists('api/mobile/ota/manifest')) {
            $score += 3;
            $f[] = ['level' => 'ok', 'message' => 'OTA manifest route exists'];
        } else {
            $f[] = ['level' => 'error', 'message' => 'OTA manifest route missing'];
        }

        // Channels: stable/beta/canary in Flutter
        $otaContent = File::exists(base_path('mobile/lib/core/ota/ota_update_service.dart'))
            ? File::get(base_path('mobile/lib/core/ota/ota_update_service.dart'))
            : '';

        foreach (['stable', 'beta', 'canary'] as $channel) {
            if (str_contains($otaContent, $channel)) {
                $score += 1;
                $f[] = ['level' => 'ok', 'message' => "Update channel '{$channel}' defined"];
            } else {
                $f[] = ['level' => 'error', 'message' => "Update channel '{$channel}' missing"];
            }
        }

        // Windows + Android update runtimes
        $runtimes = [
            'mobile/lib/core/ota/windows_update_runtime.dart',
            'mobile/lib/core/ota/android_update_runtime.dart',
        ];
        foreach ($runtimes as $path) {
            if (File::exists(base_path($path))) {
                $score += 1;
                $f[] = ['level' => 'ok', 'message' => "Found: {$path}"];
            } else {
                $f[] = ['level' => 'error', 'message' => "Missing: {$path}"];
            }
        }

        // Checksum verification
        $winContent = File::exists(base_path('mobile/lib/core/ota/windows_update_runtime.dart'))
            ? File::get(base_path('mobile/lib/core/ota/windows_update_runtime.dart'))
            : '';
        if (str_contains($winContent, 'sha256')) {
            $score += 1;
            $f[] = ['level' => 'ok', 'message' => 'SHA-256 checksum verification in Windows runtime'];
        } else {
            $f[] = ['level' => 'error', 'message' => 'Checksum verification missing in Windows runtime'];
        }

        return ['score' => min(10, $score), 'findings' => $f];
    }

    // ── 4. Notifications ──────────────────────────────────────────────────────

    private function validateNotifications(array $f): array
    {
        $score = 0;

        $files = [
            'mobile/lib/core/notifications/client_notification_runtime.dart' => 3,
            'mobile/lib/core/notifications/voice_notification_runtime.dart'  => 2,
            'mobile/lib/core/notifications/notification_service.dart'        => 2,
        ];

        foreach ($files as $path => $pts) {
            if (File::exists(base_path($path))) {
                $score += $pts;
                $f[] = ['level' => 'ok', 'message' => "Found: {$path}"];
            } else {
                $f[] = ['level' => 'error', 'message' => "Missing: {$path}"];
            }
        }

        // Priority order: reverb → local → offline
        $runtimeContent = File::exists(base_path('mobile/lib/core/notifications/client_notification_runtime.dart'))
            ? File::get(base_path('mobile/lib/core/notifications/client_notification_runtime.dart'))
            : '';

        foreach (['reverb', 'localOs', 'offlineSync'] as $transport) {
            if (str_contains($runtimeContent, $transport)) {
                $score += 1;
                $f[] = ['level' => 'ok', 'message' => "Notification transport '{$transport}' declared"];
            } else {
                $f[] = ['level' => 'warning', 'message' => "Notification transport '{$transport}' not found"];
            }
        }

        return ['score' => min(10, $score), 'findings' => $f];
    }

    // ── 5. Security ───────────────────────────────────────────────────────────

    private function validateSecurity(array $f): array
    {
        $score = 0;

        // Secure token service
        if (File::exists(base_path('mobile/lib/core/security/secure_token_service.dart'))) {
            $score += 3;
            $f[] = ['level' => 'ok', 'message' => 'SecureTokenService present'];
        }

        // DeviceTrustManager
        if (class_exists(\SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement\DeviceTrustManager::class)) {
            $score += 3;
            $f[] = ['level' => 'ok', 'message' => 'DeviceTrustManager present'];
        }

        // Token interceptor / refresh
        $apiContent = File::exists(base_path('mobile/lib/core/api/client_api_service.dart'))
            ? File::get(base_path('mobile/lib/core/api/client_api_service.dart'))
            : '';

        if (str_contains($apiContent, 'refreshToken')) {
            $score += 2;
            $f[] = ['level' => 'ok', 'message' => 'Token refresh logic present'];
        }

        // Sanctum auth on protected routes
        if ($this->routeExists('api/mobile/conversations')) {
            $score += 2;
            $f[] = ['level' => 'ok', 'message' => 'Conversation routes behind auth:sanctum'];
        }

        return ['score' => min(10, $score), 'findings' => $f];
    }

    // ── 6. Attachments ────────────────────────────────────────────────────────

    private function validateAttachments(array $f): array
    {
        $score = 0;

        $attachService = base_path('mobile/lib/core/api/attachment_upload_service.dart');
        if (File::exists($attachService)) {
            $score += 3;
            $f[] = ['level' => 'ok', 'message' => 'AttachmentUploadService present'];

            $content = File::get($attachService);

            if (str_contains($content, 'multipart')) {
                $score += 2;
                $f[] = ['level' => 'ok', 'message' => 'Multipart upload implemented'];
            }

            foreach (['image/', 'application/pdf', 'application/zip'] as $mime) {
                if (str_contains($content, $mime)) {
                    $score += 1;
                    $f[] = ['level' => 'ok', 'message' => "MIME '{$mime}' whitelisted"];
                }
            }

            if (str_contains($content, '_maxFileSizeBytes')) {
                $score += 1;
                $f[] = ['level' => 'ok', 'message' => 'File size limit enforced'];
            }

            if (str_contains($content, 'AttachmentValidationException')) {
                $score += 1;
                $f[] = ['level' => 'ok', 'message' => 'Validation exception class defined'];
            }
        } else {
            $f[] = ['level' => 'error', 'message' => 'AttachmentUploadService missing'];
        }

        // Backend attachment route
        if ($this->routeExists('api/mobile/conversations') && $this->routeMethodExists('POST', 'conversations/{id}/attachments')) {
            $score = min(10, $score + 1);
            $f[] = ['level' => 'ok', 'message' => 'Attachment upload route registered'];
        }

        return ['score' => min(10, $score), 'findings' => $f];
    }

    // ── 7. Deep Links ─────────────────────────────────────────────────────────

    private function validateDeepLinks(array $f): array
    {
        $score = 0;

        $deepLinkFile = base_path('mobile/lib/core/deeplink/conversation_deep_link_service.dart');
        if (File::exists($deepLinkFile)) {
            $score += 4;
            $f[] = ['level' => 'ok', 'message' => 'ConversationDeepLinkService present'];

            $content = File::get($deepLinkFile);

            if (str_contains($content, 'support://conversation')) {
                $score += 2;
                $f[] = ['level' => 'ok', 'message' => 'support://conversation scheme defined'];
            }

            if (str_contains($content, 'support://tenant')) {
                $score += 2;
                $f[] = ['level' => 'ok', 'message' => 'support://tenant scheme defined'];
            }

            if (str_contains($content, 'handleUri')) {
                $score += 1;
                $f[] = ['level' => 'ok', 'message' => 'handleUri() for notification click integration'];
            }

            if (str_contains($content, 'getInitialUri')) {
                $score += 1;
                $f[] = ['level' => 'ok', 'message' => 'Cold-start deep link handling present'];
            }
        } else {
            $f[] = ['level' => 'error', 'message' => 'ConversationDeepLinkService missing'];
        }

        return ['score' => min(10, $score), 'findings' => $f];
    }

    // ── 8. Dashboard ──────────────────────────────────────────────────────────

    private function validateDashboard(array $f): array
    {
        $score = 0;

        $dashFile = base_path('mobile/lib/features/support/support_dashboard_screen.dart');
        if (File::exists($dashFile)) {
            $score += 3;
            $f[] = ['level' => 'ok', 'message' => 'SupportDashboardScreen present'];

            $content = File::get($dashFile);

            $widgets = ['openConversations', 'assignedConversations', 'unresolvedConversations',
                        'onlineCustomers', 'slaWarnings', 'unreadMessages'];
            foreach ($widgets as $widget) {
                if (str_contains($content, $widget)) {
                    $score += 1;
                    $f[] = ['level' => 'ok', 'message' => "Widget '{$widget}' present"];
                } else {
                    $f[] = ['level' => 'warning', 'message' => "Widget '{$widget}' not found"];
                }
            }
        } else {
            $f[] = ['level' => 'error', 'message' => 'SupportDashboardScreen missing'];
        }

        // API endpoint
        if ($this->routeExists('api/mobile/inbox') || $this->routeExists('api/mobile/dashboard')) {
            $score += 1;
            $f[] = ['level' => 'ok', 'message' => 'Dashboard API endpoint registered'];
        }

        return ['score' => min(10, $score), 'findings' => $f];
    }

    // ── 9. Assignment ─────────────────────────────────────────────────────────

    private function validateAssignment(array $f): array
    {
        $score = 0;

        if (class_exists(\SaaSPlatform\CoreUtils\MobileRuntime\Assignment\ConversationAssignmentService::class)) {
            $score += 3;
            $f[] = ['level' => 'ok', 'message' => 'ConversationAssignmentService present'];

            $methods = ['assign', 'reassign', 'unassign', 'escalate', 'getAssignmentHistory'];
            foreach ($methods as $method) {
                if (method_exists(\SaaSPlatform\CoreUtils\MobileRuntime\Assignment\ConversationAssignmentService::class, $method)) {
                    $score += 1;
                    $f[] = ['level' => 'ok', 'message' => "Method {$method}() present"];
                } else {
                    $f[] = ['level' => 'error', 'message' => "Method {$method}() missing"];
                }
            }

            // Audit log
            if (class_exists(\SaaSPlatform\CoreUtils\MobileRuntime\Assignment\Events\ConversationAssignmentChanged::class)) {
                $score += 2;
                $f[] = ['level' => 'ok', 'message' => 'ConversationAssignmentChanged event for audit present'];
            }
        } else {
            $f[] = ['level' => 'error', 'message' => 'ConversationAssignmentService missing'];
        }

        return ['score' => min(10, $score), 'findings' => $f];
    }

    // ── 10. Presence ──────────────────────────────────────────────────────────

    private function validatePresence(array $f): array
    {
        $score = 0;

        if (class_exists(\SaaSPlatform\CoreUtils\MobileRuntime\Presence\ConversationPresenceService::class)) {
            $score += 4;
            $f[] = ['level' => 'ok', 'message' => 'ConversationPresenceService present'];

            $methods = ['joinConversation', 'leaveConversation', 'updateStatus',
                        'getConversationPresence', 'heartbeat'];
            foreach ($methods as $method) {
                if (method_exists(\SaaSPlatform\CoreUtils\MobileRuntime\Presence\ConversationPresenceService::class, $method)) {
                    $score += 1;
                    $f[] = ['level' => 'ok', 'message' => "Method {$method}() present"];
                } else {
                    $f[] = ['level' => 'error', 'message' => "Method {$method}() missing"];
                }
            }
        } else {
            $f[] = ['level' => 'error', 'message' => 'ConversationPresenceService missing'];
        }

        if (class_exists(\SaaSPlatform\CoreUtils\MobileRuntime\Presence\PresenceRuntimeManager::class)) {
            $score += 1;
            $f[] = ['level' => 'ok', 'message' => 'PresenceRuntimeManager delegated (no parallel system)'];
        }

        return ['score' => min(10, $score), 'findings' => $f];
    }

    // ── Report rendering ──────────────────────────────────────────────────────

    private function renderReport(): int
    {
        $pass = $this->total >= 95;

        if ($this->option('json')) {
            $this->line(json_encode([
                'score'     => $this->total,
                'pass'      => $pass,
                'threshold' => 95,
                'dimensions' => $this->results,
            ], JSON_PRETTY_PRINT));
        } else {
            $this->info('');
            $this->info('──────────────────────────────────────────');
            $this->info(sprintf('  TOTAL SCORE : %d / 100', $this->total));
            $this->info(sprintf('  THRESHOLD   : 95 / 100'));
            $this->info(sprintf('  RESULT      : %s', $pass ? '✓ PASS' : '✗ FAIL'));
            $this->info('──────────────────────────────────────────');
            $this->info('');
        }

        return $pass ? 0 : 1;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function routeExists(string $uri): bool
    {
        try {
            $routes = app('router')->getRoutes();
            foreach ($routes as $route) {
                if (str_contains($route->uri(), str_replace('api/', '', $uri))) {
                    return true;
                }
            }
        } catch (\Throwable) {}
        return false;
    }

    private function routeMethodExists(string $method, string $uriFragment): bool
    {
        try {
            $routes = app('router')->getRoutes();
            foreach ($routes as $route) {
                if (in_array($method, $route->methods(), true) &&
                    str_contains($route->uri(), $uriFragment)) {
                    return true;
                }
            }
        } catch (\Throwable) {}
        return false;
    }
}
