<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;
use App\Services\CustomerInstallation\Provisioning\DatabaseProvisionerFactory;

/**
 * STAGE 9: REMOVE_DATABASE
 *
 * Drops the customer's database through the hosting-appropriate provisioner.
 *
 * Phase 14-G3 — no direct SQL remains in this stage. It resolves a
 * DatabaseProvisionerInterface via DatabaseProvisionerFactory (exactly like
 * CreateDatabaseStage on the install side): VPS issues the same
 * `DROP DATABASE IF EXISTS` root-mysql command as before, cPanel calls UAPI
 * Mysql::delete_database. Because the provisioner's drop is best-effort by
 * contract, the stage verifies the outcome with the provisioner's read-only
 * existence probe and fails (non-critically) if the database survived.
 */
class RemoveDatabaseStage extends AbstractDeleteStage
{
    public function __construct(private readonly DatabaseProvisionerFactory $provisioners) {}

    public function name(): string { return 'REMOVE_DATABASE'; }
    public function label(): string { return 'حذف پایگاه داده'; }
    public function isCritical(): bool { return false; }
    public function estimatedSeconds(): int { return 5; }

    public function buildPreSnapshot(DeleteContext $ctx): array
    {
        return ['db_name' => $ctx->dbName];
    }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            if (!$ctx->dbName) {
                return DeleteStageResult::skipped(
                    $this->name(),
                    'نام پایگاه داده در متادیتای نصب موجود نیست — احتمالاً پایگاه داده‌ای ایجاد نشده'
                );
            }

            if (!$ctx->connection->capabilities()->supportsDatabaseDeletion) {
                return DeleteStageResult::skipped(
                    $this->name(),
                    'این درایور امکان حذف پایگاه داده ندارد — حذف دستی لازم است: ' . $ctx->dbName
                );
            }

            $provisioner = $this->provisioners->forDriverType($ctx->connection->driverType());
            $ictx        = $ctx->installationContext();

            $provisioner->dropDatabase($ictx, $ctx->dbName);

            // Drop is best-effort by provisioner contract — verify it stuck.
            // null = unverifiable on this driver/transport: treat as issued.
            $exists = $provisioner->databaseExists($ictx, $ctx->dbName);

            if ($exists === true) {
                return DeleteStageResult::failure(
                    $this->name(),
                    "DROP DATABASE issued but database still exists: {$ctx->dbName}"
                );
            }

            return DeleteStageResult::success($this->name(), [
                'db_name'  => $ctx->dbName,
                'dropped'  => true,
                'verified' => $exists === false,
            ]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        if (!$ctx->dbName) {
            return [['check' => 'Database removal', 'passed' => true, 'detail' => 'skipped — no db_name']];
        }

        if (!$ctx->connection->capabilities()->supportsDatabaseDeletion) {
            return [['check' => 'Database removal', 'passed' => true, 'detail' => 'skipped — driver cannot manage databases']];
        }

        $exists = $this->provisioners
            ->forDriverType($ctx->connection->driverType())
            ->databaseExists($ctx->installationContext(), $ctx->dbName);

        if ($exists === null) {
            return [['check' => "Database {$ctx->dbName} dropped", 'passed' => true, 'detail' => 'unverifiable on this driver — drop was issued']];
        }

        return [
            ['check' => "Database {$ctx->dbName} dropped", 'passed' => !$exists, 'detail' => $exists ? 'still exists' : 'gone'],
        ];
    }
}
