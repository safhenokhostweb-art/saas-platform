<?php
namespace App\Services\AI;

class RuleAgent
{
    public function __construct(
        protected DeepSeekClient $client,
        protected RuleDetector $detector,
        protected WorkspaceResolver $resolver,
        protected RuleWriter $writer
    ) {}

    public function generateRule(string $prompt): ?string
    {
        $response = $this->client->ask($prompt);

        if (!$this->detector->isRule($response)) {
            return null;
        }

        $target = $this->resolver->resolve($response);

        return $this->writer->save($response, $target);
    }
}
