<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Assignment;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\MobileRuntime\Communication\Conversation;
use SaaSPlatform\CoreUtils\MobileRuntime\Communication\ConversationParticipant;

/**
 * Conversation assignment engine with escalation chain and mandatory audit log.
 *
 * Escalation chain: Agent → Senior Agent → Supervisor → Platform Admin
 */
class ConversationAssignmentService
{
    // Escalation tier levels (ascending)
    public const TIER_AGENT          = 1;
    public const TIER_SENIOR_AGENT   = 2;
    public const TIER_SUPERVISOR     = 3;
    public const TIER_PLATFORM_ADMIN = 4;

    private const AUDIT_CHANNEL = 'assignment_audit';

    // ── Assign ────────────────────────────────────────────────────────────────

    public function assign(
        string $conversationId,
        string $assigneeId,
        string $assignedById,
        string $reason = ''
    ): array {
        $conversation = $this->findOrFail($conversationId);

        $previous = $conversation->metadata['assigned_to'] ?? null;

        $this->updateAssignmentMetadata($conversation, $assigneeId, [
            'assigned_to'   => $assigneeId,
            'assigned_by'   => $assignedById,
            'assigned_at'   => now()->toIso8601String(),
            'escalated'     => false,
        ]);

        // Ensure assignee is a participant
        $this->ensureParticipant($conversationId, $assigneeId);

        $this->audit($conversationId, 'assign', [
            'assignee_id'  => $assigneeId,
            'assigned_by'  => $assignedById,
            'previous'     => $previous,
            'reason'       => $reason,
        ]);

        $this->broadcastAssignmentChange($conversation, 'assigned');

        return $this->buildAssignmentResult($conversation, 'assigned');
    }

    // ── Reassign ──────────────────────────────────────────────────────────────

    public function reassign(
        string $conversationId,
        string $newAssigneeId,
        string $reassignedById,
        string $reason = ''
    ): array {
        $conversation = $this->findOrFail($conversationId);
        $previous     = $conversation->metadata['assigned_to'] ?? null;

        $this->updateAssignmentMetadata($conversation, $newAssigneeId, [
            'assigned_to'    => $newAssigneeId,
            'assigned_by'    => $reassignedById,
            'assigned_at'    => now()->toIso8601String(),
            'previous_agent' => $previous,
            'escalated'      => false,
        ]);

        $this->ensureParticipant($conversationId, $newAssigneeId);

        $this->audit($conversationId, 'reassign', [
            'new_assignee_id' => $newAssigneeId,
            'reassigned_by'   => $reassignedById,
            'previous'        => $previous,
            'reason'          => $reason,
        ]);

        $this->broadcastAssignmentChange($conversation, 'reassigned');

        return $this->buildAssignmentResult($conversation, 'reassigned');
    }

    // ── Unassign ──────────────────────────────────────────────────────────────

    public function unassign(
        string $conversationId,
        string $unassignedById,
        string $reason = ''
    ): array {
        $conversation = $this->findOrFail($conversationId);
        $previous     = $conversation->metadata['assigned_to'] ?? null;

        $metadata = $conversation->metadata ?? [];
        unset($metadata['assigned_to'], $metadata['assigned_by'], $metadata['assigned_at']);
        $metadata['unassigned_by'] = $unassignedById;
        $metadata['unassigned_at'] = now()->toIso8601String();

        $conversation->update(['metadata' => $metadata]);

        $this->audit($conversationId, 'unassign', [
            'previous'      => $previous,
            'unassigned_by' => $unassignedById,
            'reason'        => $reason,
        ]);

        $this->broadcastAssignmentChange($conversation, 'unassigned');

        return $this->buildAssignmentResult($conversation, 'unassigned');
    }

    // ── Escalate ──────────────────────────────────────────────────────────────

    public function escalate(
        string $conversationId,
        string $escalatedById,
        int $targetTier,
        string $reason = ''
    ): array {
        $conversation   = $this->findOrFail($conversationId);
        $currentTier    = (int) ($conversation->metadata['escalation_tier'] ?? self::TIER_AGENT);
        $newTier        = min($targetTier, self::TIER_PLATFORM_ADMIN);

        if ($newTier <= $currentTier) {
            throw new \InvalidArgumentException("Cannot escalate to tier {$newTier} — already at tier {$currentTier}.");
        }

        $metadata = $conversation->metadata ?? [];
        $metadata['escalated']         = true;
        $metadata['escalation_tier']   = $newTier;
        $metadata['escalated_by']      = $escalatedById;
        $metadata['escalated_at']      = now()->toIso8601String();
        $metadata['escalation_reason'] = $reason;
        $metadata['escalation_chain']  = array_merge(
            $metadata['escalation_chain'] ?? [],
            [[
                'from_tier'    => $currentTier,
                'to_tier'      => $newTier,
                'escalated_by' => $escalatedById,
                'escalated_at' => now()->toIso8601String(),
                'reason'       => $reason,
            ]]
        );

        $conversation->update(['metadata' => $metadata]);

        $this->audit($conversationId, 'escalate', [
            'from_tier'   => $currentTier,
            'to_tier'     => $newTier,
            'escalated_by' => $escalatedById,
            'reason'      => $reason,
        ]);

        $this->broadcastAssignmentChange($conversation, 'escalated');

        return $this->buildAssignmentResult($conversation, 'escalated');
    }

    // ── Assignment history (audit trail) ─────────────────────────────────────

    public function getAssignmentHistory(string $conversationId): array
    {
        return DB::table(self::AUDIT_CHANNEL)
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    // ── Current assignment ────────────────────────────────────────────────────

    public function getCurrentAssignment(string $conversationId): ?array
    {
        $conversation = Conversation::find($conversationId);
        if (! $conversation) return null;

        $meta = $conversation->metadata ?? [];
        if (empty($meta['assigned_to'])) return null;

        return [
            'assignee_id'     => $meta['assigned_to'],
            'assigned_by'     => $meta['assigned_by'] ?? null,
            'assigned_at'     => $meta['assigned_at'] ?? null,
            'escalated'       => $meta['escalated'] ?? false,
            'escalation_tier' => $meta['escalation_tier'] ?? self::TIER_AGENT,
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function findOrFail(string $conversationId): Conversation
    {
        $conversation = Conversation::find($conversationId);
        if (! $conversation) {
            throw new \RuntimeException("Conversation {$conversationId} not found.");
        }
        return $conversation;
    }

    private function updateAssignmentMetadata(
        Conversation $conversation,
        string $assigneeId,
        array $fields
    ): void {
        $metadata = array_merge($conversation->metadata ?? [], $fields);
        $conversation->update(['metadata' => $metadata]);
        $conversation->refresh();
    }

    private function ensureParticipant(string $conversationId, string $userId): void
    {
        $existing = ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->exists();

        if (! $existing) {
            ConversationParticipant::create([
                'conversation_id' => $conversationId,
                'user_id'         => $userId,
                'user_type'       => ConversationParticipant::USER_TYPE_AGENT,
                'role'            => ConversationParticipant::ROLE_PARTICIPANT,
                'joined_at'       => now(),
            ]);
        }
    }

    private function audit(string $conversationId, string $action, array $payload): void
    {
        try {
            DB::table(self::AUDIT_CHANNEL)->insert([
                'conversation_id' => $conversationId,
                'action'          => $action,
                'payload'         => json_encode($payload),
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            // Audit failures should not block assignment — log and continue
            Log::error('assignment.audit.failed', [
                'conversation_id' => $conversationId,
                'action'          => $action,
                'error'           => $e->getMessage(),
            ]);
        }

        Log::info("conversation.{$action}", array_merge(['conversation_id' => $conversationId], $payload));
    }

    private function broadcastAssignmentChange(Conversation $conversation, string $event): void
    {
        try {
            broadcast(new \SaaSPlatform\CoreUtils\MobileRuntime\Assignment\Events\ConversationAssignmentChanged(
                $conversation->id,
                $conversation->tenant_id,
                $event,
                $conversation->metadata ?? []
            ));
        } catch (\Throwable $e) {
            Log::warning('assignment.broadcast.failed', ['error' => $e->getMessage()]);
        }
    }

    private function buildAssignmentResult(Conversation $conversation, string $action): array
    {
        $meta = $conversation->metadata ?? [];
        return [
            'conversation_id' => $conversation->id,
            'action'          => $action,
            'assigned_to'     => $meta['assigned_to']     ?? null,
            'assigned_by'     => $meta['assigned_by']     ?? null,
            'assigned_at'     => $meta['assigned_at']     ?? null,
            'escalated'       => $meta['escalated']       ?? false,
            'escalation_tier' => $meta['escalation_tier'] ?? self::TIER_AGENT,
        ];
    }
}
