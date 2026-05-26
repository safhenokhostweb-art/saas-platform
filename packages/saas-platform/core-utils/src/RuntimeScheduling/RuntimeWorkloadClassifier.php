<?php

namespace SaaSPlatform\CoreUtils\RuntimeScheduling;

class RuntimeWorkloadClassifier
{
    public const CLASS_CRITICAL  = 'critical';
    public const CLASS_HIGH      = 'high';
    public const CLASS_NORMAL    = 'normal';
    public const CLASS_LOW       = 'low';
    public const CLASS_DEFERRED  = 'deferred';

    private const JOB_CLASS_MAP = [
        // Critical path — must never be deferred
        'payment'     => self::CLASS_CRITICAL,
        'billing'     => self::CLASS_CRITICAL,
        'auth'        => self::CLASS_CRITICAL,
        'security'    => self::CLASS_CRITICAL,
        // High priority
        'notification' => self::CLASS_HIGH,
        'alert'        => self::CLASS_HIGH,
        'webhook'      => self::CLASS_HIGH,
        // Normal
        'ticket'       => self::CLASS_NORMAL,
        'form'         => self::CLASS_NORMAL,
        'email'        => self::CLASS_NORMAL,
        // Low / deferrable
        'report'       => self::CLASS_LOW,
        'export'       => self::CLASS_LOW,
        'cleanup'      => self::CLASS_DEFERRED,
        'archive'      => self::CLASS_DEFERRED,
        'analytics'    => self::CLASS_DEFERRED,
    ];

    public function classify(string $jobClass): string
    {
        $lower = strtolower($jobClass);

        foreach (self::JOB_CLASS_MAP as $keyword => $workloadClass) {
            if (str_contains($lower, $keyword)) {
                return $workloadClass;
            }
        }

        return self::CLASS_NORMAL;
    }

    public function priority(string $workloadClass): int
    {
        return match ($workloadClass) {
            self::CLASS_CRITICAL => 100,
            self::CLASS_HIGH     => 75,
            self::CLASS_NORMAL   => 50,
            self::CLASS_LOW      => 25,
            self::CLASS_DEFERRED => 5,
            default              => 50,
        };
    }

    public function isDeferred(string $workloadClass): bool
    {
        return $workloadClass === self::CLASS_DEFERRED;
    }

    public function isCriticalPath(string $workloadClass): bool
    {
        return $workloadClass === self::CLASS_CRITICAL;
    }

    public function queueForClass(string $workloadClass): string
    {
        return match ($workloadClass) {
            self::CLASS_CRITICAL => 'high',
            self::CLASS_HIGH     => 'high',
            self::CLASS_NORMAL   => 'default',
            self::CLASS_LOW      => 'low',
            self::CLASS_DEFERRED => 'low',
            default              => 'default',
        };
    }
}
