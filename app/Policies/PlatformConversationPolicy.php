<?php

namespace App\Policies;

use App\Models\PlatformConversation;
use App\Models\PlatformConversationParticipant;
use App\Models\User;

/**
 * DOMAIN B — Access policy for platform conversations.
 *
 * Security guarantee:
 * - Customer A messages never visible to Customer B.
 * - End-user accounts have NO access.
 * - Platform Staff can only see conversations they participate in.
 * - Main Admin can see all platform conversations.
 */
class PlatformConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isPlatformActor($user);
    }

    public function view(User $user, PlatformConversation $conversation): bool
    {
        if (!$this->isPlatformActor($user)) {
            return false;
        }

        // Main Admin sees all
        if ($this->isMainAdmin($user)) {
            return true;
        }

        // Customer Admin can only see their own conversations
        // Platform Staff can only see conversations they're in
        return $conversation->participants()
            ->where('participant_id', $user->id)
            ->where('participant_type', $this->resolveParticipantType($user))
            ->whereNull('left_at')
            ->exists();
    }

    public function create(User $user): bool
    {
        return $this->isPlatformActor($user);
    }

    public function sendMessage(User $user, PlatformConversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function addParticipant(User $user, PlatformConversation $conversation): bool
    {
        // Only Main Admin or conversation owner can add participants
        if ($this->isMainAdmin($user)) {
            return true;
        }

        return $conversation->participants()
            ->where('participant_id', $user->id)
            ->where('role', PlatformConversationParticipant::ROLE_OWNER)
            ->whereNull('left_at')
            ->exists();
    }

    public function close(User $user, PlatformConversation $conversation): bool
    {
        return $this->isMainAdmin($user);
    }

    private function isPlatformActor(User $user): bool
    {
        // Deny end-users outright
        $participantType = $this->resolveParticipantType($user);
        return in_array($participantType, PlatformConversation::ALLOWED_PARTICIPANT_TYPES, true);
    }

    private function isMainAdmin(User $user): bool
    {
        return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()
            || method_exists($user, 'isMainAdmin') && $user->isMainAdmin()
            || (method_exists($user, 'hasRole') && $user->hasRole('admin'));
    }

    private function resolveParticipantType(User $user): string
    {
        if ($this->isMainAdmin($user)) {
            return PlatformConversationParticipant::TYPE_ADMIN;
        }
        if (method_exists($user, 'hasRole') && $user->hasRole('platform_staff')) {
            return PlatformConversationParticipant::TYPE_PLATFORM_STAFF;
        }
        if (method_exists($user, 'hasRole') && $user->hasRole('customer_admin')) {
            return PlatformConversationParticipant::TYPE_CUSTOMER_ADMIN;
        }
        // Default: end_user — will be denied by isPlatformActor()
        return 'end_user';
    }
}
