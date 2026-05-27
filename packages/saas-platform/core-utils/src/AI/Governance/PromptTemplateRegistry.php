<?php

namespace SaaSPlatform\CoreUtils\AI\Governance;

class PromptTemplateRegistry
{
    private array $templates = [];

    public function __construct()
    {
        $this->loadPhase95Templates();
    }

    public function register(string $key, string $template, array $meta = []): void
    {
        $this->templates[$key] = ['template' => $template, 'meta' => $meta];
    }

    public function resolve(string $key, array $variables = []): string
    {
        $entry = $this->templates[$key] ?? null;
        if (! $entry) {
            throw new \InvalidArgumentException("Prompt template not found: {$key}");
        }
        $template = $entry['template'];
        foreach ($variables as $var => $value) {
            $template = str_replace('{{' . $var . '}}', (string) $value, $template);
        }
        return $template;
    }

    public function has(string $key): bool
    {
        return isset($this->templates[$key]);
    }

    public function getMeta(string $key): array
    {
        return $this->templates[$key]['meta'] ?? [];
    }

    public function all(): array
    {
        return array_keys($this->templates);
    }

    public function getByDomain(string $domain): array
    {
        return array_keys(array_filter($this->templates, fn ($t) => ($t['meta']['domain'] ?? '') === $domain));
    }

    private function loadPhase95Templates(): void
    {
        // ── Forms ─────────────────────────────────────────────────────────────

        $this->register('form.generate_from_prompt',
            'You are a form builder assistant. The user wants to create a form. '
            . 'User request (in Persian or English): "{{prompt}}" '
            . 'Generate a complete form as JSON with: {title, description, fields:[{type,label,required,placeholder,help_text,options,validation}], settings}. '
            . 'Field types: text, email, phone, number, textarea, select, checkbox, radio, date, file, rating, url. '
            . 'Respond ONLY with valid JSON. No markdown.',
            ['domain' => 'forms', 'output' => 'json', 'capability' => 'form_generation']
        );

        $this->register('form.generate_section',
            'Generate a form section for "{{section_type}}" in the context of a "{{form_purpose}}" form. '
            . 'Return JSON: {title, description, fields:[{type,label,required,placeholder,help_text,validation}]}. '
            . 'Be specific and practical. Respond ONLY with JSON.',
            ['domain' => 'forms', 'output' => 'json', 'capability' => 'form_generation']
        );

        $this->register('form.optimize_fields',
            'Analyze this form field configuration and suggest optimizations for better UX, higher completion rates, and accessibility: {{form_json}}. '
            . 'Return JSON: {score, issues:[{type,severity,field,message,suggestion}], optimizations:[{action,field,reason}]}. '
            . 'Focus on: field reduction, label clarity, required vs optional balance, mobile UX, RTL support.',
            ['domain' => 'forms', 'output' => 'json', 'capability' => 'form_optimization']
        );

        $this->register('form.analyze_submissions',
            'Analyze these form submission results and provide intelligence. '
            . 'Form: "{{form_title}}", Total submissions: {{submission_count}}, Sample data: {{sample_data}}. '
            . 'Return JSON: {patterns:[],anomalies:[],spam_likelihood,insights:[],recommendations:[]}. '
            . 'Focus on: completion patterns, field abandonment, anomalous values, spam detection.',
            ['domain' => 'forms', 'output' => 'json', 'capability' => 'submission_analysis']
        );

        $this->register('form.summarize_submissions',
            'Summarize these form submissions in a concise admin report. '
            . 'Form: "{{form_title}}", Submissions: {{submissions_json}}. '
            . 'Return JSON: {summary_text, key_findings:[str], action_items:[str], sentiment_distribution:{positive,neutral,negative}}.',
            ['domain' => 'forms', 'output' => 'json', 'capability' => 'summarization']
        );

        $this->register('form.accessibility_check',
            'Review this form structure for accessibility issues: {{form_json}}. '
            . 'Check: ARIA labels, keyboard navigation, color contrast requirements, RTL support, mobile touch targets, error messaging. '
            . 'Return JSON array: [{issue,severity,field,suggestion}].',
            ['domain' => 'forms', 'output' => 'json', 'capability' => 'form_optimization']
        );

        $this->register('form.suggest_fields',
            'Given the form title "{{title}}" and description "{{description}}", suggest 5-8 appropriate form fields. '
            . 'Return JSON array. Each field: {type, label, required, validation, placeholder, help_text}.',
            ['domain' => 'forms', 'output' => 'json', 'capability' => 'form_generation']
        );

        $this->register('form.suggest_validation',
            'For a form field labeled "{{label}}" of type "{{type}}", suggest appropriate validation rules as a JSON array of strings.',
            ['domain' => 'forms', 'output' => 'json', 'capability' => 'form_generation']
        );

        // ── Tickets ───────────────────────────────────────────────────────────

        $this->register('ticket.suggest_reply',
            'You are a professional customer support agent. Write a helpful, empathetic reply to this support ticket. '
            . 'Subject: {{subject}}\nCustomer message: {{message}}\nContext: {{context}}\nPrevious replies count: {{reply_count}}\n'
            . 'Rules: Be concise, professional, solution-focused. Match the tone (formal/informal) of the customer. '
            . 'If in Persian, reply in Persian. If in English, reply in English. Do NOT hallucinate solutions.',
            ['domain' => 'tickets', 'output' => 'text', 'capability' => 'ticket_reply']
        );

        $this->register('ticket.rewrite_reply',
            'Rewrite this support reply to be more {{tone}} and {{style}}: "{{reply}}" '
            . 'Context: subject="{{subject}}". Keep the meaning. Respond with just the rewritten text.',
            ['domain' => 'tickets', 'output' => 'text', 'capability' => 'ticket_reply']
        );

        $this->register('ticket.categorize',
            'Categorize this support ticket based on content. '
            . 'Ticket content: {{content}} '
            . 'Return JSON: {category, subcategory, priority, urgency, sentiment, confidence, suggested_tags:[]}. '
            . 'Categories: billing, technical, account, feature_request, general, bug_report, security.',
            ['domain' => 'tickets', 'output' => 'json', 'capability' => 'ticket_categorize']
        );

        $this->register('ticket.sla_risk',
            'Assess SLA breach risk for this support ticket. '
            . 'Priority: {{priority}}, Age: {{age_hours}}h, Last response: {{last_response_hours}}h, Status: {{status}}, Category: {{category}}. '
            . 'Return JSON: {risk_level, risk_score, recommended_action, escalate, estimated_breach_in_hours}.',
            ['domain' => 'tickets', 'output' => 'json', 'capability' => 'ticket_sla_risk']
        );

        $this->register('ticket.duplicate_check',
            'Compare these two support tickets and assess if they describe the same underlying issue. '
            . 'Ticket A: {{ticket_a}}\nTicket B: {{ticket_b}} '
            . 'Return JSON: {is_duplicate, confidence, reason, suggested_merge}.',
            ['domain' => 'tickets', 'output' => 'json', 'capability' => 'ticket_categorize']
        );

        $this->register('ticket.summarize_thread',
            'Summarize this support ticket thread for a new agent taking over. '
            . 'Subject: {{subject}}\nThread: {{thread_json}} '
            . 'Return JSON: {summary, current_status, customer_sentiment, unresolved_issues:[str], next_action}.',
            ['domain' => 'tickets', 'output' => 'json', 'capability' => 'summarization']
        );

        $this->register('ticket.sentiment',
            'Analyze the sentiment and frustration level in this customer message: "{{message}}" '
            . 'Return JSON: {sentiment, frustration_level, urgency_signals:[str], churn_risk, recommended_tone}.',
            ['domain' => 'tickets', 'output' => 'json', 'capability' => 'sentiment_analysis']
        );

        $this->register('ticket.generate_internal_note',
            'Generate a professional internal note for this support ticket. '
            . 'Subject: {{subject}}, Action taken: {{action}}, Context: {{context}}. '
            . 'Write a concise note for the team. Include: what was done, what is pending, any risks.',
            ['domain' => 'tickets', 'output' => 'text', 'capability' => 'ticket_reply']
        );

        $this->register('ticket.generate_escalation_summary',
            'Generate an escalation summary for this ticket to send to the manager. '
            . 'Subject: {{subject}}, Priority: {{priority}}, Age: {{age_hours}}h, Issue: {{issue}}. '
            . 'Return JSON: {summary, impact, recommended_action, urgency_reason}.',
            ['domain' => 'tickets', 'output' => 'json', 'capability' => 'summarization']
        );

        // ── Automation ────────────────────────────────────────────────────────

        $this->register('automation.suggest_workflow',
            'Based on this form/ticket configuration, suggest 3 practical automation workflows. '
            . 'Config: {{config}} '
            . 'Return JSON array: [{name, trigger, conditions:[{field,operator,value}], actions:[{type,params}], description, benefit}].',
            ['domain' => 'automation', 'output' => 'json', 'capability' => 'form_generation']
        );
    }
}
