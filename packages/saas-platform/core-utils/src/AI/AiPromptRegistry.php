<?php

namespace SaaSPlatform\CoreUtils\AI;

class AiPromptRegistry
{
    private array $prompts = [];

    public function register(string $key, string $template, array $meta = []): void
    {
        $this->prompts[$key] = ['template' => $template, 'meta' => $meta];
    }

    public function resolve(string $key, array $variables = []): string
    {
        $entry = $this->prompts[$key] ?? null;
        if (! $entry) {
            throw new \InvalidArgumentException("AI prompt not registered: {$key}");
        }
        $template = $entry['template'];
        foreach ($variables as $var => $value) {
            $template = str_replace('{{' . $var . '}}', (string) $value, $template);
        }
        return $template;
    }

    public function has(string $key): bool
    {
        return isset($this->prompts[$key]);
    }

    public function all(): array
    {
        return array_keys($this->prompts);
    }

    public function getMeta(string $key): array
    {
        return $this->prompts[$key]['meta'] ?? [];
    }

    protected function loadDefaults(): void
    {
        $this->register('form.suggest_fields',
            'Given the form title "{{title}}" and description "{{description}}", suggest 5-8 appropriate form fields as a JSON array. Each field: {type, label, required, validation}.',
            ['domain' => 'forms', 'output' => 'json']
        );

        $this->register('form.suggest_validation',
            'For a form field labeled "{{label}}" of type "{{type}}", suggest appropriate validation rules as a JSON array.',
            ['domain' => 'forms', 'output' => 'json']
        );

        $this->register('form.accessibility_check',
            'Review this form structure and identify accessibility issues: {{form_json}}. Return JSON with issues and suggestions.',
            ['domain' => 'forms', 'output' => 'json']
        );

        $this->register('ticket.suggest_reply',
            'As a customer support agent, draft a professional reply to this ticket: Subject: {{subject}}\nMessage: {{message}}\nContext: {{context}}. Keep it concise, helpful, and empathetic.',
            ['domain' => 'tickets', 'output' => 'text']
        );

        $this->register('ticket.categorize',
            'Categorize this support ticket. Ticket: {{content}}. Return JSON with: {category, priority, urgency, sentiment, suggested_tags}.',
            ['domain' => 'tickets', 'output' => 'json']
        );

        $this->register('ticket.sla_risk',
            'Given ticket details: priority={{priority}}, age={{age_hours}}h, last_response={{last_response_hours}}h, status={{status}}. Assess SLA breach risk. Return JSON: {risk_level, risk_score, recommended_action}.',
            ['domain' => 'tickets', 'output' => 'json']
        );

        $this->register('ticket.duplicate_check',
            'Compare these two support tickets and assess if they describe the same issue:\nTicket A: {{ticket_a}}\nTicket B: {{ticket_b}}\nReturn JSON: {is_duplicate, confidence, reason}.',
            ['domain' => 'tickets', 'output' => 'json']
        );

        $this->register('automation.suggest_workflow',
            'Based on this form/ticket configuration: {{config}}, suggest 3 automation workflows. Return JSON array: [{trigger, conditions, actions, description}].',
            ['domain' => 'automation', 'output' => 'json']
        );
    }

    public function __construct()
    {
        $this->loadDefaults();
    }
}
