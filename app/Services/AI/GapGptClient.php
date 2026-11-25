<?php

namespace App\Services\AI;

use App\Helpers\PlatformSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * کلاینت انحصاری سرویس هوش مصنوعی GapGPT (OpenAI-compatible).
 * کلید API از تنظیمات رمزنگاری‌شده پنل ادمین (با fallback به .env GAPGPT_API_KEY) خوانده می‌شود.
 */
class GapGptClient
{
    public const DEFAULT_BASE_URL = 'https://api.gapgpt.app/v1';
    public const DEFAULT_MODEL    = 'gpt-4o-mini';

    public function config(): array
    {
        return PlatformSettings::gapGptConfig();
    }

    public function isEnabled(): bool
    {
        $cfg = $this->config();

        return !empty($cfg['api_key']);
    }

    public function getModel(): string
    {
        return $this->config()['model'] ?: self::DEFAULT_MODEL;
    }

    public function getBaseUrl(): string
    {
        return rtrim($this->config()['base_url'] ?: self::DEFAULT_BASE_URL, '/');
    }

    /**
     * ارسال درخواست متنی (System + User) به GapGPT.
     */
    public function ask(
        string $prompt,
        string $system = '',
        bool $jsonMode = false,
        ?string $model = null,
        int $maxTokens = 4096,
        int $timeout = 60,
        float $temperature = 0.7,
    ): string {
        $cfg = $this->config();
        $apiKey = (string) ($cfg['api_key'] ?? '');
        if ($apiKey === '') {
            throw new \RuntimeException('کلید API سرویس GapGPT تنظیم نشده است.');
        }

        $messages = [];
        if ($system !== '') {
            $messages[] = ['role' => 'system', 'content' => $system];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $payload = [
            'model'       => $model ?: ($cfg['model'] ?: self::DEFAULT_MODEL),
            'messages'    => $messages,
            'max_tokens'  => $maxTokens,
            'temperature' => $temperature,
        ];
        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $endpoint = rtrim((string) ($cfg['base_url'] ?: self::DEFAULT_BASE_URL), '/') . '/chat/completions';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout($timeout)->post($endpoint, $payload);

        if (!$response->successful()) {
            Log::warning('GapGPT HTTP error', ['status' => $response->status()]);
            throw new \RuntimeException('خطای سرویس GapGPT (کد ' . $response->status() . ')');
        }

        $content = trim((string) $response->json('choices.0.message.content', ''));
        if ($content === '') {
            throw new \RuntimeException('پاسخ خالی از سرویس GapGPT دریافت شد.');
        }

        return $content;
    }

    /**
     * ارسال تصویر (Base64) + پرامپت متنی به مدل Vision در GapGPT.
     */
    public function askWithImage(
        string $prompt,
        string $base64Image,
        string $mimeType = 'image/png',
        string $system = '',
        ?string $model = null,
        int $maxTokens = 4096,
        int $timeout = 90,
    ): string {
        $cfg = $this->config();
        $apiKey = (string) ($cfg['api_key'] ?? '');
        if ($apiKey === '') {
            throw new \RuntimeException('کلید API سرویس GapGPT تنظیم نشده است.');
        }

        $messages = [];
        if ($system !== '') {
            $messages[] = ['role' => 'system', 'content' => $system];
        }
        $messages[] = [
            'role'    => 'user',
            'content' => [
                ['type' => 'text', 'text' => $prompt],
                [
                    'type'      => 'image_url',
                    'image_url' => ['url' => "data:{$mimeType};base64,{$base64Image}"],
                ],
            ],
        ];

        $endpoint = rtrim((string) ($cfg['base_url'] ?: self::DEFAULT_BASE_URL), '/') . '/chat/completions';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout($timeout)->post($endpoint, [
            'model'      => $model ?: ($cfg['model'] ?: self::DEFAULT_MODEL),
            'messages'   => $messages,
            'max_tokens' => $maxTokens,
        ]);

        if (!$response->successful()) {
            Log::warning('GapGPT Vision HTTP error', ['status' => $response->status()]);
            throw new \RuntimeException('خطای تحلیل تصویر GapGPT (کد ' . $response->status() . ')');
        }

        $content = trim((string) $response->json('choices.0.message.content', ''));
        if ($content === '') {
            throw new \RuntimeException('پاسخ خالی از GapGPT Vision دریافت شد.');
        }

        return $content;
    }
}
