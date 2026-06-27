<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

class VerifyPackageStage extends AbstractStage
{
    public function name(): string { return 'VERIFY_PACKAGE'; }
    public function label(): string { return 'تأیید یکپارچگی بسته'; }
    public function estimatedSeconds(): int { return 5; }
    public function canRollback(): bool { return true; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $uploadOutput = $ctx->getOutput('UPLOAD_PACKAGE');
            $remotePath = $uploadOutput['remote_path'] ?? null;
            $expectedSha256 = $uploadOutput['sha256'] ?? $ctx->installation->package_sha256;

            if (!$remotePath || !$expectedSha256) {
                return StageResult::failure($this->name(), 'No remote path or expected SHA-256 from UPLOAD_PACKAGE stage', $this->elapsed());
            }

            $result = $ctx->connection->execute('sha256sum ' . escapeshellarg($remotePath) . ' 2>&1');
            if ($result->failed()) {
                return StageResult::failure($this->name(), 'sha256sum failed on remote: ' . $result->stdout, $this->elapsed());
            }

            $remoteSha256 = strtolower(explode(' ', trim($result->stdout))[0] ?? '');
            $expected = strtolower($expectedSha256);

            if (!hash_equals($expected, $remoteSha256)) {
                $ctx->connection->execute('rm -f ' . escapeshellarg($remotePath));
                return StageResult::failure(
                    $this->name(),
                    "SHA-256 mismatch. Expected: {$expected}, Got: {$remoteSha256}. Package deleted.",
                    $this->elapsed()
                );
            }

            return StageResult::success($this->name(), [
                'remote_sha256'   => $remoteSha256,
                'expected_sha256' => $expected,
                'verified'        => true,
            ], ['remote_path' => $remotePath], $this->elapsed());
        });
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        if (!empty($snapshot['remote_path'])) {
            $ctx->connection->execute('rm -f ' . escapeshellarg($snapshot['remote_path']));
        }
    }
}
