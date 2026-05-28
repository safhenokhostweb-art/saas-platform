<?php

namespace SaaSPlatform\CoreUtils\AI\Providers;

use SaaSPlatform\CoreUtils\AI\Contracts\AiProviderInterface;
use SaaSPlatform\CoreUtils\AI\Contracts\AiCompletionResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeProvider implements AiProviderInterface
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private string $modelAdvanced;
    private int    $maxTokens;
    private int    $timeout;
    private string $apiVersion;

    public function __construct()
    {
        $this->apiKey        = (string) (config('ai.claude.api_key') ?? '');
        $this->baseUrl       = rtrim((string) (config('ai.claude.base_url') ?? 'https://api.anthropic.com/v1'), '/');
        $this->model         = (string) (config('ai.claude.model') ?? 'claude-haiku-4-5-20251001');
        $this->modelAdvanced = (string) (config('ai.claude.model_advanced') ?? 'claude-sonnet-4-6');
        $this->maxTokens     = (int)    (config('ai.claude.max_tokens') ?? 2000);
        $this->timeout       = (int)    (config('ai.claude.timeout') ?? 30);
        $this->apiVersion    = (string) (config('ai.claude.api_version') ?? '2023-06-01');
    }

    public function getName(): string
    {
        return 'claude';
    }

    public function isAvailable(): bool
    {
        return config('ai.claude.enabled', false)
            && ! empty($this->apiKey)
            && ! config('ai.emergency.kill_switch', false);
    }

    public function complete(string $prompt, array $options = []): AiCompletionResult
    {
        return $this->completeChat(
            [['role' => 'user', 'content' => $prompt]],
            $options
        );
    }

    public function completeChat(array $messages, array $options = []): AiCompletionResult
    {
        if (! $this->isAvailable()) {
            return AiCompletionResult::failure('Claude provider not available');
        }

        try {
            $model = $options['advanced'] ?? false ? $this->modelAdvanced : $this->model;

            $normalized = $this->normalizeMessages($messages);
            $system     = $options['system'] ?? null;

            $payload = [
                'model'      => $options['model'] ?? $model,
                'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
                'messages'   => $normalized,
            ];

            if ($system) {
                $payload['system'] = $system;
            }

            if (! empty($options['json_mode'])) {
                $payload['system'] = ($system ? $system . "\n\n" : '')
                    . 'Always respond with valid JSON only. No markdown, no explanation outside the JSON.';
            }

            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => $this->apiVersion,
                'Content-Type'      => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/messages", $payload);

            if (! $response->successful()) {
                $errorBody = $response->json('error.message', 'Unknown Claude error');
                Log::warning('Claude API error', ['status' => $response->status(), 'error' => $errorBody]);
                return AiCompletionResult::failure("Claude error: {$errorBody}");
            }

            $data  = $response->json();
            $text  = $data['content'][0]['text'] ?? '';
            $usage = $data['usage'] ?? [];

            return AiCompletionResult::success($text, [
                'provider'          => 'claude',
                'model'             => $data['model'] ?? $model,
                'prompt_tokens'     => $usage['input_tokens'] ?? 0,
                'completion_tokens' => $usage['output_tokens'] ?? 0,
                'total_tokens'      => ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0),
                'stop_reason'       => $data['stop_reason'] ?? 'end_turn',
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('Claude connection failed', ['error' => $e->getMessage()]);
            return AiCompletionResult::failure('Claude connection timeout');
        } catch (\Throwable $e) {
            Log::error('Claude provider exception', ['error' => $e->getMessage()]);
            return AiCompletionResult::failure('Claude provider error: ' . $e->getMessage());
        }
    }

    public function embed(string $text): array
    {
        // Claude does not provide an embeddings API; return empty to signal fallback.
        return [];
    }

    public function getCapabilities(): array
    {
        return ['chat', 'completion', 'summarization', 'classification', 'json_mode'];
    }

    private function normalizeMessages(array $messages): array
    {
        $normalized = [];
        foreach ($messages as $msg) {
            if (is_string($msg)) {
                $normalized[] = ['role' => 'user', 'content' => $msg];
                continue;
            }
            $role    = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';
            // Claude only accepts 'user' and 'assistant' roles in messages
            if (! in_array($role, ['user', 'assistant'])) {
                continue;
            }
            $normalized[] = ['role' => $role, 'content' => $content];
        }
        return $normalized;
    }
}
