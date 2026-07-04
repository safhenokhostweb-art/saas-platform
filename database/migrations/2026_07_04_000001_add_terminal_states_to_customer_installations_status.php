<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TERMINAL_STATES = [
        'INSTALLATION_READY',
        'INSTALLATION_BLOCKED',
        'VERIFY_SUCCESS',
        'VERIFY_FAILED',
        'CUSTOMER_READY',
        'FAILED',
    ];

    public function up(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE customer_installations MODIFY COLUMN status ENUM(
    'pending','running','complete','failed','rolling_back','rolled_back','rollback_failed','deleted',
    'INSTALLATION_READY','INSTALLATION_BLOCKED','VERIFY_SUCCESS','VERIFY_FAILED','CUSTOMER_READY','FAILED'
) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT 'pending'
SQL);
    }

    public function down(): void
    {
        if (DB::table('customer_installations')
            ->whereIn('status', self::TERMINAL_STATES)
            ->exists()) {
            throw new \RuntimeException(
                'Cannot remove terminal installation states while records still use them.',
            );
        }

        DB::statement(<<<'SQL'
ALTER TABLE customer_installations MODIFY COLUMN status ENUM(
    'pending','running','complete','failed','rolling_back','rolled_back','rollback_failed','deleted'
) NOT NULL DEFAULT 'pending'
SQL);
    }
};
