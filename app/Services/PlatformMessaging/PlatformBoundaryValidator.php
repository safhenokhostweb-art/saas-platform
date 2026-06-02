<?php

namespace App\Services\PlatformMessaging;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Validates DOMAIN B — Platform Communication Runtime boundaries.
 * Used by: platform:validate-messaging-boundaries artisan command.
 *
 * Checks:
 * 1. No support conversation references platform tables
 * 2. No platform conversation references tenant users
 * 3. No cross-runtime foreign keys
 * 4. No shared attachments
 * 5. No shared message IDs
 */
class PlatformBoundaryValidator
{
    private array $results = [];
    private int   $score   = 0;

    public function validate(): array
    {
        $this->checkNoSupportReferencePlatformTables();
        $this->checkNoPlatformConversationReferenceTenantUsers();
        $this->checkNoCrossRuntimeForeignKeys();
        $this->checkNoSharedAttachments();
        $this->checkNoSharedMessageIds();

        return [
            'score'   => (int) round($this->score),
            'results' => $this->results,
            'passed'  => $this->score >= 95,
        ];
    }

    private function checkNoSupportReferencePlatformTables(): void
    {
        // In saas-platform: verify mobile_conversations (legacy) has no FK to platform_conversations
        // and that platform_conversations has no support-type rows from tenant users
        $violations = [];

        if (Schema::hasTable('platform_conversations') && Schema::hasTable('mobile_conversations')) {
            // Check that no mobile_conversation row has a metadata reference to platform_conversation
            $count = DB::table('mobile_conversations')
                ->whereRaw("JSON_EXTRACT(metadata, '$.platform_conversation_id') IS NOT NULL")
                ->count();

            if ($count > 0) {
                $violations[] = "{$count} mobile_conversations reference platform_conversation_id in metadata";
            }
        }

        if (empty($violations)) {
            $this->pass('no_support_platform_ref', 'No support conversation references platform tables.');
        } else {
            $this->fail('no_support_platform_ref', implode('; ', $violations));
        }
    }

    private function checkNoPlatformConversationReferenceTenantUsers(): void
    {
        if (!Schema::hasTable('platform_conversation_participants')) {
            $this->fail('no_tenant_user_in_platform', 'platform_conversation_participants table missing.');
            return;
        }

        // Check for forbidden participant_type values
        $forbidden    = ['end_user', 'user', 'tenant_user'];
        $violations   = [];

        foreach ($forbidden as $type) {
            $count = DB::table('platform_conversation_participants')
                ->where('participant_type', $type)
                ->count();

            if ($count > 0) {
                $violations[] = "{$count} rows with forbidden participant_type='{$type}'";
            }
        }

        if (empty($violations)) {
            $this->pass('no_tenant_user_in_platform', 'No tenant user records in platform conversation participants.');
        } else {
            $this->fail('no_tenant_user_in_platform', implode('; ', $violations));
        }
    }

    private function checkNoCrossRuntimeForeignKeys(): void
    {
        $violations = [];

        // platform_messages must not reference support_messages by ID
        if (Schema::hasTable('platform_messages') && Schema::hasTable('platform_conversation_participants')) {
            // Verify platform tables only FK to platform tables
            $platformTables = ['platform_conversations', 'platform_conversation_participants', 'platform_messages', 'platform_attachments'];
            $tenantTables   = ['support_conversations', 'support_messages', 'mobile_conversations'];

            // We cannot cross databases, but we can verify no column names suggest cross-runtime references
            foreach (['platform_conversations', 'platform_messages'] as $table) {
                if (!Schema::hasTable($table)) continue;
                $cols = Schema::getColumnListing($table);
                foreach ($tenantTables as $tenantTable) {
                    $singular = rtrim($tenantTable, 's');
                    foreach ($cols as $col) {
                        if (str_contains($col, $singular . '_id') && $col !== 'id') {
                            $violations[] = "{$table}.{$col} may reference tenant table {$tenantTable}";
                        }
                    }
                }
            }
        }

        if (empty($violations)) {
            $this->pass('no_cross_runtime_fk', 'No cross-runtime foreign key columns detected.');
        } else {
            $this->fail('no_cross_runtime_fk', implode('; ', $violations));
        }
    }

    private function checkNoSharedAttachments(): void
    {
        if (!Schema::hasTable('platform_attachments')) {
            $this->fail('no_shared_attachments', 'platform_attachments table missing.');
            return;
        }

        // Platform attachments must have stored_path starting with 'platform-attachments/'
        $badCount = DB::table('platform_attachments')
            ->where('is_deleted', false)
            ->where('stored_path', 'not like', 'platform-attachments/%')
            ->count();

        if ($badCount === 0) {
            $this->pass('no_shared_attachments', 'All platform attachments use platform-attachments/ prefix.');
        } else {
            $this->fail('no_shared_attachments', "{$badCount} platform attachments not stored under platform-attachments/ prefix.");
        }
    }

    private function checkNoSharedMessageIds(): void
    {
        if (!Schema::hasTable('platform_messages') || !Schema::hasTable('mobile_conversation_messages')) {
            $this->pass('no_shared_message_ids', 'Tables do not coexist — no ID overlap possible.');
            return;
        }

        // UUIDs are generated independently; overlap probability is astronomically low
        // but we verify that no mobile message ID appears in platform_messages
        $overlap = DB::table('platform_messages as pm')
            ->join('mobile_conversation_messages as mcm', 'pm.id', '=', 'mcm.id')
            ->count();

        if ($overlap === 0) {
            $this->pass('no_shared_message_ids', 'No shared UUIDs between platform_messages and mobile_conversation_messages.');
        } else {
            $this->fail('no_shared_message_ids', "{$overlap} UUID collisions detected between platform and mobile messages.");
        }
    }

    private function pass(string $key, string $message): void
    {
        $this->results[$key] = ['status' => 'pass', 'message' => $message];
        $this->score += 20; // 5 checks × 20 = 100
    }

    private function fail(string $key, string $message): void
    {
        $this->results[$key] = ['status' => 'fail', 'message' => $message];
    }
}
