<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use Illuminate\Support\Facades\Auth;
use SaaSPlatform\Modules\Tickets\Models\Ticket;
use SaaSPlatform\Modules\Tickets\Models\TicketReply;

class TicketInternalNotesService
{
    public function __construct(
        protected TicketTimelineService $timeline,
    ) {}

    public function add(int $ticketId, string $noteText, ?int $authorId = null): TicketReply
    {
        $authorId ??= Auth::id();

        $note = TicketReply::create([
            'ticket_id'   => $ticketId,
            'user_id'     => $authorId,
            'message'     => $noteText,
            'author_type' => TicketReply::AUTHOR_STAFF,
            'is_internal' => true,
        ]);

        $this->timeline->recordReplied($ticketId, $noteText, true);

        return $note;
    }

    public function list(int $ticketId): \Illuminate\Database\Eloquent\Collection
    {
        return TicketReply::where('ticket_id', $ticketId)
            ->where('is_internal', true)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function delete(int $noteId, ?int $actorId = null): bool
    {
        $note = TicketReply::find($noteId);

        if (! $note || ! $note->is_internal) {
            return false;
        }

        return (bool) $note->delete();
    }
}
