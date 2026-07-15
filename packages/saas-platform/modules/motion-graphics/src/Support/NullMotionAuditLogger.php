<?php

namespace SaaSPlatform\Modules\MotionGraphics\Support;

use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAuditLoggerContract;

final class NullMotionAuditLogger implements MotionAuditLoggerContract
{
    public function record(string $event, array $context = []): void {}
}
