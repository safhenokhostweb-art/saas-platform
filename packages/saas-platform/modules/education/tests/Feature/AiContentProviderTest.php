<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Orchestra\Testbench\TestCase;
use SaaSPlatform\Modules\Education\Ai\Providers\ClaudeAiContentProvider;
use SaaSPlatform\Modules\Education\Ai\Providers\NullAiContentProvider;
use SaaSPlatform\Modules\Education\Ai\Providers\OpenAiContentProvider;
use SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Exceptions\AiProviderException;

/**
 * EDU-15 Phase 1 — Provider Abstraction
 */
class AiContentProviderTest extends TestCase
{
    // ----- NullAiContentProvider -----

    public function test_null_provider_is_not_enabled(): void
    {
        $provider = new NullAiContentProvider();
        $this->assertFalse($provider->isEnabled());
    }

    public function test_null_provider_name_is_null(): void
    {
        $this->assertSame('null', (new NullAiContentProvider())->providerName());
    }

    public function test_null_provider_generate_returns_empty_dto(): void
    {
        $provider = new NullAiContentProvider();
        $job = $this->makeJob();
        $dto = $provider->generate($job);

        $this->assertSame('', $dto->title);
        $this->assertSame('', $dto->body);
        $this->assertSame(0.0, $dto->confidenceScore);
        $this->assertSame('null', $dto->provider);
    }

    public function test_null_provider_implements_interface(): void
    {
        $this->assertInstanceOf(AiContentProviderInterface::class, new NullAiContentProvider());
    }

    // ----- ClaudeAiContentProvider -----

    public function test_claude_provider_not_enabled_when_no_api_key(): void
    {
        $provider = new ClaudeAiContentProvider(apiKey: '');
        $this->assertFalse($provider->isEnabled());
    }

    public function test_claude_provider_enabled_when_api_key_set(): void
    {
        $provider = new ClaudeAiContentProvider(apiKey: 'sk-ant-test-key');
        $this->assertTrue($provider->isEnabled());
    }

    public function test_claude_provider_generate_throws_when_not_enabled(): void
    {
        $this->expectException(AiProviderException::class);
        (new ClaudeAiContentProvider(apiKey: ''))->generate($this->makeJob());
    }

    public function test_claude_provider_name(): void
    {
        $this->assertSame('claude', (new ClaudeAiContentProvider())->providerName());
    }

    // ----- OpenAiContentProvider -----

    public function test_openai_provider_not_enabled_when_no_api_key(): void
    {
        $provider = new OpenAiContentProvider(apiKey: '');
        $this->assertFalse($provider->isEnabled());
    }

    public function test_openai_provider_enabled_when_api_key_set(): void
    {
        $provider = new OpenAiContentProvider(apiKey: 'sk-openai-test');
        $this->assertTrue($provider->isEnabled());
    }

    public function test_openai_provider_generate_throws_when_not_enabled(): void
    {
        $this->expectException(AiProviderException::class);
        (new OpenAiContentProvider(apiKey: ''))->generate($this->makeJob());
    }

    public function test_openai_provider_name(): void
    {
        $this->assertSame('openai', (new OpenAiContentProvider())->providerName());
    }

    // ----- Provider interface contract -----

    /** @dataProvider allProviders */
    public function test_all_providers_implement_interface(AiContentProviderInterface $provider): void
    {
        $this->assertInstanceOf(AiContentProviderInterface::class, $provider);
        $this->assertIsString($provider->providerName());
        $this->assertIsBool($provider->isEnabled());
    }

    public static function allProviders(): array
    {
        return [
            'null'   => [new NullAiContentProvider()],
            'claude' => [new ClaudeAiContentProvider()],
            'openai' => [new OpenAiContentProvider()],
        ];
    }

    // -----------------------------------------------------------------------

    private function makeJob(): ContentGenerationJobDto
    {
        return new ContentGenerationJobDto(
            moduleSlug:   'invoices',
            contentType:  'articles',
            priority:     'High',
            siteId:       1,
            sourceReason: 'content_deficit',
        );
    }
}
