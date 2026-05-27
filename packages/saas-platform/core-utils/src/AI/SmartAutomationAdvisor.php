<?php

namespace SaaSPlatform\CoreUtils\AI;

class SmartAutomationAdvisor
{
    public function __construct(
        private readonly AiSuggestionEngine $engine
    ) {}

    public function analyzeForOpportunities(string $entityType, array $config): array
    {
        $opportunities = [];

        if ($entityType === 'form') {
            $opportunities = array_merge($opportunities, $this->analyzeFormOpportunities($config));
        } elseif ($entityType === 'ticket') {
            $opportunities = array_merge($opportunities, $this->analyzeTicketOpportunities($config));
        }

        if ($this->engine->isAvailable()) {
            $aiSuggestions = $this->engine->suggestJson('automation.suggest_workflow', [
                'config' => json_encode(['type' => $entityType, 'config' => $config]),
            ]);
            foreach ($aiSuggestions as $suggestion) {
                $opportunities[] = array_merge($suggestion, ['source' => 'ai']);
            }
        }

        return $opportunities;
    }

    public function getSupportedTriggers(): array
    {
        return [
            'form.submitted'        => 'فرم ارسال شد',
            'ticket.created'        => 'تیکت ایجاد شد',
            'ticket.replied'        => 'پاسخ به تیکت',
            'ticket.status_changed' => 'تغییر وضعیت تیکت',
            'ticket.sla_breached'   => 'نقض SLA',
            'ticket.assigned'       => 'تیکت اختصاص یافت',
            'user.registered'       => 'کاربر جدید',
        ];
    }

    public function getSupportedActions(): array
    {
        return [
            'send_email'        => 'ارسال ایمیل',
            'send_notification' => 'ارسال اعلان',
            'assign_ticket'     => 'اختصاص تیکت',
            'change_status'     => 'تغییر وضعیت',
            'add_tag'           => 'افزودن برچسب',
            'escalate'          => 'ارجاع به سطح بالاتر',
            'webhook'           => 'ارسال Webhook',
        ];
    }

    public function validateWorkflow(array $workflow): array
    {
        $errors = [];

        if (empty($workflow['trigger'])) {
            $errors[] = 'trigger required';
        }
        if (empty($workflow['actions'])) {
            $errors[] = 'at least one action required';
        }
        foreach ($workflow['actions'] ?? [] as $i => $action) {
            if (empty($action['type'])) {
                $errors[] = "action #{$i}: type required";
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    private function analyzeFormOpportunities(array $config): array
    {
        $opportunities = [];
        $fields        = $config['fields'] ?? [];
        $hasEmail      = ! empty(array_filter($fields, fn ($f) => $f['type'] === 'email'));

        if ($hasEmail && empty($config['has_confirmation_email'])) {
            $opportunities[] = [
                'trigger'     => 'form.submitted',
                'conditions'  => [],
                'actions'     => [['type' => 'send_email', 'template' => 'form_confirmation']],
                'description' => 'ارسال ایمیل تایید خودکار پس از ارسال فرم',
                'source'      => 'rule',
            ];
        }

        return $opportunities;
    }

    private function analyzeTicketOpportunities(array $config): array
    {
        $opportunities = [];

        if (empty($config['has_auto_assignment'])) {
            $opportunities[] = [
                'trigger'     => 'ticket.created',
                'conditions'  => [['field' => 'priority', 'op' => 'eq', 'value' => 'high']],
                'actions'     => [['type' => 'assign_ticket', 'rule' => 'least_loaded']],
                'description' => 'اختصاص خودکار تیکت‌های فوری به کمترین بار',
                'source'      => 'rule',
            ];
        }

        if (empty($config['has_sla_alert'])) {
            $opportunities[] = [
                'trigger'     => 'ticket.sla_breached',
                'conditions'  => [],
                'actions'     => [['type' => 'send_notification', 'target' => 'manager']],
                'description' => 'اعلان مدیر در صورت نقض SLA',
                'source'      => 'rule',
            ];
        }

        return $opportunities;
    }
}
