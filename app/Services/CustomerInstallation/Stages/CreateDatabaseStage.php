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
            $resolvedNames = (new DatabaseNameResolver())->resolve($ctx, $server);

            // Use reviewed/custom names if specified in request, otherwise use resolved defaults
            $dbName = !empty($ctx->request->dbName) ? preg_replace('/[^a-zA-Z0-9_]/', '', $ctx->request->dbName) : $resolvedNames->database;
            $dbUser = !empty($ctx->request->dbUser) ? preg_replace('/[^a-zA-Z0-9_]/', '', $ctx->request->dbUser) : $resolvedNames->username;
            $dbPass = !empty($ctx->request->dbPassword) ? $ctx->request->dbPassword : $this->generateDbPassword();

            $provisioner = $this->provisioners->forDriverType($ctx->connection->driverType());

            // P0 safety (2026-11-19): reviewed/custom names (the wizard now
            // always sends them) can collide with another tenant on the same
            // server. The provisioners are idempotent (CREATE ... IF NOT
            // EXISTS, ALTER USER ... IDENTIFIED BY), so a collision silently
            // reset the other site's password and granted it to this one, and
            // rollback() then DROPPED the other site's database. Refuse any
            // name that already exists; fail closed when a custom name cannot
            // be checked. Random generated names keep the old behaviour when
            // the driver cannot answer (null).
            $customNames = !empty($ctx->request->dbName) || !empty($ctx->request->dbUser);
            $dbExists = $provisioner->databaseExists($ctx, $dbName);
            $userExists = $provisioner->databaseUserExists($ctx, $dbUser);
            if ($dbExists === true || $userExists === true) {
                return StageResult::failure($this->name(), sprintf(
                    'نام %s از قبل روی این سرور وجود دارد و ممکن است متعلق به سایت دیگری باشد؛ برای جلوگیری از آسیب به آن، پایگاه داده ساخته نشد. نام دیگری انتخاب کنید.',
                    $dbExists === true ? "پایگاه داده «{$dbName}»" : "کاربر «{$dbUser}»",
                ), $this->elapsed());
            }
            if ($customNames && ($dbExists === null || $userExists === null)) {
                return StageResult::failure(
                    $this->name(),
                    'وجود پایگاه داده/کاربر روی سرور قابل بررسی نبود؛ نام‌های سفارشی بدون این بررسی استفاده نمی‌شوند.',
                    $this->elapsed(),
                );
            }

            $provisioner->createDatabase($ctx, $dbName);
            $provisioner->createUser($ctx, $dbUser, $dbPass);
            $provisioner->grantPrivileges($ctx, $dbName, $dbUser);

            // Update .env with real DB credentials, each value encoded the way
            // phpdotenv decodes it (DotenvValue) and the whole sed script
            // shell-quoted. Installation 51 (2026-11-17): the raw, unquoted
            // password contained `#`, and phpdotenv cut it there (MySQL 1045).
            $webRoot = $ctx->webRoot();
            $envPath = $webRoot . '/.env';
            $expected = [
                'DB_DATABASE' => $dbName,
                'DB_USERNAME' => $dbUser,
                'DB_PASSWORD' => $dbPass,
            ];
            $placeholders = ['DB_DATABASE' => '__DB_NAME__', 'DB_USERNAME' => '__DB_USER__', 'DB_PASSWORD' => '__DB_PASS__'];
            foreach ($placeholders as $key => $placeholder) {
                $ctx->connection->execute(self::envSedCommand($placeholder, $expected[$key], $envPath));
            }

            if (!empty($ctx->request->dbHost) && $ctx->request->dbHost !== '127.0.0.1' && $ctx->request->dbHost !== 'localhost') {
                $ctx->connection->execute(self::envSedCommand('DB_HOST=127.0.0.1', 'DB_HOST=' . $ctx->request->dbHost, $envPath));
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
                'db_name' => $dbName,
                'db_user' => $dbUser,
            ], [
                'db_name' => $dbName,
                'db_user' => $dbUser,
                // Ownership marker: this stage verified neither existed and
                // created both, so rollback() may drop exactly these.
                'created_by_stage' => true,
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
        // Snapshots written since 2026-11-19 carry created_by_stage; without
        // it (an older in-flight installation) the stage keeps the previous
        // behaviour, whose names were random and therefore its own.
        if (array_key_exists('created_by_stage', $snapshot) && $snapshot['created_by_stage'] !== true) {
            return;
        }

        $provisioner = $this->provisioners->forDriverType($ctx->connection->driverType());

        if (!empty($snapshot['db_name'])) {
            $provisioner->dropDatabase($ctx, $snapshot['db_name']);
        }
        if (!empty($snapshot['db_user'])) {
            $provisioner->dropUser($ctx, $snapshot['db_user']);
        }
    }
}
