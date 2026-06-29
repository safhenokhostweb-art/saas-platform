<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;

/**
 * STAGE 10: REMOVE_DATABASE_USER
 *
 * Drops the MySQL user that was created exclusively for this customer.
 * Separated from REMOVE_DATABASE so the audit trail proves the user account
 * was also cleaned up (important for compliance).
 */
class RemoveDatabaseUserStage extends AbstractDeleteStage
{
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

            $dbUser = $ctx->dbUser;
            $sql    = "DROP USER IF EXISTS '{$dbUser}'@'localhost'; FLUSH PRIVILEGES;";

            $result = $ctx->connection->execute(
                'mysql -u root -e ' . escapeshellarg($sql) . ' 2>/dev/null; echo $?'
            );
            $exitCode = (int) trim($result->stdout);

            if ($exitCode !== 0) {
                $result2 = $ctx->connection->execute(
                    'mysql -u root -e ' . escapeshellarg($sql)
                );
                if ($result2->failed()) {
                    return DeleteStageResult::failure(
                        $this->name(),
                        "DROP USER failed: {$result2->output()}"
                    );
                }
            }

            return DeleteStageResult::success($this->name(), [
                'db_user' => $dbUser,
                'dropped' => true,
            ]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        if (!$ctx->dbUser) {
            return [['check' => 'DB user removal', 'passed' => true, 'detail' => 'skipped — no db_user']];
        }

        $out = $this->remoteExec($ctx,
            'mysql -u root -e ' . escapeshellarg("SELECT User FROM mysql.user WHERE User='{$ctx->dbUser}';") . ' 2>/dev/null | wc -l'
        );
        $exists = (int) trim($out) > 1;
        return [
            ['check' => "DB user {$ctx->dbUser} dropped", 'passed' => !$exists, 'detail' => $exists ? 'still exists' : 'gone'],
        ];
    }
}
