<?php

namespace App\Services\AI;

class WorkspaceResolver
{
    protected array $map;

    public function __construct()
    {
        $this->map = require base_path('../ai-governance/workspace_map.php');
    }

    public function resolve(string $content): string
    {
        if (str_contains($content, 'saas-platform')) {
            return $this->map['saas'];
        }

        if (str_contains($content, 'customer-site')) {
            return $this->map['customer'];
        }

        return $this->map['shared'];
    }
}
