<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Icons\IconRegistry;

class IconListCommand extends Command
{
    protected $signature   = 'icons:list {--search= : Filter icons by name}';
    protected $description = 'List all available SaaS Platform duotone icons';

    public function handle(): int
    {
        $icons  = IconRegistry::all();
        $search = $this->option('search');

        if ($search) {
            $icons = array_values(array_filter($icons, fn($n) => str_contains($n, $search)));
        }

        $this->components->info(sprintf(
            'SaaS Platform Icon Registry — %d icon%s%s',
            count($icons),
            count($icons) === 1 ? '' : 's',
            $search ? " matching \"{$search}\"" : ''
        ));

        $rows = [];
        foreach (array_chunk($icons, 4) as $row) {
            $rows[] = array_pad($row, 4, '');
        }

        $this->table(['Col 1', 'Col 2', 'Col 3', 'Col 4'], $rows);

        $this->line('');
        $this->line('  Usage: <x-core-ui::icon name="<name>" class="w-5 h-5 text-blue-600" />');

        return self::SUCCESS;
    }
}
