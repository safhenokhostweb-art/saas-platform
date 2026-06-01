<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Merge;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\MobileRuntime\Communication\Conversation;
use SaaSPlatform\CoreUtils\MobileRuntime\Communication\ConversationMessage;
use SaaSPlatform\CoreUtils\MobileRuntime\Communication\ConversationParticipant;

class ConversationMergeService
{
    /**
     * ادغام یک یا چند مکالمه منبع (source) به داخل مکالمه هدف (target).
     * تمام پیام‌ها و شرکت‌کنندگان به target منتقل می‌شوند.
     * مکالمات منبع با وضعیت «merged» بسته می‌شوند.
     */
    public function merge(
        string $targetId,
        array  $sourceIds,
        string $mergedBy,
        string $mergedByType,
        string $tenantId
    ): array {
        $target = Conversation::forTenant($tenantId)->find($targetId);
        if (! $target) {
            throw new \InvalidArgumentException("Target conversation not found: {$targetId}");
        }

        $sourceIds = array_values(array_unique(array_filter($sourceIds, fn ($id) => $id !== $targetId)));
        if (empty($sourceIds)) {
            throw new \InvalidArgumentException('At least one source conversation is required.');
        }

        $sources = Conversation::forTenant($tenantId)
            ->whereIn('id', $sourceIds)
            ->where('status', '!=', Conversation::STATUS_MERGED)
            ->get();

        if ($sources->isEmpty()) {
            throw new \InvalidArgumentException('No valid source conversations found.');
        }

        return DB::transaction(function () use ($target, $sources, $mergedBy, $mergedByType, $tenantId) {
            $totalMessages    = 0;
            $totalParticipants = 0;
            $mergedTitles     = [];

            foreach ($sources as $source) {
                [$messages, $participants] = $this->mergeOne($target, $source, $mergedBy);
                $totalMessages    += $messages;
                $totalParticipants += $participants;
                $mergedTitles[]   = $source->title ?? $source->id;
            }

            // پیام سیستمی در مکالمه هدف
            $this->insertSystemMessage($target->id, $mergedTitles, $mergedBy);

            // ثبت در لاگ ادغام
            DB::table('mobile_conversation_merge_log')->insert([
                'tenant_id'              => $tenantId,
                'target_conversation_id' => $target->id,
                'source_conversation_ids'=> json_encode($sources->pluck('id')->values()->all()),
                'merged_by'              => $mergedBy,
                'merged_by_type'         => $mergedByType,
                'messages_moved'         => $totalMessages,
                'participants_added'     => $totalParticipants,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);

            // به‌روزرسانی زمان آخرین پیام مکالمه هدف
            $target->touch();

            Log::info('mobile.conversation.merged', [
                'target'  => $target->id,
                'sources' => $sources->pluck('id')->all(),
                'by'      => $mergedBy,
                'tenant'  => $tenantId,
            ]);

            return [
                'target_id'           => $target->id,
                'merged_count'        => $sources->count(),
                'messages_moved'      => $totalMessages,
                'participants_added'  => $totalParticipants,
            ];
        });
    }

    private function mergeOne(Conversation $target, Conversation $source, string $mergedBy): array
    {
        // انتقال پیام‌ها
        $messagesMoved = ConversationMessage::where('conversation_id', $source->id)
            ->update(['conversation_id' => $target->id]);

        // انتقال شرکت‌کنندگان (بدون تکرار)
        $existingUserIds = ConversationParticipant::where('conversation_id', $target->id)
            ->whereNull('left_at')
            ->pluck('user_id')
            ->flip()
            ->all();

        $sourceParticipants = ConversationParticipant::where('conversation_id', $source->id)
            ->whereNull('left_at')
            ->get();

        $participantsAdded = 0;
        foreach ($sourceParticipants as $sp) {
            if (! isset($existingUserIds[$sp->user_id])) {
                ConversationParticipant::create([
                    'conversation_id' => $target->id,
                    'user_id'         => $sp->user_id,
                    'user_type'       => $sp->user_type,
                    'role'            => ConversationParticipant::ROLE_PARTICIPANT,
                    'joined_at'       => now(),
                    'metadata'        => array_merge($sp->metadata ?? [], ['merged_from' => $source->id]),
                ]);
                $participantsAdded++;
            }
        }

        // بستن شرکت‌کنندگان قدیمی مکالمه منبع
        ConversationParticipant::where('conversation_id', $source->id)
            ->whereNull('left_at')
            ->update(['left_at' => now()]);

        // علامت‌گذاری مکالمه منبع به عنوان merged
        $source->update([
            'status'         => Conversation::STATUS_MERGED,
            'merged_into_id' => $target->id,
            'metadata'       => array_merge($source->metadata ?? [], [
                'merged_into' => $target->id,
                'merged_at'   => now()->toIso8601String(),
                'merged_by'   => $mergedBy,
            ]),
        ]);

        return [$messagesMoved, $participantsAdded];
    }

    private function insertSystemMessage(string $conversationId, array $sourceTitles, string $mergedBy): void
    {
        $titlesStr = implode('، ', $sourceTitles);
        ConversationMessage::create([
            'conversation_id' => $conversationId,
            'sender_id'       => $mergedBy,
            'sender_type'     => 'system',
            'message_type'    => ConversationMessage::TYPE_SYSTEM,
            'content'         => "مکالمات ادغام شدند: {$titlesStr}",
            'metadata'        => ['event' => 'merge', 'merged_titles' => $sourceTitles],
            'is_deleted'      => false,
            'read_by'         => [],
        ]);
    }
}
