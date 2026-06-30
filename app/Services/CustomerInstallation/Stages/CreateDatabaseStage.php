<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use Illuminate\Support\Str;

class CreateDatabaseStage extends AbstractStage
{
    public function name(): string { return 'CREATE_DATABASE'; }
    public function label(): string { return 'ساخت پایگاه داده'; }
    public function estimatedSeconds(): int { return 3; }
    public function canRollback(): bool { return true; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $dbName = 'cust_' . Str::random(6) . '_' . $ctx->installation->id;
            $dbUser = 'u_' . Str::random(8);
            $dbPass = $this->generateDbPassword();
            $webRoot = $ctx->webRoot();

            $sqlCommands = [
                "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
                "CREATE USER IF NOT EXISTS '{$dbUser}'@'localhost' IDENTIFIED BY '{$dbPass}';",
                "GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'localhost';",
                "FLUSH PRIVILEGES;",
            ];

            foreach ($sqlCommands as $sql) {
                $result = $ctx->connection->execute(
                    'mysql -u root 2>/dev/null -e ' . escapeshellarg($sql),
                    ['MYSQL_PWD' => '']
                );
                // If root has no password access via socket, try without password env
                if ($result->failed()) {
                    $result = $ctx->connection->execute('mysql -u root -e ' . escapeshellarg($sql));
                    if ($result->failed()) {
                        return StageResult::failure(
                            $this->name(),
                            "MySQL command failed: {$sql}\n" . $result->output(),
                            $this->elapsed()
                        );
                    }
                }
            }

            // Update .env with real DB credentials.
            // Use | as sed delimiter (password charset never includes |).
            $envPath = $webRoot . '/.env';
            $sedCommands = [
                "sed -i 's|__DB_NAME__|{$dbName}|' " . escapeshellarg($envPath),
                "sed -i 's|__DB_USER__|{$dbUser}|' " . escapeshellarg($envPath),
                "sed -i 's|__DB_PASS__|{$dbPass}|' " . escapeshellarg($envPath),
            ];
            foreach ($sedCommands as $cmd) {
                $ctx->connection->execute($cmd);
            }

            return StageResult::success($this->name(), [
                'db_name' => $dbName,
                'db_user' => $dbUser,
            ], [
                'db_name' => $dbName,
                'db_user' => $dbUser,
            ], $this->elapsed());
        });
    }

    /**
     * Generate a 32-char password that satisfies MySQL validate_password STRONG policy.
     * Charset excludes /, &, \, ', ", `, $, | to be safe in sed and shell substitutions.
     */
    private function generateDbPassword(): string
    {
        $lower   = 'abcdefghijklmnopqrstuvwxyz';
        $upper   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digits  = '0123456789';
        $special = '!@#%^*_+=:,.?~';

        $pool    = $lower . $upper . $digits . $special;
        $poolLen = strlen($pool);

        // Guarantee at least one character from each required class
        $pass  = $lower[random_int(0, 25)];
        $pass .= $upper[random_int(0, 25)];
        $pass .= $digits[random_int(0, 9)];
        $pass .= $special[random_int(0, strlen($special) - 1)];

        for ($i = 4; $i < 32; $i++) {
            $pass .= $pool[random_int(0, $poolLen - 1)];
        }

        return str_shuffle($pass);
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        if (!empty($snapshot['db_name'])) {
            $ctx->connection->execute('mysql -u root -e ' . escapeshellarg("DROP DATABASE IF EXISTS `{$snapshot['db_name']}`;"));
        }
        if (!empty($snapshot['db_user'])) {
            $ctx->connection->execute('mysql -u root -e ' . escapeshellarg("DROP USER IF EXISTS '{$snapshot['db_user']}'@'localhost';"));
        }
    }
}
