<?php

namespace SaaSPlatform\CoreUtils\Search;

class RuntimeQuickActionsService
{
    private array $actionGroups = [];

    public function registerGroup(string $group, string $label, string $icon = ''): void
    {
        $this->actionGroups[$group] = [
            'id'      => $group,
            'label'   => $label,
            'icon'    => $icon,
            'actions' => [],
        ];
    }

    public function registerAction(string $group, string $key, array $action): void
    {
        if (! isset($this->actionGroups[$group])) {
            $this->registerGroup($group, $group);
        }

        $this->actionGroups[$group]['actions'][$key] = array_merge([
            'key'         => $key,
            'label'       => '',
            'description' => '',
            'icon'        => '',
            'shortcut'    => null,
            'url'         => null,
            'method'      => 'GET',
            'confirm'     => false,
            'permissions' => [],
            'badge'       => null,
        ], $action);
    }

    public function getActionsForContext(string $context, array $permissions = []): array
    {
        $groups  = [];
        foreach ($this->actionGroups as $group => $data) {
            $actions = array_filter($data['actions'], function ($action) use ($permissions) {
                if (empty($action['permissions'])) {
                    return true;
                }
                return ! empty(array_intersect($action['permissions'], $permissions));
            });

            if (! empty($actions)) {
                $groups[] = array_merge($data, ['actions' => array_values($actions)]);
            }
        }
        return $groups;
    }

    public function getQuickActions(string $context = 'global', array $permissions = []): array
    {
        return array_merge(...array_map(
            fn ($g) => array_map(fn ($a) => array_merge($a, ['group' => $g['id'], 'group_label' => $g['label']]), $g['actions']),
            $this->getActionsForContext($context, $permissions)
        ));
    }

    public function loadDefaults(): void
    {
        $this->registerGroup('tickets',  'تیکت‌ها',  '🎫');
        $this->registerGroup('forms',    'فرم‌ها',   '📋');
        $this->registerGroup('system',   'سیستم',    '⚙️');

        // Ticket actions
        $this->registerAction('tickets', 'ticket_new', [
            'label'       => 'تیکت جدید',
            'description' => 'ایجاد یک تیکت پشتیبانی جدید',
            'shortcut'    => 'n t',
            'url'         => '/admin/tickets/create',
            'icon'        => 'plus',
        ]);
        $this->registerAction('tickets', 'ticket_open', [
            'label'       => 'تیکت‌های باز',
            'description' => 'مشاهده تمام تیکت‌های باز',
            'url'         => '/admin/tickets?status=open',
            'icon'        => 'inbox',
            'badge'       => 'dynamic:open_ticket_count',
        ]);
        $this->registerAction('tickets', 'ticket_analytics', [
            'label'       => 'داشبورد پشتیبانی',
            'description' => 'آنالیتیکس عملیاتی تیکت‌ها',
            'url'         => '/admin/tickets/analytics',
            'icon'        => 'chart',
        ]);

        // Form actions
        $this->registerAction('forms', 'form_new', [
            'label'       => 'فرم جدید',
            'description' => 'ساخت فرم جدید',
            'shortcut'    => 'n f',
            'url'         => '/admin/forms/create',
            'icon'        => 'plus',
        ]);
        $this->registerAction('forms', 'form_submissions', [
            'label'       => 'پاسخ‌های فرم',
            'description' => 'مشاهده آخرین ارسال‌ها',
            'url'         => '/admin/forms/submissions',
            'icon'        => 'inbox',
        ]);

        // System actions
        $this->registerAction('system', 'settings', [
            'label'       => 'تنظیمات',
            'url'         => '/admin/settings',
            'icon'        => 'settings',
            'permissions' => ['admin'],
        ]);
    }

    public function resolveAction(string $key): ?array
    {
        foreach ($this->actionGroups as $group) {
            if (isset($group['actions'][$key])) {
                return $group['actions'][$key];
            }
        }
        return null;
    }
}
