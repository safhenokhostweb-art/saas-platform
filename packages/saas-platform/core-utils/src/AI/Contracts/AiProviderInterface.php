<?php

namespace SaaSPlatform\CoreUtils\AI\Contracts;

interface AiProviderInterface
{
    public function getName(): string;

    public function isAvailable(): bool;

    public function complete(string $prompt, array $options = []): AiCompletionResult;

    public function completeChat(array $messages, array $options = []): AiCompletionResult;

    public function embed(string $text): array;

    public function getCapabilities(): array;
}
