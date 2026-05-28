<?php

namespace SaaSPlatform\CoreUtils\AI\Providers;

use SaaSPlatform\CoreUtils\AI\Contracts\AiProviderInterface;
use SaaSPlatform\CoreUtils\AI\Contracts\AiCompletionResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIProvider implements AiProviderInterface
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private string $modelAdvanced;
    private int    $maxTokens;
    private float  $temperature;
    private int    $timeout;

    public function __construct()
    {
        $this->apiKey        = (string) (config('ai.openai.api_key') ?? '');
        $this->baseUrl       = rtrim((string) (config('ai.openai.base_url') ?? 'https://api.openai.com/v1'), '/');
        $this->model         = (string) (config('ai.openai.model') ?? 'gpt-4o-mini');
        $this->modelAdvanced = (string) (config('ai.openai.model_advanced') ?? 'gpt-4o');
        $this->maxTokens     = (int)    (config('ai.openai.max_tokens') ?? 2000);
        $this->temperature   = (float)  (config('ai.openai.temperature') ?? 0.7);
        $this->timeout       = (int)    (config('ai.openai.timeout') ?? 30);
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function isAvailable(): bool
    {
        return config('ai.openai.enabled', false)
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
            return AiCompletionResult::failure('OpenAI provider not available');
        }

        try {
            $model = $options['advanced'] ?? false ? $this->modelAdvanced : $this->model;

            $payload = [
                'model'       => $options['model'] ?? $model,
                'messages'    => $this->normalizeMessages($messages),
                'max_tokens'  => $options['max_tokens'] ?? $this->maxTokens,
                'temperature' => $options['temperature'] ?? $this->temperature,
            ];

            if (! empty($options['json_mode'])) {
                $payload['response_format'] = ['type' => 'json_object'];
            }

            if (! empty($options['system'])) {
                array_unshift($payload['messages'], ['role' => 'system', 'content' => $options['system']]);
            }

            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/chat/completions", $payload);

            if (! $response->successful()) {
                $errorBody = $response->json('error.message', 'Unknown OpenAI error');
                Log::warning('OpenAI API error', ['status' => $response->status(), 'error' => $errorBody]);
                return AiCompletionResult::failure("OpenAI error: {$errorBody}");
            }

            $data  = $response->json();
            $text  = $data['choices'][0]['message']['content'] ?? '';
            $usage = $data['usage'] ?? [];

            return AiCompletionResult::success($text, [
                'provider'        => 'openai',
                'model'           => $data['model'] ?? $model,
                'prompt_tokens'   => $usage['prompt_tokens'] ?? 0,
                'completion_tokens' => $usage['completion_tokens'] ?? 0,
                'total_tokens'    => $usage['total_tokens'] ?? 0,
                'finish_reason'   => $data['choices'][0]['finish_reason'] ?? 'stop',
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('OpenAI connection failed', ['error' => $e->getMessage()]);
            return AiCompletionResult::failure('OpenAI connection timeout');
        } catch (\Throwable $e) {
            Log::error('OpenAI provider exception', ['error' => $e->getMessage()]);
            return AiCompletionResult::failure('OpenAI provider error: ' . $e->getMessage());
        }
    }

    public function embed(string $text): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/embeddings", [
                    'model' => 'text-embedding-3-small',
                    'input' => $text,
                ]);

            if ($response->successful()) {
                return $response->json('data.0.embedding', []);
            }
        } catch (\Throwable) {}

        return [];
    }

    public function getCapabilities(): array
    {
        return ['chat', 'completion', 'embeddings', 'json_mode', 'summarization', 'classification'];
    }

    private function normalizeMessages(array $messages): array
    {
        return array_map(function ($msg) {
            if (is_string($msg)) {
                return ['role' => 'user', 'content' => $msg];
            }
            return ['role' => $msg['role'] ?? 'user', 'content' => $msg['content'] ?? ''];
        }, $messages);
    }
}
