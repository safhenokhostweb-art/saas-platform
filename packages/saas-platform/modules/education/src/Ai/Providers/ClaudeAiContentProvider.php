<?php

namespace SaaSPlatform\Modules\Education\Ai\Providers;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use SaaSPlatform\Modules\Education\Ai\Prompts\PersianEducationPromptBuilder;
use SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Exceptions\AiProviderException;

/**
 * Live Claude (Anthropic) provider — calls the Messages API via Guzzle HTTP.
 *
 * Prompt construction is delegated to PersianEducationPromptBuilder (EDU-17 Phase 4).
 * No Anthropic SDK required; all I/O is plain HTTPS through guzzlehttp/guzzle.
 * No database writes. No logging. Callers own failure reporting.
 */
class ClaudeAiContentProvider implements AiContentProviderInterface
{
    private const API_ENDPOINT    = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION     = '2023-06-01';
    private const CONNECT_TIMEOUT = 10;

    public function __construct(
        private readonly string                        $apiKey         = '',
        private readonly PersianEducationPromptBuilder $promptBuilder  = new PersianEducationPromptBuilder(),
        private readonly string                        $model          = 'claude-haiku-4-5-20251001',
        private readonly int                           $maxTokens      = 2048,
        private readonly int                           $timeoutSeconds = 60,
        private readonly ?HttpClient                   $httpClient     = null,
    ) {}

    public function generate(ContentGenerationJobDto $job): GeneratedContentDto
    {
        if (! $this->isEnabled()) {
            throw new AiProviderException(
                'Claude provider disabled — EDUCATION_CLAUDE_API_KEY is not set.'
            );
        }

        $prompt = $this->promptBuilder->build($job);

        $client = $this->httpClient ?? new HttpClient([
            'timeout'         => $this->timeoutSeconds,
            'connect_timeout' => self::CONNECT_TIMEOUT,
        ]);

        try {
            $response = $client->post(self::API_ENDPOINT, [
                'headers' => [
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => self::API_VERSION,
                    'content-type'      => 'application/json',
                ],
                'json' => [
                    'model'      => $this->model,
                    'max_tokens' => $this->maxTokens,
                    'system'     => $prompt['system'],
                    'messages'   => [
                        ['role' => 'user', 'content' => $prompt['user']],
                    ],
                ],
            ]);
        } catch (ConnectException $e) {
            throw new AiProviderException(
                'Anthropic API connection timed out: ' . $e->getMessage(),
                previous: $e,
            );
        } catch (RequestException $e) {
            $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            $body   = $e->hasResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();
            throw new AiProviderException(
                "Anthropic API request failed (HTTP {$status}): {$body}",
                previous: $e,
            );
        }

        try {
            $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new AiProviderException(
                'Anthropic API response is not valid JSON: ' . $e->getMessage(),
                previous: $e,
            );
        }

        return $this->parseResponse($body);
    }

    public function isEnabled(): bool
    {
        return $this->apiKey !== '';
    }

    public function providerName(): string
    {
        return 'claude';
    }

    // -------------------------------------------------------------------------
    // Response parsing
    // -------------------------------------------------------------------------

    private function parseResponse(array $body): GeneratedContentDto
    {
        $rawText = $body['content'][0]['text'] ?? '';

        if ($rawText === '') {
            throw new AiProviderException(
                'Anthropic API returned an empty content block.'
            );
        }

        try {
            $parsed = json_decode(
                $this->extractJson($rawText),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $e) {
            throw new AiProviderException(
                'Claude response could not be parsed as JSON: ' . $e->getMessage(),
                previous: $e,
            );
        }

        return new GeneratedContentDto(
            title:           trim((string) ($parsed['title']   ?? '')),
            summary:         trim((string) ($parsed['summary'] ?? '')),
            body:            trim((string) ($parsed['body']    ?? '')),
            tags:            is_array($parsed['tags'] ?? null) ? $parsed['tags'] : [],
            confidenceScore: $this->normaliseScore($parsed['confidence_score'] ?? 70),
            provider:        $this->providerName(),
        );
    }

    /**
     * Strip markdown code fences and extract the outermost JSON object.
     * Guards against Claude wrapping output in ```json ... ```.
     */
    private function extractJson(string $text): string
    {
        $text = trim($text);

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $text, $m)) {
            return $m[1];
        }

        $start = strpos($text, '{');
        $end   = strrpos($text, '}');

        if ($start !== false && $end !== false && $end > $start) {
            return substr($text, $start, $end - $start + 1);
        }

        return $text;
    }

    /**
     * Normalise confidence score to the 0–100 range expected by GeneratedContentValidator.
     * Guards against Claude returning a 0.0–1.0 float instead of an integer 0–100.
     */
    private function normaliseScore(mixed $raw): float
    {
        $score = (float) $raw;

        if ($score > 0 && $score <= 1.0) {
            return round($score * 100, 2);
        }

        return max(0.0, min(100.0, $score));
    }
}
