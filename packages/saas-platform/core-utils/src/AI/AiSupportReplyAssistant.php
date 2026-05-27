<?php

namespace SaaSPlatform\CoreUtils\AI;

use SaaSPlatform\CoreUtils\AI\Contracts\AiCompletionResult;
use SaaSPlatform\CoreUtils\AI\Governance\PromptTemplateRegistry;
use SaaSPlatform\CoreUtils\AI\Governance\TenantSafePromptBuilder;
use Illuminate\Support\Facades\Cache;

class AiSupportReplyAssistant
{
    private array $cannedResponses = [];

    public function __construct(
        private readonly AiSuggestionEngine     $engine,
        private readonly AiRuntimeCoordinator   $coordinator,
        private readonly PromptTemplateRegistry  $templates,
        private readonly TenantSafePromptBuilder $promptBuilder,
    ) {
        $this->loadDefaultCannedResponses();
    }

    // ── A. Smart Reply Generation ─────────────────────────────────────────────

    /**
     * Generate AI-powered reply suggestions for a support ticket.
     */
    public function suggestReply(
        string $subject,
        string $message,
        array  $context = [],
        string $tenantId = ''
    ): array {
        if (! $this->coordinator->isAvailable()) {
            return $this->getRuleBasedReplies($subject, $message);
        }

        $promptText = $this->templates->resolve('ticket.suggest_reply', [
            'subject'     => $subject,
            'message'     => $message,
            'context'     => implode(', ', $context),
            'reply_count' => count($context),
        ]);

        $builtPrompt = $this->promptBuilder->buildPromptWithContext(
            $promptText, [], $tenantId, 'tickets'
        );

        $result = $this->coordinator->complete(
            $builtPrompt['message'],
            $tenantId,
            'tickets',
            ['system' => $builtPrompt['system'], 'advanced' => false],
            [$subject, $message]
        );

        if (! $result->success || empty($result->text)) {
            return $this->getRuleBasedReplies($subject, $message);
        }

        return [[
            'text'       => $result->text,
            'confidence' => $result->confidence,
            'source'     => $result->meta['source'] ?? 'ai',
            'provider'   => $result->meta['provider'] ?? 'unknown',
            'tokens'     => $result->meta['total_tokens'] ?? 0,
        ]];
    }

    /**
     * Rewrite a reply with a different tone/style.
     */
    public function rewriteReply(
        string $reply,
        string $subject = '',
        string $tone = 'professional',
        string $style = 'concise',
        string $tenantId = ''
    ): string {
        if (! $this->coordinator->isAvailable()) {
            return $reply;
        }

        $promptText = $this->templates->resolve('ticket.rewrite_reply', [
            'reply'   => $reply,
            'subject' => $subject,
            'tone'    => $tone,
            'style'   => $style,
        ]);

        $result = $this->coordinator->complete($promptText, $tenantId, 'tickets');
        return $result->success && ! empty($result->text) ? $result->text : $reply;
    }

    /**
     * Generate an internal note for team members.
     */
    public function generateInternalNote(
        string $subject,
        string $action,
        string $context = '',
        string $tenantId = ''
    ): string {
        if (! $this->coordinator->isAvailable()) {
            return "یادداشت: {$action}";
        }

        $promptText = $this->templates->resolve('ticket.generate_internal_note', [
            'subject' => $subject,
            'action'  => $action,
            'context' => $context,
        ]);

        $result = $this->coordinator->complete($promptText, $tenantId, 'tickets');
        return $result->success && ! empty($result->text) ? $result->text : "یادداشت: {$action}";
    }

    /**
     * Generate an escalation summary for management.
     */
    public function generateEscalationSummary(
        string $subject,
        string $priority,
        float  $ageHours,
        string $issue,
        string $tenantId = ''
    ): array {
        if (! $this->coordinator->isAvailable()) {
            return [
                'summary'           => "تیکت \"{$subject}\" نیاز به ارجاع دارد.",
                'impact'            => 'نامشخص',
                'recommended_action'=> 'بررسی فوری',
                'urgency_reason'    => "سن تیکت: {$ageHours} ساعت",
            ];
        }

        $promptText = $this->templates->resolve('ticket.generate_escalation_summary', [
            'subject'   => $subject,
            'priority'  => $priority,
            'age_hours' => round($ageHours, 1),
            'issue'     => $issue,
        ]);

        $result = $this->coordinator->complete($promptText, $tenantId, 'tickets', ['json_mode' => true]);
        return $this->parseJson($result, []);
    }

    // ── B. Ticket Intelligence ────────────────────────────────────────────────

    /**
     * AI-powered ticket categorization with confidence scoring.
     */
    public function categorizeTicket(string $content, string $tenantId = ''): array
    {
        if (! $this->coordinator->isAvailable()) {
            return $this->ruleBasedCategorization($content);
        }

        $promptText = $this->templates->resolve('ticket.categorize', ['content' => $content]);
        $result     = $this->coordinator->complete($promptText, $tenantId, 'tickets', ['json_mode' => true]);
        $data       = $this->parseJson($result, []);

        return ! empty($data) ? $data : $this->ruleBasedCategorization($content);
    }

    /**
     * Predict SLA breach risk with AI enrichment.
     */
    public function predictSlaRisk(
        string $priority,
        float  $ageHours,
        float  $lastResponseHours,
        string $status,
        string $category = '',
        string $tenantId = ''
    ): array {
        if (! $this->coordinator->isAvailable()) {
            return $this->ruleBasedSlaRisk($priority, $ageHours, $lastResponseHours);
        }

        $promptText = $this->templates->resolve('ticket.sla_risk', [
            'priority'            => $priority,
            'age_hours'           => round($ageHours, 1),
            'last_response_hours' => round($lastResponseHours, 1),
            'status'              => $status,
            'category'            => $category,
        ]);

        $result = $this->coordinator->complete($promptText, $tenantId, 'tickets', ['json_mode' => true]);
        $data   = $this->parseJson($result, []);

        return ! empty($data) ? $data : $this->ruleBasedSlaRisk($priority, $ageHours, $lastResponseHours);
    }

    /**
     * Analyze customer sentiment and frustration level.
     */
    public function analyzeSentiment(string $message, string $tenantId = ''): array
    {
        if (! $this->coordinator->isAvailable()) {
            return $this->ruleBasedSentiment($message);
        }

        $promptText = $this->templates->resolve('ticket.sentiment', ['message' => $message]);
        $result     = $this->coordinator->complete($promptText, $tenantId, 'tickets', ['json_mode' => true]);
        $data       = $this->parseJson($result, []);

        return ! empty($data) ? $data : $this->ruleBasedSentiment($message);
    }

    /**
     * Summarize a ticket thread for agent handover.
     */
    public function summarizeThread(string $subject, array $thread, string $tenantId = ''): array
    {
        if (! $this->coordinator->isAvailable() || empty($thread)) {
            return [
                'summary'          => '',
                'current_status'   => 'open',
                'customer_sentiment' => 'neutral',
                'unresolved_issues'=> [],
                'next_action'      => '',
            ];
        }

        $promptText = $this->templates->resolve('ticket.summarize_thread', [
            'subject'     => $subject,
            'thread_json' => json_encode(array_slice($thread, 0, 20)),
        ]);

        $result = $this->coordinator->complete(
            $promptText, $tenantId, 'tickets', ['json_mode' => true, 'advanced' => true]
        );

        return $this->parseJson($result, []);
    }

    /**
     * Check if two tickets are duplicates.
     */
    public function checkDuplicate(array $ticketA, array $ticketB, string $tenantId = ''): array
    {
        if (! $this->coordinator->isAvailable()) {
            return ['is_duplicate' => false, 'confidence' => 0.0, 'reason' => 'AI not available'];
        }

        $promptText = $this->templates->resolve('ticket.duplicate_check', [
            'ticket_a' => json_encode($ticketA),
            'ticket_b' => json_encode($ticketB),
        ]);

        $result = $this->coordinator->complete($promptText, $tenantId, 'tickets', ['json_mode' => true]);
        return $this->parseJson($result, ['is_duplicate' => false, 'confidence' => 0.0, 'reason' => '']);
    }

    // ── C. Operational Intelligence ───────────────────────────────────────────

    public function assessChurnRisk(array $ticketHistory, string $tenantId = ''): array
    {
        $openCount   = count(array_filter($ticketHistory, fn ($t) => ($t['status'] ?? '') === 'open'));
        $totalCount  = count($ticketHistory);
        $avgAge      = $totalCount > 0
            ? array_sum(array_column($ticketHistory, 'age_hours')) / $totalCount
            : 0;

        $riskScore = 0;
        $riskScore += min(40, $openCount * 10);
        $riskScore += min(30, ($avgAge / 48) * 30);
        $riskScore = min(100, $riskScore);

        return [
            'risk_score' => (int) $riskScore,
            'risk_level' => $riskScore >= 70 ? 'high' : ($riskScore >= 40 ? 'medium' : 'low'),
            'open_tickets' => $openCount,
            'avg_age_hours' => round($avgAge, 1),
            'recommendation' => $riskScore >= 70 ? 'تماس فوری با مشتری' : ($riskScore >= 40 ? 'پیگیری در اولین فرصت' : 'وضعیت طبیعی'),
        ];
    }

    // ── Canned Responses ──────────────────────────────────────────────────────

    public function getCannedResponses(string $category = ''): array
    {
        if ($category) {
            return array_values(array_filter($this->cannedResponses, fn ($r) => $r['category'] === $category));
        }
        return array_values($this->cannedResponses);
    }

    public function registerCannedResponse(string $key, string $title, string $text, string $category = 'general'): void
    {
        $this->cannedResponses[$key] = compact('key', 'title', 'text', 'category');
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function parseJson(AiCompletionResult $result, array $default): array
    {
        if (! $result->success || empty($result->text)) return $default;

        $text = preg_replace('/^```(?:json)?\s*/m', '', trim($result->text));
        $text = preg_replace('/```\s*$/m', '', $text);
        $decoded = json_decode(trim($text), true);
        return is_array($decoded) ? $decoded : $default;
    }

    private function loadDefaultCannedResponses(): void
    {
        $this->cannedResponses = [
            'greet' => [
                'key' => 'greet', 'title' => 'سلام و خوش‌آمد',
                'text' => "سلام [نام مشتری],\n\nممنون از تماس شما با پشتیبانی. خوشحالیم که می‌توانیم کمک کنیم.\n\n",
                'category' => 'greeting',
            ],
            'need_info' => [
                'key' => 'need_info', 'title' => 'نیاز به اطلاعات بیشتر',
                'text' => "سلام،\n\nجهت بررسی بهتر مشکل شما، لطفاً اطلاعات زیر را ارسال نمایید:\n- \n- \n\n",
                'category' => 'info_request',
            ],
            'resolved' => [
                'key' => 'resolved', 'title' => 'مشکل حل شد',
                'text' => "سلام،\n\nخوشحالیم که مشکل شما حل شد. در صورت نیاز به هرگونه کمک دیگری، در خدمت هستیم.\n\n",
                'category' => 'closing',
            ],
            'escalating' => [
                'key' => 'escalating', 'title' => 'ارجاع به تیم فنی',
                'text' => "سلام،\n\nتیکت شما به تیم تخصصی فنی ارجاع داده شد. ظرف [X] ساعت کاری پاسخ خواهید گرفت.\n\n",
                'category' => 'escalation',
            ],
            'apology' => [
                'key' => 'apology', 'title' => 'عذرخواهی بابت تأخیر',
                'text' => "سلام،\n\nضمن عذرخواهی از تأخیر در پاسخگویی، موضوع شما در اولویت قرار گرفته و به زودی پاسخ کامل ارسال خواهد شد.\n\n",
                'category' => 'apology',
            ],
        ];
    }

    private function getRuleBasedReplies(string $subject, string $message): array
    {
        $lower = mb_strtolower($message . ' ' . $subject);
        $text  = '';

        if (str_contains($lower, 'password') || str_contains($lower, 'رمز') || str_contains($lower, 'ورود')) {
            $text = "سلام،\n\nبرای بازیابی رمز عبور، از لینک «فراموشی رمز عبور» در صفحه ورود استفاده نمایید. ایمیل بازیابی ظرف چند دقیقه ارسال می‌شود.";
        } elseif (str_contains($lower, 'خطا') || str_contains($lower, 'error') || str_contains($lower, 'bug')) {
            $text = "سلام،\n\nممنون از گزارش شما. مشکل بررسی می‌شود. لطفاً اگر پیام خطای دقیق یا تصویری از صفحه دارید ارسال کنید.";
        } else {
            $text = "سلام،\n\nتیکت شما دریافت شد و در اسرع وقت بررسی خواهد شد. سپاسگزاریم از صبر شما.";
        }

        return [['text' => $text, 'confidence' => 0.5, 'source' => 'rule_based']];
    }

    private function ruleBasedCategorization(string $content): array
    {
        $lower    = mb_strtolower($content);
        $category = 'general';
        $priority = 'normal';
        $urgency  = 'medium';
        $sentiment = 'neutral';

        if (str_contains($lower, 'فوری') || str_contains($lower, 'urgent')) {
            $urgency = 'high'; $priority = 'high';
        }
        if (str_contains($lower, 'صورتحساب') || str_contains($lower, 'billing') || str_contains($lower, 'پرداخت')) {
            $category = 'billing';
        } elseif (str_contains($lower, 'فنی') || str_contains($lower, 'bug') || str_contains($lower, 'خطا')) {
            $category = 'technical';
        } elseif (str_contains($lower, 'حساب') || str_contains($lower, 'رمز')) {
            $category = 'account';
        }

        if (str_contains($lower, 'ناراضی') || str_contains($lower, 'مشکل بزرگ') || str_contains($lower, 'غیرقابل قبول')) {
            $sentiment = 'negative';
        }

        return [
            'category'      => $category,
            'subcategory'   => '',
            'priority'      => $priority,
            'urgency'       => $urgency,
            'sentiment'     => $sentiment,
            'confidence'    => 0.6,
            'suggested_tags'=> [$category],
        ];
    }

    private function ruleBasedSlaRisk(string $priority, float $ageHours, float $lastResponseHours): array
    {
        $thresholds = ['critical' => 4, 'high' => 8, 'normal' => 24, 'low' => 72];
        $threshold  = $thresholds[$priority] ?? 24;
        $score      = min(100, ($ageHours / $threshold) * 100);
        $level      = $score >= 90 ? 'critical' : ($score >= 70 ? 'high' : ($score >= 40 ? 'medium' : 'low'));
        $action     = match($level) {
            'critical' => 'فوری: پاسخ دهید',
            'high'     => 'تا ۱ ساعت پاسخ دهید',
            'medium'   => 'در طول روز پاسخ دهید',
            default    => 'معمولی',
        };
        return [
            'risk_level'         => $level,
            'risk_score'         => round($score),
            'recommended_action' => $action,
            'escalate'           => $score >= 90,
            'estimated_breach_in_hours' => max(0, $threshold - $ageHours),
        ];
    }

    private function ruleBasedSentiment(string $message): array
    {
        $lower = mb_strtolower($message);
        $negative = ['ناراضی', 'بد', 'مشکل', 'خطا', 'اشتباه', 'غیرقابل', 'ترک', 'cancel'];
        $positive = ['ممنون', 'عالی', 'خوب', 'رضایت', 'thanks', 'great', 'perfect'];

        $negScore = array_sum(array_map(fn ($w) => str_contains($lower, $w) ? 1 : 0, $negative));
        $posScore = array_sum(array_map(fn ($w) => str_contains($lower, $w) ? 1 : 0, $positive));

        $sentiment = $negScore > $posScore ? 'negative' : ($posScore > 0 ? 'positive' : 'neutral');
        $frustration = $negScore >= 2 ? 'high' : ($negScore === 1 ? 'medium' : 'low');

        return [
            'sentiment'         => $sentiment,
            'frustration_level' => $frustration,
            'urgency_signals'   => [],
            'churn_risk'        => $frustration === 'high' ? 'elevated' : 'low',
            'recommended_tone'  => $frustration === 'high' ? 'empathetic' : 'professional',
        ];
    }
}
