<?php

namespace SaaSPlatform\Modules\Tickets\Dto;

use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

/**
 * Wraps an API reply array so canonical Blade views can use object-style access.
 *
 * Views use: $reply->created_at, $reply->author_type, $reply->user?->name,
 *            $reply->customer_name, $reply->message, $reply->attachments
 */
final class TicketReplyProxyData
{
    public readonly int|string $id;
    public readonly string  $message;
    public readonly string  $author_type;
    public readonly ?string $customer_name;
    public readonly Carbon  $created_at;
    public readonly ?array  $attachments;
    public readonly bool    $is_internal;

    public readonly ?object $user;

    public function __construct(array $data)
    {
        $this->id            = $data['id'] ?? 0;
        $this->message       = $data['message'] ?? '';
        $this->author_type   = $data['author_type'] ?? 'customer';
        $this->customer_name = $data['customer_name'] ?? null;
        $this->attachments   = is_array($data['attachments'] ?? null) ? $data['attachments'] : null;
        $this->is_internal   = (bool) ($data['is_internal'] ?? false);

        $rawTs = $data['created_at'] ?? now()->toIso8601String();
        $this->created_at = $rawTs instanceof Carbon ? $rawTs : Carbon::parse($rawTs);

        $userName = $data['user_name'] ?? ($data['author_name'] ?? null);
        $this->user = $userName
            ? (object) ['name' => $userName]
            : null;
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public static function collectionFrom(array $replies): \Illuminate\Support\Collection
    {
        return collect($replies)->map(fn($r) => new self(is_array($r) ? $r : (array) $r));
    }
}
