<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts;

interface MotionAuditLoggerContract
{
    public function record(string $event, array $context = []): void;
}
