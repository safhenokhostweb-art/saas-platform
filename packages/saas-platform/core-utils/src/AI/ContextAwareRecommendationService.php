<?php

namespace SaaSPlatform\CoreUtils\AI;

use Illuminate\Support\Facades\Cache;

class ContextAwareRecommendationService
{
    private const CONTEXT_TTL = 1800;

    public function __construct(
        private readonly AiSuggestionEngine $engine
    ) {}

    public function getRecommendations(string $tenantId, int|string $userId, string $context, array $data = []): array
    {
        $cacheKey = "ai:rec:{$tenantId}:{$userId}:{$context}:" . md5(serialize($data));
        return Cache::remember($cacheKey, self::CONTEXT_TTL, fn () => $this->buildRecommendations($context, $data));
    }

    public function getFormRecommendations(string $tenantId, array $formMeta): array
    {
        return [
            'field_suggestions'   => $this->suggestMissingFields($formMeta),
            'ux_improvements'     => $this->suggestUxImprovements($formMeta),
            'automation_triggers' => $this->suggestAutomationTriggers($formMeta),
        ];
    }

    public function getTicketRecommendations(string $tenantId, array $ticketMeta): array
    {
        return [
            'assign_to'         => $this->suggestAssignee($tenantId, $ticketMeta),
            'canned_response'   => $this->suggestCannedResponse($ticketMeta),
            'escalation_needed' => $this->assessEscalation($ticketMeta),
        ];
    }

    public function getDashboardInsights(string $tenantId, array $metrics): array
    {
        $insights = [];

        if (($metrics['open_tickets'] ?? 0) > ($metrics['avg_open_tickets'] ?? PHP_INT_MAX) * 1.5) {
            $insights[] = [
                'type'    => 'anomaly',
                'title'   => 'افزایش تیکت‌های باز',
                'message' => 'تعداد تیکت‌های باز ۵۰٪ بالاتر از میانگین است',
                'action'  => 'ticket_audit',
                'severity'=> 'warning',
            ];
        }

        if (($metrics['sla_compliance'] ?? 100) < 85) {
            $insights[] = [
                'type'    => 'sla_alert',
                'title'   => 'هشدار SLA',
                'message' => 'نرخ رعایت SLA زیر ۸۵٪ است',
                'action'  => 'sla_review',
                'severity'=> 'error',
            ];
        }

        if (($metrics['form_submissions_today'] ?? 0) > ($metrics['avg_daily_submissions'] ?? 0) * 2) {
            $insights[] = [
                'type'    => 'spike',
                'title'   => 'اوج ارسال فرم',
                'message' => 'ارسال فرم‌ها دو برابر میانگین روزانه است',
                'action'  => 'review_submissions',
                'severity'=> 'info',
            ];
        }

        return $insights;
    }

    private function buildRecommendations(string $context, array $data): array
    {
        return match($context) {
            'form_builder' => $this->getFormRecommendations('', $data),
            'ticket_view'  => $this->getTicketRecommendations('', $data),
            default        => [],
        };
    }

    private function suggestMissingFields(array $formMeta): array
    {
        $fields     = $formMeta['fields'] ?? [];
        $types      = array_column($fields, 'type');
        $suggestions = [];

        if (! in_array('email', $types) && ($formMeta['has_submission'] ?? false)) {
            $suggestions[] = ['type' => 'email', 'label' => 'ایمیل', 'reason' => 'برای ارتباط با کاربر ضروری است'];
        }

        return $suggestions;
    }

    private function suggestUxImprovements(array $formMeta): array
    {
        $improvements = [];
        $fieldCount   = count($formMeta['fields'] ?? []);

        if ($fieldCount > 8 && empty($formMeta['sections'])) {
            $improvements[] = ['type' => 'sections', 'message' => 'فرم را به بخش‌های منطقی تقسیم کنید'];
        }

        return $improvements;
    }

    private function suggestAutomationTriggers(array $formMeta): array
    {
        $triggers = [];
        if (! empty($formMeta['has_email_field'])) {
            $triggers[] = ['trigger' => 'submission', 'action' => 'send_confirmation_email', 'label' => 'ارسال ایمیل تایید'];
        }
        return $triggers;
    }

    private function suggestAssignee(string $tenantId, array $ticketMeta): ?array
    {
        return null; // Requires workload data — caller should inject
    }

    private function suggestCannedResponse(array $ticketMeta): ?string
    {
        $subject = mb_strtolower($ticketMeta['subject'] ?? '');
        if (str_contains($subject, 'رمز') || str_contains($subject, 'password')) {
            return 'برای بازیابی رمز عبور از صفحه ورود استفاده کنید.';
        }
        return null;
    }

    private function assessEscalation(array $ticketMeta): bool
    {
        return ($ticketMeta['age_hours'] ?? 0) > 24 && ($ticketMeta['priority'] ?? 'normal') === 'high';
    }
}
