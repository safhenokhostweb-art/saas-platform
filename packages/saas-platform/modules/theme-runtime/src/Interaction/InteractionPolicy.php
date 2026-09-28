<?php

namespace SaaSPlatform\ThemeRuntime\Interaction;

/** Deny-by-default authorization for all interaction side-effect targets. */
final class InteractionPolicy
{
    private array $apiHosts;
    private array $interactionHosts;

    public function __construct(array $apiHosts = [], array $interactionHosts = [])
    {
        $this->apiHosts = $this->normalizeHosts($apiHosts);
        $this->interactionHosts = $this->normalizeHosts($interactionHosts);
    }

    public function allows(InteractionEvent $event): bool
    {
        $target = $event->payload['target'] ?? null;
        if (!is_string($target) || $target === '' || preg_match('/[\x00-\x1F\\\\]/', $target) === 1) {
            return false;
        }

        return match ($event->actionTrigger) {
            'call_api' => $this->allowsHttpsHost($target, array_unique([...$this->apiHosts, ...$this->interactionHosts])),
            'navigate' => $this->allowsNavigation($target),
            'toggle_element', 'open_modal', 'scroll_to' => preg_match('/^#?[A-Za-z][A-Za-z0-9_:\\-]{0,127}$/', $target) === 1,
            default => false,
        };
    }

    /** @return list<string> */
    public function apiHosts(): array
    {
        return $this->apiHosts;
    }

    /** @return list<string> */
    public function interactionHosts(): array
    {
        return $this->interactionHosts;
    }

    private function allowsNavigation(string $target): bool
    {
        if (preg_match('{^/(?!/)[A-Za-z0-9._~!$&\'()*+,;=:@%/?#-]*$}', $target) === 1) {
            return true;
        }
        return $this->allowsHttpsHost($target, array_unique([...$this->interactionHosts, ...$this->apiHosts]));
    }

    private function allowsHttpsHost(string $target, array $hosts): bool
    {
        $parts = parse_url($target);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        return in_array(strtolower(rtrim((string) $parts['host'], '.')), $hosts, true);
    }

    private function normalizeHosts(array $hosts): array
    {
        $safe = [];
        foreach ($hosts as $host) {
            $host = is_string($host) ? strtolower(rtrim(trim($host), '.')) : '';
            if ($host !== '' && preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $host) === 1) {
                $safe[] = $host;
            }
        }
        return array_values(array_unique($safe));
    }
}
