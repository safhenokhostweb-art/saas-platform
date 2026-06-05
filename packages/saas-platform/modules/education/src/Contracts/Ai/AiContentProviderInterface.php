<?php

namespace SaaSPlatform\Modules\Education\Contracts\Ai;

use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Exceptions\AiProviderException;

/**
 * Single-method contract for every AI content provider.
 *
 * Module code depends only on this interface — no provider-specific imports
 * may appear outside of src/Ai/Providers/.
 */
interface AiContentProviderInterface
{
    /**
     * Generate content for the given job.
     *
     * @throws AiProviderException  when the provider cannot produce a result.
     */
    public function generate(ContentGenerationJobDto $job): GeneratedContentDto;

    /**
     * Returns true when this provider is actually capable of making calls.
     * The NullAiContentProvider always returns false.
     */
    public function isEnabled(): bool;

    /**
     * Short identifier used in analytics (e.g. 'null', 'claude', 'openai').
     */
    public function providerName(): string;
}
