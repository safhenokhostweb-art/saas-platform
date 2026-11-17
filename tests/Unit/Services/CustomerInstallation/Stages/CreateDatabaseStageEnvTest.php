<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Dto\CommandResult;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Env\DotenvValue;
use App\Services\CustomerInstallation\Provisioning\CpanelDatabaseProvisioner;
use App\Services\CustomerInstallation\Provisioning\DatabaseProvisionerFactory;
use App\Services\CustomerInstallation\Provisioning\VpsDatabaseProvisioner;
use App\Services\CustomerInstallation\Stages\CreateDatabaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Installation 51 (2026-11-17) failed RUN_MIGRATIONS with MySQL 1045: the
 * password MySQL had was not the one Laravel read from .env, because the
 * unquoted value contained `#`. These tests run the stage's sed commands in
 * a real POSIX shell and decode the result with phpdotenv, exactly as the
 * customer site will.
 */
class CreateDatabaseStageEnvTest extends TestCase
{
    use RefreshDatabase;

    private const TEMPLATE = "APP_ENV=production\nDB_HOST=127.0.0.1\nDB_DATABASE=__DB_NAME__\nDB_USERNAME=__DB_USER__\nDB_PASSWORD=__DB_PASS__\n";

    private ?string $sh = null;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
        $this->sh = $this->findShell();
    }

    public function test_generated_passwords_satisfy_mysql_policy_and_survive_unquoted_dotenv(): void
    {
        $stage = app(CreateDatabaseStage::class);

        for ($i = 0; $i < 500; $i++) {
            $pw = $stage->generateDbPassword();

            $this->assertMatchesRegularExpression(CreateDatabaseStage::PASSWORD_CHARSET_PATTERN, $pw);
            $this->assertMatchesRegularExpression('/[a-z]/', $pw);
            $this->assertMatchesRegularExpression('/[A-Z]/', $pw);
            $this->assertMatchesRegularExpression('/[0-9]/', $pw);
            $this->assertMatchesRegularExpression('/[^A-Za-z0-9]/', $pw, 'validate_password special_char_count=1');
            $this->assertSame($pw, DotenvValue::parse("DB_PASSWORD={$pw}")['DB_PASSWORD']);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function hostilePasswords(): iterable
    {
        yield 'the installation 51 failure mode' => ['aB3xY9#kLm2!qq'];
        yield 'sed specials' => ['a&b|c\\d'];
        yield 'quotes and dollar' => ["q'uo\"te\$HOME"];
        yield 'leading hash' => ['#start'];
    }

    /** @dataProvider hostilePasswords */
    public function test_sed_command_writes_a_value_phpdotenv_reads_back_exactly(string $password): void
    {
        $sh = $this->requireShell();
        $file = $this->tempEnv();

        try {
            foreach (['__DB_NAME__' => 'cust_ab12CD_51', '__DB_USER__' => 'u_Vwa6d7Y7', '__DB_PASS__' => $password] as $placeholder => $value) {
                [$code, $out] = $this->runShell($sh, CreateDatabaseStage::envSedCommand($placeholder, $value, $file));
                $this->assertSame(0, $code, $out);
            }

            $parsed = DotenvValue::parse(file_get_contents($file));
            $this->assertSame($password, $parsed['DB_PASSWORD']);
            $this->assertSame('u_Vwa6d7Y7', $parsed['DB_USERNAME']);
            $this->assertSame('cust_ab12CD_51', $parsed['DB_DATABASE']);
            $this->assertSame('production', $parsed['APP_ENV']);
        } finally {
            @unlink($file);
        }
    }

    public function test_stage_end_to_end_mysql_password_equals_what_laravel_reads(): void
    {
        $sh = $this->requireShell();
        $file = $this->tempEnv();
        $created = [];

        // Runs the stage's sed/grep for real against a local copy of the remote .env.
        $driver = new class($file, fn (string $c) => $this->runShell($sh, $c)) extends FakeRemoteDriver {
            public function __construct(private string $file, private \Closure $run) { parent::__construct(); }

            public function execute(string $command, array $env = []): CommandResult
            {
                parent::execute($command, $env);
                if (str_starts_with($command, 'sed -i ') || str_starts_with($command, 'grep -E ')) {
                    $local = preg_replace("#'[^']*/\.env'#", "'" . $this->file . "'", $command);
                    [$code, $out] = ($this->run)($local);

                    return new CommandResult(exitCode: $code, stdout: $out, stderr: '', durationMs: 1);
                }

                return new CommandResult(exitCode: 0, stdout: '', stderr: '', durationMs: 1);
            }
        };

        $spy = new class($created) extends VpsDatabaseProvisioner {
            public function __construct(private array &$created) {}
            public function createDatabase(InstallationContext $ctx, string $dbName): void { $this->created['db'] = $dbName; }
            public function createUser(InstallationContext $ctx, string $dbUser, string $dbPassword): void { $this->created['user'] = $dbUser; $this->created['password'] = $dbPassword; }
            public function grantPrivileges(InstallationContext $ctx, string $dbName, string $dbUser): void {}
        };
        $stage = new CreateDatabaseStage(new DatabaseProvisionerFactory($spy, $this->createStub(CpanelDatabaseProvisioner::class)));

        try {
            $result = $stage->execute($this->context($driver));

            $this->assertTrue($result->success, (string) $result->error);
            $parsed = DotenvValue::parse(file_get_contents($file));
            $this->assertSame($created['password'], $parsed['DB_PASSWORD'], 'MySQL and Laravel must see the same password');
            $this->assertSame($created['user'], $parsed['DB_USERNAME']);
            $this->assertSame($created['db'], $parsed['DB_DATABASE']);
        } finally {
            @unlink($file);
        }
    }

    public function test_a_mismatch_read_back_fails_the_stage_before_migrations_without_leaking_the_secret(): void
    {
        $driver = new FakeRemoteDriver();
        // What phpdotenv made of a password containing '#': the prefix.
        $driver->addResponse('grep -E', 0, "DB_DATABASE=cust_x_1\nDB_USERNAME=u_x\nDB_PASSWORD=aB3xY9#kLm2\n");
        $calls = [];
        $spy = new class($calls) extends VpsDatabaseProvisioner {
            public function __construct(private array &$calls) {}
            public function createDatabase(InstallationContext $ctx, string $dbName): void { $this->calls[] = 'db'; }
            public function createUser(InstallationContext $ctx, string $dbUser, string $dbPassword): void { $this->calls[] = 'user'; }
            public function grantPrivileges(InstallationContext $ctx, string $dbName, string $dbUser): void { $this->calls[] = 'grant'; }
        };

        $result = (new CreateDatabaseStage(new DatabaseProvisionerFactory($spy, $this->createStub(CpanelDatabaseProvisioner::class))))
            ->execute($this->context($driver));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('.env', $result->error);
        $this->assertStringContainsString('مایگریشن', $result->error);
        $this->assertStringNotContainsString('aB3xY9', $result->error);
    }

    private function context(FakeRemoteDriver $driver): InstallationContext
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Fake', 'type' => 'fake', 'host' => 'localhost', 'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);
        $payload = [
            'company' => 'C', 'owner_name' => 'O', 'email' => '', 'phone' => '09120000000', 'language' => 'fa',
            'timezone' => 'Asia/Tehran', 'currency' => 'IRR', 'domain' => 'solar.example.ir', 'license_type' => 'basic',
            'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [], 'ssl_enabled' => false,
            'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
        ];
        $installation = CustomerInstallation::create([
            'remote_server_id' => $server->id, 'installer_user_id' => 1, 'install_mode' => 'MODE_FRESH', 'status' => 'running',
            'customer_company' => 'C', 'customer_email' => '', 'customer_domain' => 'solar.example.ir', 'request_payload' => $payload,
        ]);

        return new InstallationContext($installation, CustomerInstallRequest::fromArray($payload), $driver);
    }

    private function tempEnv(): string
    {
        $file = str_replace('\\', '/', tempnam(sys_get_temp_dir(), 'cie-env-'));
        file_put_contents($file, self::TEMPLATE);

        return $file;
    }

    private function requireShell(): string
    {
        if ($this->sh === null) {
            $this->markTestSkipped('No POSIX sh with sed available on this host.');
        }

        return $this->sh;
    }

    private function findShell(): ?string
    {
        foreach (['sh', 'C:/Program Files/Git/usr/bin/sh.exe', 'C:/Program Files/Git/bin/sh.exe'] as $candidate) {
            [$code, $out] = $this->runShell($candidate, "echo 'a|b' | sed 's|a|x|'");
            if ($code === 0 && trim($out) === 'x|b') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The command goes to sh as a script file, byte for byte, the way SSH
     * delivers it. Passing it as an argv item would let Windows' command-line
     * re-quoting of `"` and `\` change it before sh ever sees it.
     *
     * @return array{0: int, 1: string}
     */
    private function runShell(string $sh, string $command): array
    {
        $script = str_replace('\\', '/', tempnam(sys_get_temp_dir(), 'cie-sh-'));
        file_put_contents($script, $command . "\n");

        $process = @proc_open([$sh, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            @unlink($script);

            return [127, ''];
        }
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        @unlink($script);

        return [proc_close($process), $out];
    }
}
