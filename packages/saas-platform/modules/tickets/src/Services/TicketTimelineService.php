<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use Illuminate\Support\Facades\Auth;
use SaaSPlatform\Modules\Tickets\Models\Ticket;
use SaaSPlatform\Modules\Tickets\Models\TicketActivity;

class TicketTimelineService
{
    public function record(
        int     $ticketId,
        string  $actionType,
        ?string $oldValue = null,
        ?string $newValue = null,
        array   $metadata = [],
        ?int    $actorId  = null,
    ): TicketActivity {
        $actorId ??= Auth::id();

        if ($actorId && empty($metadata['actor_name'])) {
            $userModel = config('tickets.user_model', \App\Models\User::class);
            $actor = $userModel::find($actorId);
            if ($actor) {
                $metadata['actor_name'] = $actor->name;
            }
        }

        return TicketActivity::create([
            'ticket_id'   => $ticketId,
            'actor_id'    => $actorId,
            'action_type' => $actionType,
            'old_value'   => $oldValue,
            'new_value'   => $newValue,
            'metadata'    => $metadata ?: null,
        ]);
    }

    public function recordCreated(int $ticketId, string $subject): TicketActivity
    {
        return $this->record($ticketId, TicketActivity::ACTION_CREATED, null, $subject);
    }

    public function recordStatusChanged(int $ticketId, string $from, string $to): TicketActivity
    {
        return $this->record($ticketId, TicketActivity::ACTION_STATUS_CHANGED, $from, $to);
    }

    public function recordAssigned(int $ticketId, ?string $fromName, string $toName, int $assigneeId): TicketActivity
    {
        return $this->record(
            $ticketId,
            TicketActivity::ACTION_ASSIGNED,
            $fromName,
            $toName,
            ['assignee_id' => $assigneeId, 'assignee_name' => $toName]
        );
    }

    public function recordUnassigned(int $ticketId, ?string $fromName): TicketActivity
    {
        return $this->record($ticketId, TicketActivity::ACTION_UNASSIGNED, $fromName, null);
    }

    public function recordReplied(int $ticketId, string $excerpt, bool $isInternal = false): TicketActivity
    {
        $action = $isInternal ? TicketActivity::ACTION_NOTE_ADDED : TicketActivity::ACTION_REPLIED;
        return $this->record($ticketId, $action, null, null, ['excerpt' => mb_substr($excerpt, 0, 120)]);
    }

    public function recordPriorityChanged(int $ticketId, string $from, string $to): TicketActivity
    {
        return $this->record($ticketId, TicketActivity::ACTION_PRIORITY_CHANGED, $from, $to);
    }

    public function recordWatcherAdded(int $ticketId, string $watcherEmail): TicketActivity
    {
        return $this->record($ticketId, TicketActivity::ACTION_WATCHER_ADDED, null, $watcherEmail);
    }

    public function recordWatcherRemoved(int $ticketId, string $watcherEmail): TicketActivity
    {
        return $this->record($ticketId, TicketActivity::ACTION_WATCHER_REMOVED, $watcherEmail, null);
    }

    public function recordSlaBreached(int $ticketId, string $slaType): TicketActivity
    {
        return $this->record($ticketId, TicketActivity::ACTION_SLA_BREACHED, null, $slaType, [], null);
    }

    public function recordClosed(int $ticketId): TicketActivity
    {
        return $this->record($ticketId, TicketActivity::ACTION_CLOSED);
    }

    public function recordReopened(int $ticketId): TicketActivity
    {
        return $this->record($ticketId, TicketActivity::ACTION_REOPENED);
    }

    public function getTimeline(int $ticketId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return TicketActivity::where('ticket_id', $ticketId)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    public function getRecentActivity(int $ticketId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return TicketActivity::where('ticket_id', $ticketId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
