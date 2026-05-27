<?php

namespace SaaSPlatform\Modules\Forms\Services;

use SaaSPlatform\Modules\Forms\Models\Form;
use SaaSPlatform\Modules\Forms\Models\FormAutomation;
use SaaSPlatform\Modules\Forms\Models\FormSubmission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class FormAutomationService
{
    public function getForForm(Form $form)
    {
        return $form->automations()->orderBy('created_at', 'desc')->get();
    }

    public function create(Form $form, array $data): FormAutomation
    {
        return $form->automations()->create([
            'name'       => $data['name'],
            'type'       => $data['type'],
            'config'     => $data['config'] ?? [],
            'conditions' => $data['conditions'] ?? null,
            'is_active'  => $data['is_active'] ?? true,
        ]);
    }

    public function update(FormAutomation $automation, array $data): FormAutomation
    {
        $automation->update([
            'name'       => $data['name'] ?? $automation->name,
            'type'       => $data['type'] ?? $automation->type,
            'config'     => $data['config'] ?? $automation->config,
            'conditions' => $data['conditions'] ?? $automation->conditions,
            'is_active'  => $data['is_active'] ?? $automation->is_active,
        ]);

        return $automation->fresh();
    }

    public function delete(FormAutomation $automation): void
    {
        $automation->delete();
    }

    public function toggle(FormAutomation $automation): void
    {
        $automation->update(['is_active' => ! $automation->is_active]);
    }

    /**
     * Fire all active automations for a submission.
     */
    public function fireForSubmission(FormSubmission $submission): void
    {
        $automations = $submission->form->automations()
            ->where('is_active', true)
            ->get();

        foreach ($automations as $automation) {
            if (! $this->conditionsPassed($automation, $submission)) {
                continue;
            }

            try {
                $this->dispatch($automation, $submission);
            } catch (\Throwable $e) {
                Log::warning("Forms automation [{$automation->id}] failed: " . $e->getMessage());
            }
        }
    }

    private function dispatch(FormAutomation $automation, FormSubmission $submission): void
    {
        match ($automation->type) {
            'webhook'  => $this->dispatchWebhook($automation, $submission),
            'slack'    => $this->dispatchSlack($automation, $submission),
            'telegram' => $this->dispatchTelegram($automation, $submission),
            'email', 'autoresponder' => $this->dispatchEmail($automation, $submission),
            default    => null,
        };
    }

    private function dispatchWebhook(FormAutomation $automation, FormSubmission $submission): void
    {
        $url = $automation->config['url'] ?? null;
        if (! $url) {
            return;
        }

        Http::timeout(10)->post($url, [
            'form_id'       => $submission->form_id,
            'submission_id' => $submission->id,
            'data'          => $submission->data,
            'submitted_at'  => $submission->created_at->toIso8601String(),
        ]);
    }

    private function dispatchSlack(FormAutomation $automation, FormSubmission $submission): void
    {
        $url = $automation->config['webhook_url'] ?? null;
        if (! $url) {
            return;
        }

        $text = $automation->config['message'] ?? "فرم «{$submission->form->title}» ارسال جدید دارد.";

        Http::timeout(10)->post($url, ['text' => $text]);
    }

    private function dispatchTelegram(FormAutomation $automation, FormSubmission $submission): void
    {
        $token  = $automation->config['bot_token'] ?? null;
        $chatId = $automation->config['chat_id'] ?? null;
        if (! $token || ! $chatId) {
            return;
        }

        $text = $automation->config['message'] ?? "📩 ارسال جدید برای فرم «{$submission->form->title}»";

        Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text'    => $text,
        ]);
    }

    private function dispatchEmail(FormAutomation $automation, FormSubmission $submission): void
    {
        $to      = $automation->config['to'] ?? null;
        $subject = $automation->config['subject'] ?? "ارسال جدید: {$submission->form->title}";

        if (! $to) {
            return;
        }

        $body = $this->buildEmailBody($submission, $automation->config['body'] ?? null);

        Mail::raw($body, function ($message) use ($to, $subject) {
            $message->to($to)->subject($subject);
        });
    }

    private function buildEmailBody(FormSubmission $submission, ?string $template): string
    {
        if ($template) {
            return $template;
        }

        $lines = ["فرم: {$submission->form->title}", "تاریخ: {$submission->created_at}", '---'];
        foreach ((array) $submission->data as $key => $value) {
            $lines[] = "{$key}: " . (is_array($value) ? implode(', ', $value) : $value);
        }

        return implode("\n", $lines);
    }

    private function conditionsPassed(FormAutomation $automation, FormSubmission $submission): bool
    {
        $conditions = $automation->conditions;
        if (empty($conditions['rules'])) {
            return true;
        }

        foreach ($conditions['rules'] as $rule) {
            $field = $rule['field'] ?? null;
            $op    = $rule['operator'] ?? 'equals';
            $val   = $rule['value'] ?? '';

            if (! $field) {
                continue;
            }

            $actual = $submission->data[$field] ?? null;

            $passed = match ($op) {
                'equals'     => (string) $actual === (string) $val,
                'not_equals' => (string) $actual !== (string) $val,
                'contains'   => str_contains((string) $actual, (string) $val),
                'is_empty'   => empty($actual),
                default      => true,
            };

            $logic = $conditions['logic'] ?? 'AND';
            if ($logic === 'AND' && ! $passed) {
                return false;
            }
            if ($logic === 'OR' && $passed) {
                return true;
            }
        }

        return ($conditions['logic'] ?? 'AND') === 'AND';
    }
}
