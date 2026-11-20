<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;
use App\Services\CustomerDeletion\Guards\DatabaseNameGuard;
use App\Services\CustomerDeletion\Guards\PurgeGuardViolation;
use App\Services\CustomerInstallation\Provisioning\DatabaseProvisionerFactory;

/**
 * STAGE 10: REMOVE_DATABASE_USER
 *
 * Drops the DB user that was created exclusively for this customer.
 * Separated from REMOVE_DATABASE so the audit trail proves the user account
 * was also cleaned up (important for compliance).
 *
 * Phase 14-G3 — no direct SQL remains in this stage; drop + verification go
 * through DatabaseProvisionerFactory (VPS: root mysql `DROP USER IF EXISTS`,
 * cPanel: UAPI Mysql::delete_user). See RemoveDatabaseStage for the pattern.
 */
class RemoveDatabaseUserStage extends AbstractDeleteStage
{
    public function __construct(private readonly DatabaseProvisionerFactory $provisioners) {}

    public function name(): string { return 'REMOVE_DATABASE_USER'; }
    public function label(): string { return 'حذف کاربر پایگاه داده'; }
    public function isCritical(): bool { return false; }
    public function estimatedSeconds(): int { return 3; }

    public function buildPreSnapshot(DeleteContext $ctx): array
    {
        return ['db_user' => $ctx->dbUser];
    }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            if (!$ctx->dbUser) {
                return DeleteStageResult::skipped(
                    $this->name(),
                    'نام کاربر پایگاه داده در متادیتا موجود نیست — احتمالاً کاربری ایجاد نشده'
                );
            }

            try {
                DatabaseNameGuard::assertUser($ctx->dbUser);
            } catch (PurgeGuardViolation $e) {
                return DeleteStageResult::manual($this->name(), 'کاربر پایگاه داده خودکار حذف نشد — ' . $e->getMessage());
            }

            if (!$ctx->connection->capabilities()->supportsDatabaseDeletion) {
                return DeleteStageResult::skipped(
                    $this->name(),
                    'این درایور امکان حذف کاربر پایگاه داده ندارد — حذف دستی لازم است: ' . $ctx->dbUser
                );
            }

            $provisioner = $this->provisioners->forDriverType($ctx->connection->driverType());
            $ictx        = $ctx->installationContext();

            $provisioner->dropUser($ictx, $ctx->dbUser);

            $exists = $provisioner->databaseUserExists($ictx, $ctx->dbUser);

            if ($exists === true) {
                return DeleteStageResult::failure(
                    $this->name(),
                    "DROP USER issued but user still exists: {$ctx->dbUser}"
                );
            }

            return DeleteStageResult::success($this->name(), [
                'db_user'  => $ctx->dbUser,
                'dropped'  => true,
                'verified' => $exists === false,
            ]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        if (!$ctx->dbUser) {
            return [['check' => 'DB user removal', 'passed' => true, 'detail' => 'skipped — no db_user']];
        }

        if (!$ctx->connection->capabilities()->supportsDatabaseDeletion) {
            return [['check' => 'DB user removal', 'passed' => true, 'detail' => 'skipped — driver cannot manage databases']];
        }

        $exists = $this->provisioners
            ->forDriverType($ctx->connection->driverType())
            ->databaseUserExists($ctx->installationContext(), $ctx->dbUser);

        if ($exists === null) {
            return [['check' => "DB user {$ctx->dbUser} dropped", 'passed' => true, 'detail' => 'unverifiable on this driver — drop was issued']];
        }

        return [
            ['check' => "DB user {$ctx->dbUser} dropped", 'passed' => !$exists, 'detail' => $exists ? 'still exists' : 'gone'],
        ];
    }
}
