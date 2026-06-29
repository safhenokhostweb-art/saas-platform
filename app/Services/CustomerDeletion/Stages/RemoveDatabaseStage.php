<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;

/**
 * STAGE 9: REMOVE_DATABASE
 *
 * Drops the customer's MySQL database.
 * Uses IF EXISTS for idempotency — safe to retry if the pipeline was
 * interrupted after this stage had already run.
 */
class RemoveDatabaseStage extends AbstractDeleteStage
{
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

            $dbName = $ctx->dbName;
            $sql    = "DROP DATABASE IF EXISTS `{$dbName}`;";

            $result = $ctx->connection->execute(
                'mysql -u root -e ' . escapeshellarg($sql) . ' 2>/dev/null; echo $?'
            );
            $exitCode = (int) trim($result->stdout);

            if ($exitCode !== 0) {
                // Try without password env (some servers use socket auth)
                $result2 = $ctx->connection->execute(
                    'mysql -u root -e ' . escapeshellarg($sql)
                );
                if ($result2->failed()) {
                    return DeleteStageResult::failure(
                        $this->name(),
                        "DROP DATABASE failed: {$result2->output()}"
                    );
                }
            }

            return DeleteStageResult::success($this->name(), [
                'db_name' => $dbName,
                'dropped' => true,
            ]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        if (!$ctx->dbName) {
            return [['check' => 'Database removal', 'passed' => true, 'detail' => 'skipped — no db_name']];
        }

        $out = $this->remoteExec($ctx,
            'mysql -u root -e ' . escapeshellarg("SHOW DATABASES LIKE '{$ctx->dbName}';") . ' 2>/dev/null | wc -l'
        );
        $exists = (int) trim($out) > 1;
        return [
            ['check' => "Database {$ctx->dbName} dropped", 'passed' => !$exists, 'detail' => $exists ? 'still exists' : 'gone'],
        ];
    }
}
