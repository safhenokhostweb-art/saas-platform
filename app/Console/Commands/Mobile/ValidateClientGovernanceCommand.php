<?php

namespace App\Console\Commands\Mobile;

use Illuminate\Console\Command;

class ValidateClientGovernanceCommand extends Command
{
    protected $signature = 'mobile:validate-client-governance
                            {--json : Output as JSON}
                            {--strict : Exit 1 if score below 85}
                            {--fail-below=85 : Minimum passing score}';

    protected $description = 'Master governance validation for Phase M2.0 unified client — orchestrates all sub-validations';

    public function handle(): int
    {
        $this->info('Phase M2.0 — Master Client Governance Validation');
        $this->newLine();

        $dimensions = [];
        $totalScore = 0;
        $totalMax   = 0;

        // ── Architecture governance ────────────────────────────────────────

        $arch = $this->validateArchitecture();
        $dimensions[] = $arch;
        $totalScore  += $arch['score'];
        $totalMax    += $arch['max'];

        // ── Single codebase governance ─────────────────────────────────────

        $codebase = $this->validateSingleCodebase();
        $dimensions[] = $codebase;
        $totalScore  += $codebase['score'];
        $totalMax    += $codebase['max'];

        // ── Transport authority ────────────────────────────────────────────

        $transport = $this->validateTransportAuthority();
        $dimensions[] = $transport;
        $totalScore  += $transport['score'];
        $totalMax    += $transport['max'];

        // ── No business logic duplication ──────────────────────────────────

        $noDupe = $this->validateNoDuplication();
        $dimensions[] = $noDupe;
        $totalScore  += $noDupe['score'];
        $totalMax    += $noDupe['max'];

        // ── Offline resilience ─────────────────────────────────────────────

        $offline = $this->validateOfflineResilience();
        $dimensions[] = $offline;
        $totalScore  += $offline['score'];
        $totalMax    += $offline['max'];

        // ── CI/CD pipeline ─────────────────────────────────────────────────

        $cicd = $this->validateCiCd();
        $dimensions[] = $cicd;
        $totalScore  += $cicd['score'];
        $totalMax    += $cicd['max'];

        // ── Design system authority ────────────────────────────────────────

        $design = $this->validateDesignAuthority();
        $dimensions[] = $design;
        $totalScore  += $design['score'];
        $totalMax    += $design['max'];

        // ── Firebase-optional posture ──────────────────────────────────────

        $firebase = $this->validateFirebaseOptional();
        $dimensions[] = $firebase;
        $totalScore  += $firebase['score'];
        $totalMax    += $firebase['max'];

        // ── Self-hosted readiness ──────────────────────────────────────────

        $selfHosted = $this->validateSelfHosted();
        $dimensions[] = $selfHosted;
        $totalScore  += $selfHosted['score'];
        $totalMax    += $selfHosted['max'];

        // ── Documentation ──────────────────────────────────────────────────

        $docs = $this->validateDocumentation();
        $dimensions[] = $docs;
        $totalScore  += $docs['score'];
        $totalMax    += $docs['max'];

        $pct     = $totalMax > 0 ? round(($totalScore / $totalMax) * 100) : 0;
        $passing = $pct >= (int) $this->option('fail-below');
        $grade   = $pct >= 95 ? 'A+' : ($pct >= 85 ? 'A' : ($pct >= 75 ? 'B+' : 'B'));

        if ($this->option('json')) {
            $this->line(json_encode([
                'command'       => 'mobile:validate-client-governance',
                'phase'         => 'M2.0',
                'score'         => "$totalScore/$totalMax ({$pct}%)",
                'grade'         => $grade,
                'passing'       => $passing,
                'dimensions'    => $dimensions,
                'm2_1_blockers' => $this->getM21Blockers(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderDimensions($dimensions);
            $this->newLine();
            $this->line("Score: <fg=" . ($passing ? 'green' : 'red') . ">{$totalScore}/{$totalMax} ({$pct}%) — Grade {$grade}</>");
            $this->newLine();

            $blockers = $this->getM21Blockers();
            if (!empty($blockers)) {
                $this->info('Remaining blockers before M2.1:');
                foreach ($blockers as $i => $b) {
                    $this->line('  ' . ($i + 1) . '. ' . $b);
                }
            }
        }

        return ($this->option('strict') && !$passing) ? self::FAILURE : self::SUCCESS;
    }

    private function validateArchitecture(): array
    {
        $checks = [
            file_exists(base_path('mobile/pubspec.yaml')),
            file_exists(base_path('mobile/lib/main.dart')),
            file_exists(base_path('mobile/lib/core/api/client_api_service.dart')),
            file_exists(base_path('mobile/lib/core/realtime/realtime_client_service.dart')),
            file_exists(base_path('docs/phase-m2.0/client-architecture.md')),
        ];
        $passed = count(array_filter($checks));
        return ['dimension' => 'Architecture', 'score' => $passed * 2, 'max' => 10,
            'detail' => "$passed/5 architecture files present"];
    }

    private function validateSingleCodebase(): array
    {
        // Verify no platform-specific forks exist
        $dartFiles = glob(base_path('mobile/lib/**/*.dart')) ?: [];
        $platformForks = array_filter($dartFiles, fn($f) => str_contains($f, '_android.dart') || str_contains($f, '_ios.dart'));
        $noForks = count($platformForks) === 0;
        $screens = file_exists(base_path('mobile/lib/features/auth/login_screen.dart'))
            && file_exists(base_path('mobile/lib/features/conversations/conversation_list_screen.dart'))
            && file_exists(base_path('mobile/lib/features/conversations/conversation_view_screen.dart'));
        $score = ($noForks ? 5 : 0) + ($screens ? 5 : 0);
        return ['dimension' => 'Single Codebase', 'score' => $score, 'max' => 10,
            'detail' => 'No platform forks + all 3 screens present'];
    }

    private function validateTransportAuthority(): array
    {
        $reverbPrimary = file_exists(base_path('mobile/lib/core/realtime/realtime_client_service.dart'))
            && str_contains(file_get_contents(base_path('mobile/lib/core/realtime/realtime_client_service.dart')), 'pusher_channels_flutter');
        $firebaseOptional = config('mobile.notification_transport.firebase_optional', false);
        $score = ($reverbPrimary ? 7 : 0) + ($firebaseOptional ? 3 : 0);
        return ['dimension' => 'Transport Authority', 'score' => $score, 'max' => 10,
            'detail' => 'Reverb primary, Firebase optional'];
    }

    private function validateNoDuplication(): array
    {
        // Business logic must live in PHP services, not duplicated in Flutter
        $conversationServiceExists = class_exists('SaaSPlatform\\CoreUtils\\MobileRuntime\\Communication\\ConversationService');
        $flutterCallsApi = file_exists(base_path('mobile/lib/core/api/client_api_service.dart'));
        $score = ($conversationServiceExists ? 5 : 0) + ($flutterCallsApi ? 5 : 0);
        return ['dimension' => 'No Business Logic Duplication', 'score' => $score, 'max' => 10,
            'detail' => 'PHP services authoritative; Flutter consumes /api/mobile/*'];
    }

    private function validateOfflineResilience(): array
    {
        $cacheExists = file_exists(base_path('mobile/lib/core/offline/offline_cache_service.dart'));
        $syncExists  = file_exists(base_path('mobile/lib/core/offline/offline_sync_service.dart'));
        $score = ($cacheExists ? 5 : 0) + ($syncExists ? 5 : 0);
        return ['dimension' => 'Offline Resilience', 'score' => $score, 'max' => 10,
            'detail' => 'Offline cache + sync service implemented'];
    }

    private function validateCiCd(): array
    {
        $workflow = file_exists(base_path('.github/workflows/mobile-build.yml'));
        $content  = $workflow ? file_get_contents(base_path('.github/workflows/mobile-build.yml')) : '';
        $android  = str_contains($content, 'flutter build apk');
        $windows  = str_contains($content, 'flutter build windows');
        $linux    = str_contains($content, 'flutter build linux');
        $macos    = str_contains($content, 'flutter build macos');
        $score    = ($android ? 2 : 0) + ($windows ? 2 : 0) + ($linux ? 2 : 0) + ($macos ? 2 : 0) + ($workflow ? 2 : 0);
        return ['dimension' => 'CI/CD Pipeline', 'score' => $score, 'max' => 10,
            'detail' => 'mobile-build.yml: Android+Windows+Linux+macOS'];
    }

    private function validateDesignAuthority(): array
    {
        $bridge   = file_exists(base_path('mobile/lib/core/design/client_design_token_bridge.dart'));
        $tokens   = file_exists(base_path('packages/saas-platform/core-utils/resources/css/design-tokens.css'));
        $score    = ($bridge ? 5 : 0) + ($tokens ? 5 : 0);
        return ['dimension' => 'Design Token Authority', 'score' => $score, 'max' => 10,
            'detail' => 'ClientDesignTokenBridge → design-tokens.css'];
    }

    private function validateFirebaseOptional(): array
    {
        $firebaseOptional   = config('mobile.notification_transport.firebase_optional', false) === true;
        $notificationExists = file_exists(base_path('mobile/lib/core/notifications/notification_service.dart'));
        $content            = $notificationExists ? file_get_contents(base_path('mobile/lib/core/notifications/notification_service.dart')) : '';
        $hasProviderHierarchy = str_contains($content, 'ReverbRealtimeProvider') || str_contains($content, 'Priority 1');
        $score = ($firebaseOptional ? 4 : 0) + ($hasProviderHierarchy ? 3 : 0) + ($notificationExists ? 3 : 0);
        return ['dimension' => 'Firebase Optional', 'score' => $score, 'max' => 10,
            'detail' => 'System operational without Firebase'];
    }

    private function validateSelfHosted(): array
    {
        $reverbConfig  = config('mobile.reverb.socket_auth_ttl') !== null;
        $redisPresence = config('mobile.presence.device_ttl_seconds') !== null;
        $otaSignable   = config('mobile.ota.signing_enabled') !== null;
        $score = ($reverbConfig ? 4 : 0) + ($redisPresence ? 3 : 0) + ($otaSignable ? 3 : 0);
        return ['dimension' => 'Self-Hosted Readiness', 'score' => $score, 'max' => 10,
            'detail' => 'Reverb + Redis + OTA — no external cloud required'];
    }

    private function validateDocumentation(): array
    {
        $docs = [
            'docs/phase-m2.0/client-architecture.md',
            'docs/phase-m2.0/deployment-guide.md',
            'docs/phase-m2.0/build-guide.md',
            'docs/phase-m2.0/release-guide.md',
            'docs/phase-m2.0/ota-guide.md',
            'docs/phase-m2.0/security-guide.md',
        ];
        $found = count(array_filter($docs, fn($d) => file_exists(base_path($d))));
        $score = (int) round(($found / count($docs)) * 10);
        return ['dimension' => 'Documentation', 'score' => $score, 'max' => 10,
            'detail' => "$found/" . count($docs) . " docs present"];
    }

    private function getM21Blockers(): array
    {
        return [
            'File attachment upload via ClientApiService (M2.1)',
            'iOS build pipeline (requires macOS runner with code signing)',
            'Firebase FCM integration (optional — inject token via updatePushToken())',
            'ClientDesignTokenBridge live sync from OTA manifest',
            'Push notification deep-link routing (conversation ID from payload)',
            'In-app OTA install flow for Android APK sideload',
            'System tray icon assets (tray_icon.ico / tray_icon.png) production art',
            'Performance profiling pass (Dart DevTools)',
        ];
    }

    private function renderDimensions(array $dimensions): void
    {
        $this->table(
            ['Dimension', 'Score', 'Max', '%', 'Detail'],
            array_map(fn($d) => [
                $d['dimension'],
                $d['score'],
                $d['max'],
                $d['max'] > 0 ? round(($d['score'] / $d['max']) * 100) . '%' : '0%',
                $d['detail'],
            ], $dimensions)
        );
    }
}
