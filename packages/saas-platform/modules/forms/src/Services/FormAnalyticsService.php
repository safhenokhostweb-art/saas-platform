<?php

namespace SaaSPlatform\Modules\Forms\Services;

use SaaSPlatform\Modules\Forms\Models\Form;
use SaaSPlatform\Modules\Forms\Models\FormSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class FormAnalyticsService
{
    /**
     * Return full analytics data for a form.
     */
    public function getFormAnalytics(Form $form, int $days = 30): array
    {
        $from = Carbon::now()->subDays($days)->startOfDay();

        $totalSubmissions = FormSubmission::where('form_id', $form->id)->count();
        $recentSubmissions = FormSubmission::where('form_id', $form->id)
            ->where('created_at', '>=', $from)
            ->count();

        $trend = $this->getSubmissionTrend($form, $from, $days);
        $deviceBreakdown = $this->getDeviceBreakdown($form);
        $fieldCompletionRate = $this->getFieldCompletionRate($form);
        $submissionsByDay = $this->getSubmissionsByDay($form, $from, $days);

        return [
            'total_submissions'     => $totalSubmissions,
            'recent_submissions'    => $recentSubmissions,
            'avg_per_day'           => $days > 0 ? round($recentSubmissions / $days, 1) : 0,
            'trend'                 => $trend,
            'device_breakdown'      => $deviceBreakdown,
            'field_completion_rate' => $fieldCompletionRate,
            'submissions_by_day'    => $submissionsByDay,
            'period_days'           => $days,
        ];
    }

    private function getSubmissionTrend(Form $form, Carbon $from, int $days): string
    {
        $midpoint = Carbon::now()->subDays((int) ($days / 2))->startOfDay();

        $firstHalf  = FormSubmission::where('form_id', $form->id)
            ->whereBetween('created_at', [$from, $midpoint])
            ->count();

        $secondHalf = FormSubmission::where('form_id', $form->id)
            ->where('created_at', '>', $midpoint)
            ->count();

        if ($firstHalf === 0) {
            return $secondHalf > 0 ? 'up' : 'flat';
        }

        $delta = ($secondHalf - $firstHalf) / $firstHalf;

        return match (true) {
            $delta > 0.1  => 'up',
            $delta < -0.1 => 'down',
            default       => 'flat',
        };
    }

    private function getDeviceBreakdown(Form $form): array
    {
        $submissions = FormSubmission::where('form_id', $form->id)
            ->whereNotNull('user_agent')
            ->select('user_agent')
            ->limit(500)
            ->get();

        $counts = ['desktop' => 0, 'mobile' => 0, 'tablet' => 0, 'other' => 0];

        foreach ($submissions as $submission) {
            $ua = strtolower($submission->user_agent ?? '');
            if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
                $counts['tablet']++;
            } elseif (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
                $counts['mobile']++;
            } elseif (! empty($ua)) {
                $counts['desktop']++;
            } else {
                $counts['other']++;
            }
        }

        return $counts;
    }

    private function getFieldCompletionRate(Form $form): array
    {
        $submissions = FormSubmission::where('form_id', $form->id)
            ->latest()
            ->limit(200)
            ->pluck('data');

        if ($submissions->isEmpty()) {
            return [];
        }

        $fieldCounts = [];
        $total       = $submissions->count();

        foreach ($submissions as $data) {
            foreach ((array) $data as $key => $value) {
                $fieldCounts[$key] = ($fieldCounts[$key] ?? 0) + (! empty($value) ? 1 : 0);
            }
        }

        return array_map(
            fn($count) => round(($count / $total) * 100, 1),
            $fieldCounts
        );
    }

    private function getSubmissionsByDay(Form $form, Carbon $from, int $days): array
    {
        $raw = FormSubmission::where('form_id', $form->id)
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date          = Carbon::now()->subDays($i)->format('Y-m-d');
            $result[$date] = $raw[$date] ?? 0;
        }

        return $result;
    }
}
