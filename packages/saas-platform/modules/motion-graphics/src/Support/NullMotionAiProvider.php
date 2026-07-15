<?php

namespace SaaSPlatform\Modules\MotionGraphics\Support;

use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAiProviderContract;
use SaaSPlatform\Modules\MotionGraphics\Data\MotionAiRequest;
use SaaSPlatform\Modules\MotionGraphics\Data\MotionAiResult;

final class NullMotionAiProvider implements MotionAiProviderContract
{
    public function providerName(): string
    {
        return 'disabled';
    }

    public function generate(MotionAiRequest $request): MotionAiResult
    {
        return MotionAiResult::failure('AI generation is disabled.');
    }
}
