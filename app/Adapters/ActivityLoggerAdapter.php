<?php

namespace App\Adapters;

use App\Services\ActivityLogger;
use SaaSPlatform\CoreUtils\Contracts\Services\ActivityLoggerInterface;

class ActivityLoggerAdapter implements ActivityLoggerInterface
{
    public function log(
        string $action,
        ?string $description = null,
        ?object $subject = null,
        array $properties = []
    ): void {
        if (!class_exists(ActivityLogger::class)) {
            return;
        }

        $model = $subject instanceof \Illuminate\Database\Eloquent\Model ? $subject : null;
        ActivityLogger::log($action, $description, $model, $properties);
    }
}
