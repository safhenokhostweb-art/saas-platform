<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts;

use SaaSPlatform\Modules\MotionGraphics\Data\MotionAiRequest;
use SaaSPlatform\Modules\MotionGraphics\Data\MotionAiResult;

interface MotionAiProviderContract
{
    public function providerName(): string;

    public function generate(MotionAiRequest $request): MotionAiResult;
}
