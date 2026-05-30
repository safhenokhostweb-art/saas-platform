<?php

namespace SaaSPlatform\Modules\Tickets\Dto;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Wraps an API ticket array so canonical Blade views can use object-style access.
 *
 * Provides the same property interface as the Eloquent Ticket model, allowing
 * tickets::site.tickets.* views to render identically in customer-site context.
 *
 * Canonical views use: $ticket->subject, $ticket->ticket_number, $ticket->status,
 *   $ticket->priority, $ticket->description, $ticket->customer_name,
 *   $ticket->customer_mobile, $ticket->created_at, $ticket->id,
 *   $ticket->site?->name, $ticket->assignee?->name, $ticket->replies,
 *   $ticket->attachments, $ticket->category, $ticket->tags
 */
final class TicketProxyData
{
    public readonly int|string $id;
    public readonly string     $ticket_number;
    public readonly string     $subject;
    public readonly string     $description;
    public readonly string     $status;
    public readonly string     $priority;
    public readonly ?string    $customer_name;
    public readonly ?string    $customer_mobile;
    public readonly ?string    $category;
    public readonly ?array     $tags;
    public readonly ?array     $attachments;
    public readonly Carbon     $created_at;
    public readonly Carbon     $updated_at;

    public readonly ?object    $site;
    public readonly ?object    $assignee;
    public readonly Collection $replies;
    public readonly Collection $publicReplies;

    /** Set after construction: 'platform' | 'user' */
    public ?string $channel = null;

    public function __construct(array $data)
    {
        $this->id             = $data['id'] ?? 0;
        $this->ticket_number  = $data['ticket_number'] ?? ('TKT-' . ($data['id'] ?? '0'));
        $this->subject        = $data['subject'] ?? '';
        $this->description    = $data['description'] ?? $data['message'] ?? '';
        $this->status         = $data['status'] ?? 'open';
        $this->priority       = $data['priority'] ?? 'normal';
        $this->customer_name   = $data['customer_name'] ?? null;
        $this->customer_mobile = $data['customer_mobile'] ?? $data['mobile'] ?? null;
        $this->category       = $data['category'] ?? null;
        $this->tags           = is_array($data['tags'] ?? null) ? $data['tags'] : null;
        $this->attachments    = is_array($data['attachments'] ?? null) ? $data['attachments'] : null;

        $rawCreated = $data['created_at'] ?? now()->toIso8601String();
        $rawUpdated = $data['updated_at'] ?? $rawCreated;
        $this->created_at = $rawCreated instanceof Carbon ? $rawCreated : Carbon::parse($rawCreated);
        $this->updated_at = $rawUpdated instanceof Carbon ? $rawUpdated : Carbon::parse($rawUpdated);

        $siteName = $data['site_name'] ?? ($data['site']['name'] ?? null);
        $this->site = $siteName ? (object) ['name' => $siteName, 'id' => $data['site_id'] ?? null] : null;

        $assigneeName = $data['assignee_name'] ?? ($data['assignee']['name'] ?? null);
        $this->assignee = $assigneeName ? (object) ['name' => $assigneeName] : null;

        $rawReplies = $data['replies'] ?? [];
        $this->replies = TicketReplyProxyData::collectionFrom(
            is_array($rawReplies) ? $rawReplies : []
        );
        // All API-sourced replies are public; publicReplies mirrors replies for view compatibility.
        $this->publicReplies = $this->replies;
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public static function collectionFromApiResult($tickets): Collection
    {
        if ($tickets instanceof Collection) {
            return $tickets->map(fn($t) => new self(is_array($t) ? $t : (array) $t));
        }

        return collect((array) $tickets)->map(fn($t) => new self(is_array($t) ? $t : (array) $t));
    }

    /**
     * Build a TicketProxyData from an Eloquent Ticket model (for unified inbox).
     * Requires 'replies' and 'assignee' relations to be eager-loaded.
     */
    public static function fromEloquent(\SaaSPlatform\Modules\Tickets\Models\Ticket $model): self
    {
        $replies = $model->relationLoaded('replies')
            ? $model->replies->map(fn($r) => [
                'id'          => $r->id,
                'message'     => $r->message,
                'author_type' => $r->author_type,
                'author_name' => $r->user?->name ?? $r->customer_name ?? null,
                'is_internal' => (bool) ($r->is_internal ?? false),
                'created_at'  => $r->created_at->toIso8601String(),
                'attachments' => $r->attachments,
            ])->toArray()
            : [];

        return new self([
            'id'             => $model->id,
            'ticket_number'  => $model->ticket_number,
            'subject'        => $model->subject,
            'description'    => $model->description,
            'status'         => $model->status,
            'priority'       => $model->priority,
            'customer_name'   => $model->customer_name,
            'customer_mobile' => $model->customer_mobile,
            'category'       => $model->category,
            'created_at'     => $model->created_at->toIso8601String(),
            'updated_at'     => $model->updated_at->toIso8601String(),
            'assignee_name'  => $model->assignee?->name,
            'replies'        => $replies,
        ]);
    }

    /** Build summary stats from a collection of TicketProxyData. */
    public static function statsFrom(Collection $tickets): array
    {
        return [
            'total'       => $tickets->count(),
            'open'        => $tickets->where('status', 'open')->count(),
            'in_progress' => $tickets->where('status', 'in_progress')->count(),
            'closed'      => $tickets->where('status', 'closed')->count(),
            'sla_breaches' => 0,
        ];
    }
}
