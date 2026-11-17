<?php

namespace App\Services\CustomerInstallation\Provisioning;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use RuntimeException;

/**
 * Phase 14-F2 — extracted verbatim from the pre-14-F2 CreateDatabaseStage.
 * Same SQL, same two-attempt retry (with/without MYSQL_PWD env), same error
 * message format, same rollback semantics (best-effort, never throws).
 * No behavior change for VPS/SSH installations.
 */
class VpsDatabaseProvisioner implements DatabaseProvisionerInterface
{
    /**
     * Every account exists for both client hosts. The site connects to
     * DB_HOST=127.0.0.1 over TCP. With skip_name_resolve=OFF (production,
     * 2026-11-17) MySQL maps that to 'localhost'; with it ON only a
     * '127.0.0.1' account would match.
     */
    public const HOSTS = ['localhost', '127.0.0.1'];

    public function createDatabase(InstallationContext $ctx, string $dbName): void
    {
        $this->runSql($ctx, "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;", 'CREATE DATABASE');
    }

    public function createUser(InstallationContext $ctx, string $dbUser, string $dbPassword): void
    {
        // ALTER USER after CREATE ... IF NOT EXISTS: an account that already
        // exists (a retried stage) must still end up with THIS password.
        $password = self::sqlString($dbPassword);
        $sql = '';
        foreach (self::HOSTS as $host) {
            $sql .= "CREATE USER IF NOT EXISTS '{$dbUser}'@'{$host}' IDENTIFIED BY {$password}; "
                . "ALTER USER '{$dbUser}'@'{$host}' IDENTIFIED BY {$password}; ";
        }
        $this->runSql($ctx, rtrim($sql), 'CREATE USER');
    }

    public function grantPrivileges(InstallationContext $ctx, string $dbName, string $dbUser): void
    {
        $sql = '';
        foreach (self::HOSTS as $host) {
            $sql .= "GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'{$host}'; ";
        }
        $this->runSql($ctx, rtrim($sql), 'GRANT PRIVILEGES');
        $this->runSql($ctx, "FLUSH PRIVILEGES;", 'FLUSH PRIVILEGES');
    }

    public function dropDatabase(InstallationContext $ctx, string $dbName): void
    {
        $ctx->connection->execute('mysql -u root -e ' . escapeshellarg("DROP DATABASE IF EXISTS `{$dbName}`;"));
    }

    public function dropUser(InstallationContext $ctx, string $dbUser): void
    {
        $sql = '';
        foreach (self::HOSTS as $host) {
            $sql .= "DROP USER IF EXISTS '{$dbUser}'@'{$host}'; ";
        }
        $ctx->connection->execute('mysql -u root -e ' . escapeshellarg(rtrim($sql)));
    }

    /** A MySQL single-quoted string literal (backslash and quote escaped). */
    private static function sqlString(string $value): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }

    /**
     * Phase 14-G3 — moved verbatim from the pre-14-G3 deletion stages'
     * verify() methods (same SHOW DATABASES query, same `wc -l > 1` parse),
     * so VPS verification behavior is unchanged. Never returns null on VPS:
     * if root mysql access is unavailable the query outputs nothing and this
     * reports false ("gone"), exactly as the old inline check did.
     */
    public function databaseExists(InstallationContext $ctx, string $dbName): ?bool
    {
        $result = $ctx->connection->execute(
            'mysql -u root -e ' . escapeshellarg("SHOW DATABASES LIKE '{$dbName}';") . ' 2>/dev/null | wc -l'
        );

        return (int) trim($result->stdout) > 1;
    }

    /** Phase 14-G3 — see databaseExists(). */
    public function databaseUserExists(InstallationContext $ctx, string $dbUser): ?bool
    {
        $result = $ctx->connection->execute(
            'mysql -u root -e ' . escapeshellarg("SELECT User FROM mysql.user WHERE User='{$dbUser}';") . ' 2>/dev/null | wc -l'
        );

        return (int) trim($result->stdout) > 1;
    }

    /**
     * Phase 14-G1 — $context is a short, secret-free label (e.g. "CREATE USER")
     * describing the statement kind, never the SQL itself. createUser() embeds
     * the freshly-generated tenant DB password directly into $sql via
     * IDENTIFIED BY '{password}' — putting the raw $sql in an exception message
     * (as this used to) means a failed CREATE_DATABASE stage persists the
     * plaintext password, unredacted, into customer_installation_logs.message
     * and customer_installations.failure_reason, both rendered on the admin
     * installation detail page. $result->output() (the mysql CLI's own
     * stdout/stderr) is intentionally still included — MySQL error output
     * does not echo back statement text for the statements this class runs.
     *
     * @throws RuntimeException
     */
    private function runSql(InstallationContext $ctx, string $sql, string $context): void
    {
        $result = $ctx->connection->execute(
            'mysql -u root 2>/dev/null -e ' . escapeshellarg($sql),
            ['MYSQL_PWD' => '']
        );

        // If root has no password access via socket, try without password env
        if ($result->failed()) {
            $result = $ctx->connection->execute('mysql -u root -e ' . escapeshellarg($sql));
            if ($result->failed()) {
                throw new RuntimeException("MySQL command failed ({$context}): " . $result->output());
            }
        }
    }
}
