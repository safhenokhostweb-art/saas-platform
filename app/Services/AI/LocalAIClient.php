<?php
namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Exception;

class LocalAIClient
{
    protected string $endpoint;
    protected string $model;

    public function __construct()
    {
        $this->endpoint = config('services.local_ai.endpoint', 'http://127.0.0.1:11434/api/generate');
        $this->model = config('services.local_ai.model', 'llama3');
    }

    public function ask(string $prompt): string
    {
        try {

            $response = Http::timeout(120)->post($this->endpoint, [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
            ]);

            if (!$response->successful()) {
                throw new Exception("Local AI HTTP Error: " . $response->status());
            }

            $data = $response->json();

            if (!isset($data['response'])) {
                throw new Exception("Invalid Local AI response format.");
            }

            return trim($data['response']);
        } catch (\Throwable $e) {

            throw new Exception("Local AI failed: " . $e->getMessage());
        }
    }
}
