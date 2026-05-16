<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class DeepSeekClient
{
    protected string $endpoint = 'https://api.deepseek.com/chat/completions';

    public function ask(string $prompt): string
    {
        $response = Http::timeout(60)
            ->withToken(config('services.deepseek.key'))
            ->post($this->endpoint, [
                'model' => 'deepseek-chat',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

        if (!$response->successful()) {
            throw new \Exception(
                "DeepSeek HTTP Error {$response->status()}: " . $response->body()
            );
        }

        $json = $response->json();

        if (!$json) {
            throw new \Exception("DeepSeek returned empty response: " . $response->body());
        }

        if (isset($json['error'])) {
            throw new \Exception("DeepSeek API Error: " . $json['error']['message']);
        }

        $content = data_get($json, 'choices.0.message.content');

        if (!$content) {
            throw new \Exception("Invalid DeepSeek format: " . json_encode($json));
        }

        return $content;
    }
}
