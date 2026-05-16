<?php

namespace App\Services\AI;

use Exception;

class ModelRouter
{
    protected DeepSeekClient $deepseek;
    protected LocalAIClient $local;

    public function __construct(
        DeepSeekClient $deepseek,
        LocalAIClient $local
    ) {
        $this->deepseek = $deepseek;
        $this->local = $local;
    }

    public function ask(string $prompt): string
    {
        try {

            $response = $this->deepseek->ask($prompt);

            if (!empty($response)) {
                return $response;
            }
        } catch (Exception $e) {

            if ($this->shouldFallback($e)) {
                return $this->local->ask($prompt);
            }

            throw $e;
        }

        return $this->local->ask($prompt);
    }

    protected function shouldFallback(Exception $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, '402')
            || str_contains($message, '429')
            || str_contains($message, '500')
            || str_contains($message, 'timeout');
    }
}
