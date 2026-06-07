<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Orchestra\Testbench\TestCase;
use SaaSPlatform\Modules\Education\Ai\Prompts\PersianEducationPromptBuilder;
use SaaSPlatform\Modules\Education\Ai\Providers\ClaudeAiContentProvider;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Exceptions\AiProviderException;
use SaaSPlatform\Modules\Education\Providers\EducationServiceProvider;

/**
 * EDU-17 Phase 5 — ClaudeAiContentProvider HTTP integration tests.
 *
 * All HTTP calls are intercepted by Guzzle's MockHandler.
 * No real Anthropic API calls are made.
 */
class ClaudeProviderHttpTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [EducationServiceProvider::class];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeJob(
        string $contentType = 'articles',
        string $moduleSlug  = 'invoices',
    ): ContentGenerationJobDto {
        return new ContentGenerationJobDto(
            moduleSlug:   $moduleSlug,
            contentType:  $contentType,
            priority:     'high',
            siteId:       1,
            sourceReason: 'content_deficit',
        );
    }

    private function mockClient(array $responses): HttpClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        return new HttpClient(['handler' => $stack]);
    }

    private function anthropicOkResponse(array $payload): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'content' => [['type' => 'text', 'text' => json_encode($payload)]],
        ]));
    }

    private function validPayload(): array
    {
        return [
            'title'            => 'راهنمای کامل فاکتورها',
            'summary'          => 'این مقاله نحوه استفاده از فاکتورها را آموزش می‌دهد.',
            'body'             => '<p>' . str_repeat('محتوای آموزشی ', 25) . '</p>',
            'tags'             => ['فاکتور', 'آموزش', 'مالی'],
            'confidence_score' => 88,
        ];
    }

    private function provider(HttpClient $client, string $apiKey = 'sk-ant-test'): ClaudeAiContentProvider
    {
        return new ClaudeAiContentProvider(
            apiKey:        $apiKey,
            promptBuilder: new PersianEducationPromptBuilder(),
            httpClient:    $client,
        );
    }

    // ─── Happy path ──────────────────────────────────────────────────────────

    public function test_successful_response_returns_generated_content_dto(): void
    {
        $dto = $this->provider($this->mockClient([$this->anthropicOkResponse($this->validPayload())]))
                    ->generate($this->makeJob());

        $this->assertSame('راهنمای کامل فاکتورها', $dto->title);
        $this->assertSame('claude', $dto->provider);
    }

    public function test_summary_field_is_populated_from_response(): void
    {
        $dto = $this->provider($this->mockClient([$this->anthropicOkResponse($this->validPayload())]))
                    ->generate($this->makeJob());

        $this->assertSame('این مقاله نحوه استفاده از فاکتورها را آموزش می‌دهد.', $dto->summary);
    }

    public function test_tags_field_is_populated_as_array(): void
    {
        $dto = $this->provider($this->mockClient([$this->anthropicOkResponse($this->validPayload())]))
                    ->generate($this->makeJob());

        $this->assertSame(['فاکتور', 'آموزش', 'مالی'], $dto->tags);
    }

    public function test_confidence_score_integer_is_preserved(): void
    {
        $dto = $this->provider($this->mockClient([$this->anthropicOkResponse($this->validPayload())]))
                    ->generate($this->makeJob());

        $this->assertSame(88.0, $dto->confidenceScore);
    }

    public function test_float_confidence_score_is_normalised_to_0_100_range(): void
    {
        $payload = array_merge($this->validPayload(), ['confidence_score' => 0.88]);

        $dto = $this->provider($this->mockClient([$this->anthropicOkResponse($payload)]))
                    ->generate($this->makeJob());

        $this->assertSame(88.0, $dto->confidenceScore);
    }

    public function test_markdown_fences_are_stripped_from_response(): void
    {
        $fenced   = "```json\n" . json_encode($this->validPayload()) . "\n```";
        $response = new Response(200, [], json_encode([
            'content' => [['type' => 'text', 'text' => $fenced]],
        ]));

        $dto = $this->provider($this->mockClient([$response]))->generate($this->makeJob());

        $this->assertSame('راهنمای کامل فاکتورها', $dto->title);
    }

    // ─── Provider state ──────────────────────────────────────────────────────

    public function test_is_enabled_returns_true_when_api_key_set(): void
    {
        $provider = new ClaudeAiContentProvider(apiKey: 'sk-ant-real-key');
        $this->assertTrue($provider->isEnabled());
    }

    public function test_is_enabled_returns_false_when_api_key_empty(): void
    {
        $provider = new ClaudeAiContentProvider(apiKey: '');
        $this->assertFalse($provider->isEnabled());
    }

    public function test_provider_name_is_claude(): void
    {
        $this->assertSame('claude', (new ClaudeAiContentProvider())->providerName());
    }

    // ─── Error paths ─────────────────────────────────────────────────────────

    public function test_generate_throws_when_api_key_is_empty(): void
    {
        $this->expectException(AiProviderException::class);
        (new ClaudeAiContentProvider(apiKey: ''))->generate($this->makeJob());
    }

    public function test_connect_timeout_throws_ai_provider_exception(): void
    {
        $mock  = new MockHandler([
            new ConnectException('Connection timed out', new Request('POST', 'test')),
        ]);
        $client = new HttpClient(['handler' => HandlerStack::create($mock)]);

        $this->expectException(AiProviderException::class);
        $this->provider($client)->generate($this->makeJob());
    }

    public function test_http_4xx_response_throws_ai_provider_exception(): void
    {
        $this->expectException(AiProviderException::class);
        $this->provider($this->mockClient([new Response(429, [], '{"error":"rate_limit"}')]))
             ->generate($this->makeJob());
    }

    public function test_empty_response_text_throws_ai_provider_exception(): void
    {
        $response = new Response(200, [], json_encode([
            'content' => [['type' => 'text', 'text' => '']],
        ]));

        $this->expectException(AiProviderException::class);
        $this->provider($this->mockClient([$response]))->generate($this->makeJob());
    }
}
