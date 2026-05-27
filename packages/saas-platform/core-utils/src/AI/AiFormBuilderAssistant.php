<?php

namespace SaaSPlatform\CoreUtils\AI;

use SaaSPlatform\CoreUtils\AI\Contracts\AiCompletionResult;
use SaaSPlatform\CoreUtils\AI\Governance\PromptTemplateRegistry;
use SaaSPlatform\CoreUtils\AI\Governance\TenantSafePromptBuilder;
use Illuminate\Support\Facades\Cache;

class AiFormBuilderAssistant
{
    public function __construct(
        private readonly AiSuggestionEngine    $engine,
        private readonly AiRuntimeCoordinator  $coordinator,
        private readonly PromptTemplateRegistry $templates,
        private readonly TenantSafePromptBuilder $promptBuilder,
    ) {}

    // ── A. Form Generation ────────────────────────────────────────────────────

    /**
     * Generate a complete form structure from a Persian/English natural language prompt.
     */
    public function generateFromPrompt(string $prompt, string $tenantId = ''): array
    {
        $cacheKey = 'ai:form:generate:' . md5($tenantId . $prompt);
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $builtPrompt = $this->promptBuilder->buildPromptWithContext(
            $this->templates->resolve('form.generate_from_prompt', ['prompt' => $prompt]),
            [],
            $tenantId,
            'forms'
        );

        $result = $this->coordinator->complete(
            $builtPrompt['message'],
            $tenantId,
            'forms',
            ['json_mode' => true, 'system' => $builtPrompt['system']],
            [$prompt]
        );

        $data = $this->parseJson($result, []);

        if (! empty($data)) {
            Cache::put($cacheKey, $data, 1800);
        }

        return ! empty($data) ? $data : $this->ruleBasedFormGeneration($prompt);
    }

    /**
     * Generate a specific section template.
     */
    public function generateSection(string $sectionType, string $formPurpose = '', string $tenantId = ''): array
    {
        $promptText = $this->templates->resolve('form.generate_section', [
            'section_type'  => $sectionType,
            'form_purpose'  => $formPurpose ?: $sectionType,
        ]);

        $result = $this->coordinator->complete(
            $promptText,
            $tenantId,
            'forms',
            ['json_mode' => true]
        );

        return $this->parseJson($result, $this->ruleBasedSectionTemplate($sectionType));
    }

    /**
     * Suggest fields based on form title and description.
     */
    public function suggestFields(string $title, string $description = '', string $tenantId = ''): array
    {
        if (! $this->coordinator->isAvailable()) {
            return $this->ruleBasedFieldSuggestions($title);
        }

        $promptText = $this->templates->resolve('form.suggest_fields', [
            'title'       => $title,
            'description' => $description,
        ]);

        $result = $this->coordinator->complete($promptText, $tenantId, 'forms', ['json_mode' => true]);
        $fields = $this->parseJson($result, []);

        return ! empty($fields) ? $fields : $this->ruleBasedFieldSuggestions($title);
    }

    // ── B. Form Optimization ──────────────────────────────────────────────────

    /**
     * Analyze form for UX issues, accessibility problems, and optimization opportunities.
     */
    public function optimizeForm(array $formJson, string $tenantId = ''): array
    {
        $promptText = $this->templates->resolve('form.optimize_fields', [
            'form_json' => json_encode($formJson),
        ]);

        $result = $this->coordinator->complete($promptText, $tenantId, 'forms', ['json_mode' => true]);

        return $this->parseJson($result, [
            'score'         => 70,
            'issues'        => [],
            'optimizations' => [],
        ]);
    }

    /**
     * Detect accessibility issues in a form.
     */
    public function detectAccessibilityIssues(array $formJson, string $tenantId = ''): array
    {
        if (! $this->coordinator->isAvailable()) {
            return $this->ruleBasedAccessibilityCheck($formJson);
        }

        $promptText = $this->templates->resolve('form.accessibility_check', [
            'form_json' => json_encode($formJson),
        ]);

        $result = $this->coordinator->complete($promptText, $tenantId, 'forms', ['json_mode' => true]);
        $issues = $this->parseJson($result, []);

        return ! empty($issues) ? $issues : $this->ruleBasedAccessibilityCheck($formJson);
    }

    /**
     * Detect UX weaknesses rule-based + AI hybrid.
     */
    public function detectWeakUx(array $fields, string $tenantId = ''): array
    {
        // Rule-based base checks
        $issues = [];

        if (count($fields) > 10) {
            $issues[] = ['type' => 'overload', 'message' => 'فرم دارای بیش از ۱۰ فیلد است — تقسیم به بخش‌ها توصیه می‌شود', 'severity' => 'warning'];
        }

        $required = array_filter($fields, fn ($f) => ($f['required'] ?? false) === true);
        if (count($fields) > 0 && count($required) > count($fields) * 0.7) {
            $issues[] = ['type' => 'required_overload', 'message' => 'بیش از ۷۰٪ فیلدها اجباری هستند', 'severity' => 'warning'];
        }

        foreach ($fields as $field) {
            if (empty($field['label'])) {
                $issues[] = ['type' => 'missing_label', 'message' => "فیلد بدون برچسب: {$field['type']}", 'severity' => 'error'];
            }
        }

        return $issues;
    }

    // ── C. Submission Intelligence ────────────────────────────────────────────

    /**
     * Analyze form submissions for patterns, anomalies, and insights.
     */
    public function analyzeSubmissions(string $formTitle, int $count, array $sample, string $tenantId = ''): array
    {
        if (! $this->coordinator->isAvailable() || $count < 5) {
            return $this->ruleBasedSubmissionAnalysis($count, $sample);
        }

        $promptText = $this->templates->resolve('form.analyze_submissions', [
            'form_title'        => $formTitle,
            'submission_count'  => $count,
            'sample_data'       => json_encode(array_slice($sample, 0, 10)),
        ]);

        $result = $this->coordinator->complete($promptText, $tenantId, 'forms', ['json_mode' => true]);

        return $this->parseJson($result, [
            'patterns' => [], 'anomalies' => [], 'spam_likelihood' => 'low', 'insights' => [], 'recommendations' => [],
        ]);
    }

    /**
     * Summarize submissions into a readable admin report.
     */
    public function summarizeSubmissions(string $formTitle, array $submissions, string $tenantId = ''): array
    {
        if (! $this->coordinator->isAvailable() || empty($submissions)) {
            return ['summary_text' => 'خلاصه‌ای موجود نیست', 'key_findings' => [], 'action_items' => [], 'sentiment_distribution' => []];
        }

        $promptText = $this->templates->resolve('form.summarize_submissions', [
            'form_title'       => $formTitle,
            'submissions_json' => json_encode(array_slice($submissions, 0, 20)),
        ]);

        $result = $this->coordinator->complete($promptText, $tenantId, 'forms', ['json_mode' => true, 'advanced' => true]);

        return $this->parseJson($result, []);
    }

    /**
     * Suggest automation workflows for a form.
     */
    public function suggestAutomation(array $formConfig, string $tenantId = ''): array
    {
        if (! $this->coordinator->isAvailable()) {
            return [];
        }

        $promptText = $this->templates->resolve('automation.suggest_workflow', [
            'config' => json_encode($formConfig),
        ]);

        $result = $this->coordinator->complete($promptText, $tenantId, 'automation', ['json_mode' => true]);
        return $this->parseJson($result, []);
    }

    /**
     * Suggest field validation rules.
     */
    public function suggestValidation(string $label, string $type, string $tenantId = ''): array
    {
        if (! $this->coordinator->isAvailable()) {
            return $this->ruleBasedValidation($type);
        }

        $promptText = $this->templates->resolve('form.suggest_validation', ['label' => $label, 'type' => $type]);
        $result     = $this->coordinator->complete($promptText, $tenantId, 'forms', ['json_mode' => true]);
        $rules      = $this->parseJson($result, []);

        return ! empty($rules) ? $rules : $this->ruleBasedValidation($type);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function parseJson(AiCompletionResult $result, array $default): array
    {
        if (! $result->success || empty($result->text)) {
            return $default;
        }

        $text = preg_replace('/^```(?:json)?\s*/m', '', trim($result->text));
        $text = preg_replace('/```\s*$/m', '', $text);

        $decoded = json_decode(trim($text), true);
        return is_array($decoded) ? $decoded : $default;
    }

    private function ruleBasedFormGeneration(string $prompt): array
    {
        $lower  = mb_strtolower($prompt);
        $fields = $this->ruleBasedFieldSuggestions($prompt);

        return [
            'title'       => $prompt,
            'description' => '',
            'fields'      => $fields,
            'settings'    => ['submit_label' => 'ارسال'],
        ];
    }

    private function ruleBasedSectionTemplate(string $type): array
    {
        $templates = [
            'contact' => [
                'title'  => 'اطلاعات تماس',
                'fields' => [
                    ['type' => 'text', 'label' => 'نام و نام خانوادگی', 'required' => true],
                    ['type' => 'email', 'label' => 'ایمیل', 'required' => true],
                    ['type' => 'text', 'label' => 'شماره تلفن', 'required' => false],
                ],
            ],
            'address' => [
                'title'  => 'آدرس',
                'fields' => [
                    ['type' => 'text', 'label' => 'استان', 'required' => true],
                    ['type' => 'text', 'label' => 'شهر', 'required' => true],
                    ['type' => 'textarea', 'label' => 'آدرس کامل', 'required' => true],
                    ['type' => 'text', 'label' => 'کد پستی', 'required' => false],
                ],
            ],
            'feedback' => [
                'title'  => 'بازخورد',
                'fields' => [
                    ['type' => 'rating', 'label' => 'امتیاز کلی', 'required' => true],
                    ['type' => 'textarea', 'label' => 'نظر شما', 'required' => true],
                ],
            ],
        ];

        return $templates[$type] ?? ['title' => $type, 'fields' => []];
    }

    private function ruleBasedFieldSuggestions(string $title): array
    {
        $lower = mb_strtolower($title);

        if (str_contains($lower, 'تماس') || str_contains($lower, 'contact')) {
            return [
                ['type' => 'text', 'label' => 'نام و نام خانوادگی', 'required' => true],
                ['type' => 'email', 'label' => 'ایمیل', 'required' => true],
                ['type' => 'text', 'label' => 'شماره تلفن', 'required' => false],
                ['type' => 'textarea', 'label' => 'پیام', 'required' => true],
            ];
        }
        if (str_contains($lower, 'نظر') || str_contains($lower, 'survey') || str_contains($lower, 'feedback')) {
            return [
                ['type' => 'text', 'label' => 'نام', 'required' => false],
                ['type' => 'rating', 'label' => 'امتیاز کلی', 'required' => true],
                ['type' => 'textarea', 'label' => 'نظر شما', 'required' => true],
            ];
        }

        return [
            ['type' => 'text', 'label' => 'نام', 'required' => true],
            ['type' => 'email', 'label' => 'ایمیل', 'required' => true],
            ['type' => 'textarea', 'label' => 'توضیحات', 'required' => false],
        ];
    }

    private function ruleBasedValidation(string $type): array
    {
        return match($type) {
            'email'    => ['required', 'email', 'max:255'],
            'text'     => ['required', 'string', 'max:255'],
            'textarea' => ['required', 'string', 'max:2000'],
            'number'   => ['required', 'numeric'],
            'phone'    => ['required', 'regex:/^[0-9+\-\s]{7,15}$/'],
            'url'      => ['required', 'url'],
            'file'     => ['required', 'file', 'max:10240'],
            default    => ['required'],
        };
    }

    private function ruleBasedAccessibilityCheck(array $formJson): array
    {
        $issues = [];
        $fields = $formJson['fields'] ?? [];
        foreach ($fields as $field) {
            if (empty($field['label'])) {
                $issues[] = ['issue' => 'missing_label', 'severity' => 'error', 'field' => $field['id'] ?? '?'];
            }
        }
        return $issues;
    }

    private function ruleBasedSubmissionAnalysis(int $count, array $sample): array
    {
        return [
            'patterns'        => [],
            'anomalies'       => [],
            'spam_likelihood' => 'unknown',
            'insights'        => ["تعداد {$count} ارسال ثبت شده است."],
            'recommendations' => [],
        ];
    }
}
