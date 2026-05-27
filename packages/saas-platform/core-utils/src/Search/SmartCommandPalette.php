<?php

namespace SaaSPlatform\CoreUtils\Search;

class SmartCommandPalette
{
    private array $commands      = [];
    private array $recentHistory = [];

    public function registerCommand(string $key, array $command): void
    {
        $this->commands[$key] = array_merge([
            'key'         => $key,
            'label'       => '',
            'description' => '',
            'shortcut'    => null,
            'icon'        => null,
            'category'    => 'general',
            'action'      => 'navigate',
            'url'         => null,
            'handler'     => null,
            'permissions' => [],
            'contexts'    => [],
        ], $command);
    }

    public function getCommands(string $query = '', array $context = []): array
    {
        $commands = array_values($this->commands);

        if (! empty($context['permissions'])) {
            $commands = array_filter($commands, function ($cmd) use ($context) {
                if (empty($cmd['permissions'])) {
                    return true;
                }
                return (bool) array_intersect($cmd['permissions'], $context['permissions']);
            });
        }

        if ($query) {
            $lower    = mb_strtolower($query);
            $commands = array_filter($commands, fn ($cmd) =>
                str_contains(mb_strtolower($cmd['label']), $lower) ||
                str_contains(mb_strtolower($cmd['description'] ?? ''), $lower) ||
                ($cmd['shortcut'] && str_contains(mb_strtolower($cmd['shortcut']), $lower))
            );

            // Fuzzy score
            $commands = array_map(function ($cmd) use ($lower) {
                $score = 0;
                if (str_starts_with(mb_strtolower($cmd['label']), $lower)) {
                    $score += 10;
                } elseif (str_contains(mb_strtolower($cmd['label']), $lower)) {
                    $score += 5;
                }
                return array_merge($cmd, ['_score' => $score]);
            }, $commands);

            usort($commands, fn ($a, $b) => ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0));
        }

        return array_values($commands);
    }

    public function getCommandsByCategory(): array
    {
        $grouped = [];
        foreach ($this->commands as $cmd) {
            $grouped[$cmd['category']][] = $cmd;
        }
        return $grouped;
    }

    public function getRecent(string $tenantId, int|string $userId, int $limit = 5): array
    {
        $key = "cmd:recent:{$tenantId}:{$userId}";
        $ids = \Illuminate\Support\Facades\Cache::get($key, []);
        return array_filter(
            array_map(fn ($id) => $this->commands[$id] ?? null, $ids),
            fn ($c) => $c !== null
        );
    }

    public function recordUsage(string $tenantId, int|string $userId, string $commandKey): void
    {
        $key = "cmd:recent:{$tenantId}:{$userId}";
        $ids = \Illuminate\Support\Facades\Cache::get($key, []);
        $ids = array_diff($ids, [$commandKey]);
        array_unshift($ids, $commandKey);
        \Illuminate\Support\Facades\Cache::put($key, array_slice($ids, 0, 20), 86400 * 30);
    }

    public function loadDefaults(): void
    {
        $this->registerCommand('new-ticket', [
            'label'       => 'تیکت جدید',
            'description' => 'ایجاد تیکت پشتیبانی جدید',
            'shortcut'    => 'n t',
            'icon'        => 'ticket',
            'category'    => 'tickets',
            'action'      => 'navigate',
            'url'         => '/admin/tickets/create',
        ]);
        $this->registerCommand('new-form', [
            'label'       => 'فرم جدید',
            'description' => 'ساخت فرم جدید',
            'shortcut'    => 'n f',
            'icon'        => 'form',
            'category'    => 'forms',
            'action'      => 'navigate',
            'url'         => '/admin/forms/create',
        ]);
        $this->registerCommand('open-tickets', [
            'label'       => 'تیکت‌های باز',
            'description' => 'مشاهده تیکت‌های باز',
            'icon'        => 'inbox',
            'category'    => 'tickets',
            'action'      => 'navigate',
            'url'         => '/admin/tickets?status=open',
        ]);
        $this->registerCommand('form-submissions', [
            'label'       => 'پاسخ‌های فرم',
            'description' => 'مشاهده ارسال‌های فرم',
            'icon'        => 'inbox',
            'category'    => 'forms',
            'action'      => 'navigate',
            'url'         => '/admin/forms/submissions',
        ]);
        $this->registerCommand('settings', [
            'label'       => 'تنظیمات',
            'description' => 'باز کردن تنظیمات پلتفرم',
            'icon'        => 'settings',
            'category'    => 'system',
            'action'      => 'navigate',
            'url'         => '/admin/settings',
        ]);
        $this->registerCommand('dashboard', [
            'label'       => 'داشبورد',
            'description' => 'صفحه اصلی مدیریت',
            'shortcut'    => 'g d',
            'icon'        => 'home',
            'category'    => 'navigation',
            'action'      => 'navigate',
            'url'         => '/admin',
        ]);
    }
}
