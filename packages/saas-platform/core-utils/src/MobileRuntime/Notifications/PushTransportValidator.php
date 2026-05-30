<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Notifications;

use Illuminate\Support\Facades\Config;

class PushTransportValidator
{
    public const STATUS_DISABLED    = 'disabled';
    public const STATUS_CONFIGURED  = 'configured';
    public const STATUS_HEALTHY     = 'healthy';

    public function validate(): array
    {
        $fcm  = $this->validateFcm();
        $apns = $this->validateApns();

        $anyConfigured = $fcm['configured'] || $apns['configured'];
        $overall = $anyConfigured ? self::STATUS_CONFIGURED : self::STATUS_DISABLED;

        $score = 0;
        if ($fcm['configured'])  $score += 50;
        if ($apns['configured']) $score += 50;

        return [
            'status'           => $overall,
            'score'            => $score,
            'production_ready' => $score >= 50,
            'fcm'              => $fcm,
            'apns'             => $apns,
            'message'          => $this->overallMessage($fcm, $apns),
            'authority_note'   => 'FCM/APNs act as transport carriers only — all push authority remains on platform',
        ];
    }

    public function validateFcm(): array
    {
        $key       = Config::get('mobile.fcm_server_key', '');
        $projectId = Config::get('mobile.fcm_project_id', '');
        $configured = ! empty($key) && ! empty($projectId);

        return [
            'transport'    => 'fcm',
            'platform'     => 'android',
            'configured'   => $configured,
            'has_key'      => ! empty($key),
            'has_project'  => ! empty($projectId),
            'status'       => $configured ? self::STATUS_CONFIGURED : self::STATUS_DISABLED,
            'message'      => $configured
                ? 'FCM configured — Android push transport active'
                : $this->fcmMissingMessage($key, $projectId),
        ];
    }

    public function validateApns(): array
    {
        $keyId    = Config::get('mobile.apns_key_id', '');
        $teamId   = Config::get('mobile.apns_team_id', '');
        $bundle   = Config::get('mobile.apns_bundle_id', '');
        $configured = ! empty($keyId) && ! empty($teamId) && ! empty($bundle);

        return [
            'transport'    => 'apns',
            'platform'     => 'ios',
            'configured'   => $configured,
            'status'       => $configured ? self::STATUS_CONFIGURED : self::STATUS_DISABLED,
            'message'      => $configured
                ? 'APNs configured — iOS push transport active'
                : 'APNs not configured — iOS push disabled (set MOBILE_APNS_KEY_ID, MOBILE_APNS_TEAM_ID, MOBILE_APNS_BUNDLE_ID)',
        ];
    }

    public function getChecks(): array
    {
        $result = $this->validate();
        return [
            [
                'name'    => 'FCM Transport (Android)',
                'status'  => $result['fcm']['configured'] ? 'pass' : 'warn',
                'message' => $result['fcm']['message'],
            ],
            [
                'name'    => 'APNs Transport (iOS)',
                'status'  => $result['apns']['configured'] ? 'pass' : 'warn',
                'message' => $result['apns']['message'],
            ],
            [
                'name'    => 'Transport Authority',
                'status'  => 'pass',
                'message' => $result['authority_note'],
            ],
        ];
    }

    private function overallMessage(array $fcm, array $apns): string
    {
        $enabled = array_filter([$fcm['configured'] ? 'FCM' : null, $apns['configured'] ? 'APNs' : null]);
        if (empty($enabled)) {
            return 'No push transport configured — push notifications disabled';
        }
        return 'Push transport active: ' . implode(', ', $enabled);
    }

    private function fcmMissingMessage(string $key, string $projectId): string
    {
        $missing = [];
        if (empty($key))       $missing[] = 'MOBILE_FCM_SERVER_KEY';
        if (empty($projectId)) $missing[] = 'MOBILE_FCM_PROJECT_ID';
        return 'FCM not configured — missing: ' . implode(', ', $missing);
    }
}
