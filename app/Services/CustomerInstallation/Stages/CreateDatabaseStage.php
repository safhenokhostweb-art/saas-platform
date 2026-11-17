<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\CustomerInstallation\Env\DotenvValue;
use App\Services\CustomerInstallation\Provisioning\DatabaseNameResolver;
use App\Services\CustomerInstallation\Provisioning\DatabaseProvisionerFactory;

/**
 * Phase 14-F2 — this stage no longer knows `mysql`, SSH, or cPanel. It
 * resolves hosting-appropriate names via DatabaseNameResolver, resolves the
 * right DatabaseProvisionerInterface via DatabaseProvisionerFactory for
 * whatever driver is connected, and calls only that interface. The .env
 * update below is NOT hosting-specific: it uses $ctx->connection->execute(),
 * the same generic primitive every driver (including CpanelDeploymentDriver)
 * implements identically over its own transport.
 */
class CreateDatabaseStage extends AbstractStage
{
    public function __construct(private readonly DatabaseProvisionerFactory $provisioners) {}

    public function name(): string { return 'CREATE_DATABASE'; }
    public function label(): string { return 'ساخت پایگاه داده'; }
    public function estimatedSeconds(): int { return 3; }
    public function canRollback(): bool { return true; }

    /**
     * Phase 14-E — this stage's mechanism requires the connected driver to
     * be able to create a database. Essential: a site cannot run without
     * one, so a missing capability fails the installation rather than
     * skipping (isCapabilityRequiredToProceed() default = true).
     */
    public function requiredCapabilities(): array
    {
        return ['supportsDatabaseCreation'];
    }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $server = CustomerRemoteServer::find($ctx->installation->remote_server_id);
            $names  = (new DatabaseNameResolver())->resolve($ctx, $server);
            $dbPass = $this->generateDbPassword();

            $provisioner = $this->provisioners->forDriverType($ctx->connection->driverType());

            $provisioner->createDatabase($ctx, $names->database);
            $provisioner->createUser($ctx, $names->username, $dbPass);
            $provisioner->grantPrivileges($ctx, $names->database, $names->username);

            // Update .env with real DB credentials, each value encoded the way
            // phpdotenv decodes it (DotenvValue) and the whole sed script
            // shell-quoted. Installation 51 (2026-11-17): the raw, unquoted
            // password contained `#`, and phpdotenv cut it there (MySQL 1045).
            $webRoot = $ctx->webRoot();
            $envPath = $webRoot . '/.env';
            $expected = [
                'DB_DATABASE' => $names->database,
                'DB_USERNAME' => $names->username,
                'DB_PASSWORD' => $dbPass,
            ];
            $placeholders = ['DB_DATABASE' => '__DB_NAME__', 'DB_USERNAME' => '__DB_USER__', 'DB_PASSWORD' => '__DB_PASS__'];
            foreach ($placeholders as $key => $placeholder) {
                $ctx->connection->execute(self::envSedCommand($placeholder, $expected[$key], $envPath));
            }

            // Read the lines back and decode them exactly as Laravel will, so
            // a mismatch fails here (no secret in the message), not as a 1045
            // inside RUN_MIGRATIONS.
            $readBack = $ctx->connection->execute("grep -E '^DB_(DATABASE|USERNAME|PASSWORD)=' " . self::posixQuote($envPath));
            $written = trim((string) $readBack->stdout);
            if ($written !== '') {
                $parsed = DotenvValue::parse($written);
                foreach ($expected as $key => $value) {
                    if (($parsed[$key] ?? null) !== $value) {
                        return StageResult::failure($this->name(),
                            "مقدار {$key} در فایل .env سایت پس از نوشتن با مقدار ساخته‌شده در MySQL یکسان نیست؛ نصب پیش از اجرای مایگریشن متوقف شد.",
                            $this->elapsed());
                    }
                }
            }

            return StageResult::success($this->name(), [
                'db_name' => $names->database,
                'db_user' => $names->username,
            ], [
                'db_name' => $names->database,
                'db_user' => $names->username,
            ], $this->elapsed());
        });
    }

    /**
     * `sed -i` that replaces $placeholder in the remote .env with the
     * dotenv-encoded $value. The script is POSIX-quoted explicitly, because
     * escapeshellarg() quotes for the platform's own OS (double quotes on
     * Windows) while this runs in the remote server's sh.
     */
    public static function envSedCommand(string $placeholder, string $value, string $envPath): string
    {
        $script = 's|' . $placeholder . '|' . self::sedReplacement(DotenvValue::encode($value)) . '|';

        return 'sed -i ' . self::posixQuote($script) . ' ' . self::posixQuote($envPath);
    }

    /** Escape a sed `s|…|…|` replacement: `\`, `&` and the `|` delimiter are special. */
    private static function sedReplacement(string $value): string
    {
        return str_replace(['\\', '&', '|', "\n"], ['\\\\', '\\&', '\\|', ''], $value);
    }

    private static function posixQuote(string $value): string
    {
        return "'" . str_replace("'", "'\\''", $value) . "'";
    }

    /**
     * 32 chars satisfying MySQL validate_password (production: policy
     * MEDIUM, special_char_count=1, so a purely alphanumeric password would
     * be rejected by CREATE USER).
     *
     * Specials are only `-`, `_` and `.`: inert unquoted in .env (phpdotenv
     * treats `#` anywhere as a comment and `$` as interpolation, which cut
     * installation 51's password), in sed, in SQL literals and in the shell.
     * Entropy stays above 190 bits.
     */
    public const PASSWORD_CHARSET_PATTERN = '/^[A-Za-z0-9._-]{32}$/';

    public function generateDbPassword(): string
    {
        $lower   = 'abcdefghijklmnopqrstuvwxyz';
        $upper   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digits  = '0123456789';
        $special = '-_.';

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
        $provisioner = $this->provisioners->forDriverType($ctx->connection->driverType());

        if (!empty($snapshot['db_name'])) {
            $provisioner->dropDatabase($ctx, $snapshot['db_name']);
        }
        if (!empty($snapshot['db_user'])) {
            $provisioner->dropUser($ctx, $snapshot['db_user']);
        }
    }
}
