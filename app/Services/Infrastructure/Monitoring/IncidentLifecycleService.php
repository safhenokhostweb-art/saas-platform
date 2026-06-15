<?php
namespace App\Services\Infrastructure\Monitoring;

use App\Models\Infrastructure\InfraIncident;
use App\Models\Infrastructure\InfraIncidentNote;
use App\Models\Site;
use App\Models\User;
use App\Services\Notification\NotificationCenterService;
use Illuminate\Support\Facades\Log;

/**
 * Manages incident lifecycle transitions and side effects.
 * open → investigating → resolved → closed
 */
class IncidentLifecycleService
{
    public function __construct(private NotificationCenterService $nc) {}

    // ── Lifecycle transitions ─────────────────────────────────────────────

    public function open(InfraIncident $incident, ?string $systemNote = null): void
    {
        if (! $incident->isOpen()) {
            $incident->update(['status' => InfraIncident::STATUS_OPEN]);
        }

        $this->addNote($incident, null, $systemNote ?? "حادثه ایجاد شد: {$incident->title}", isSystem: true);

        // Auto-create support ticket for critical/high severity with site
        if (in_array($incident->severity, ['critical', 'high'], true) && $incident->site_id && ! $incident->support_ticket_id) {
            $this->createSupportTicket($incident);
        }
    }

    public function investigate(InfraIncident $incident, User $assignee, ?string $note = null): void
    {
        $incident->markInvestigating($assignee->id);
        $this->addNote(
            $incident, $assignee,
            $note ?? "بررسی آغاز شد. مسئول: {$assignee->name}",
            isSystem: true
        );
    }

    public function resolve(InfraIncident $incident, User $resolver, ?string $note = null): void
    {
        $wasActive = $incident->isActive();
        $incident->markResolved();
        $this->addNote(
            $incident, $resolver,
            $note ?? "حادثه برطرف شد توسط {$resolver->name}",
            isSystem: true
        );

        if ($wasActive && $incident->site_id) {
            $this->notifyCustomerOnResolve($incident);
        }
    }

    public function close(InfraIncident $incident, User $closer, ?string $note = null): void
    {
        $incident->markClosed();
        $this->addNote(
            $incident, $closer,
            $note ?? "حادثه بسته شد توسط {$closer->name}",
            isSystem: true
        );
    }

    public function addNote(InfraIncident $incident, ?User $user, string $text, bool $isSystem = false): InfraIncidentNote
    {
        return InfraIncidentNote::create([
            'incident_id' => $incident->id,
            'user_id'     => $user?->id,
            'note'        => $text,
            'is_system'   => $isSystem,
        ]);
    }

    // ── Customer notification on resolution ───────────────────────────────

    private function notifyCustomerOnResolve(InfraIncident $incident): void
    {
        if ($incident->customer_notified_at) {
            return;
        }

        $site = $incident->site ?? Site::find($incident->site_id);
        if (! $site) {
            return;
        }

        // SMS to site owner
        $mobile = $this->resolveSiteMobile($site);
        if ($mobile) {
            $this->trySendResolutionSms($mobile, $incident, $site);
        }

        // Platform notification (audience: platform_admin + site context)
        try {
            $this->nc->incidentResolved($incident, $site);
        } catch (\Throwable $e) {
            Log::warning('IncidentLifecycleService: NC notify failed', ['error' => $e->getMessage()]);
        }

        $incident->markCustomerNotified();
    }

    private function resolveSiteMobile(Site $site): ?string
    {
        // Try customer mobile first
        $customer = $site->customer ?? $site->customer()->first();
        if ($customer?->mobile) {
            return $this->normalizeMobile($customer->mobile);
        }
        return null;
    }

    private function trySendResolutionSms(string $mobile, InfraIncident $incident, Site $site): void
    {
        try {
            $gateway = \App\Services\Sms\SmsGatewayResolver::resolve();
            if (! $gateway) {
                return;
            }
            $text = "کاربر گرامی، مشکل «{$incident->title}» در سایت {$site->domain} برطرف گردید. با عرض پوزش از اختلال ایجاد شده.";
            $gateway->send($mobile, $text);
        } catch (\Throwable $e) {
            Log::warning('IncidentLifecycleService: SMS failed', ['error' => $e->getMessage()]);
        }
    }

    // ── Support ticket creation ───────────────────────────────────────────

    private function createSupportTicket(InfraIncident $incident): void
    {
        try {
            $site     = $incident->site ?? Site::find($incident->site_id);
            $customer = $site?->customer ?? $site?->customer()->first();

            $ticket = \SaaSPlatform\Modules\Tickets\Models\Ticket::create([
                'subject'         => "[حادثه زیرساختی] {$incident->title}",
                'description'     => $incident->message ?? $incident->title,
                'status'          => 'open',
                'priority'        => $incident->isCritical() ? 'high' : 'normal',
                'site_id'         => $incident->site_id,
                'customer_id'     => $customer?->id,
                'customer_mobile' => $customer?->mobile,
                'customer_name'   => $customer?->name,
                'category'        => 'infrastructure',
                'type'            => 'incident',
            ]);

            $incident->update(['support_ticket_id' => $ticket->id]);
            $this->addNote($incident, null, "تیکت پشتیبانی #{$ticket->ticket_number} خودکار ایجاد شد.", isSystem: true);
        } catch (\Throwable $e) {
            Log::warning('IncidentLifecycleService: ticket creation failed', ['error' => $e->getMessage()]);
        }
    }

    private function normalizeMobile(string $m): string
    {
        $m = preg_replace('/\D/', '', $m);
        if (str_starts_with($m, '98')) return $m;
        if (str_starts_with($m, '0'))  return '98' . substr($m, 1);
        if (strlen($m) === 10)         return '98' . $m;
        return $m;
    }
}
