<?php

namespace Tests\Unit\CustomerInstallation;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Drivers\SshRemoteDriver;
use phpseclib3\Net\SSH2;
use ReflectionClass;
use Tests\TestCase;

/**
 * Phase 13-O2 — verifies SshRemoteDriver no longer reports "false success"
 * when the remote SSH server doesn't send a usable exit status, or when a
 * command times out mid-execution. Drives SshRemoteDriver::execute() with a
 * mocked phpseclib3 SSH2 instance injected via reflection (bypassing
 * connect(), which needs a real network round trip) so the test exercises
 * doExecute()'s exit-status/timeout branching directly.
 */
class SshRemoteDriverExitStatusTest extends TestCase
{
    private function makeDriverWithMockedSsh(SSH2 $ssh): SshRemoteDriver
    {
        $driver = new SshRemoteDriver(new CustomerRemoteServer(), new CredentialVault());

        $ref = new ReflectionClass($driver);
        $prop = $ref->getProperty('ssh');
        $prop->setAccessible(true);
        $prop->setValue($driver, $ssh);

        return $driver;
    }

    public function test_valid_zero_exit_status_is_success(): void
    {
        $ssh = \Mockery::mock(SSH2::class);
        $ssh->shouldReceive('exec')->once()->andReturn('ok output');
        $ssh->shouldReceive('isTimeout')->once()->andReturn(false);
        $ssh->shouldReceive('getExitStatus')->once()->andReturn(0);

        $result = $this->makeDriverWithMockedSsh($ssh)->execute('true');

        $this->assertTrue($result->succeeded());
        $this->assertSame(0, $result->exitCode);
        $this->assertSame('ok output', $result->stdout);
    }

    public function test_valid_nonzero_exit_status_is_failure(): void
    {
        $ssh = \Mockery::mock(SSH2::class);
        $ssh->shouldReceive('exec')->once()->andReturn('boom');
        $ssh->shouldReceive('isTimeout')->once()->andReturn(false);
        $ssh->shouldReceive('getExitStatus')->once()->andReturn(1);

        $result = $this->makeDriverWithMockedSsh($ssh)->execute('false');

        $this->assertTrue($result->failed());
        $this->assertSame(1, $result->exitCode);
    }

    public function test_invalid_exit_status_is_treated_as_failure_not_success(): void
    {
        $ssh = \Mockery::mock(SSH2::class);
        $ssh->shouldReceive('exec')->once()->andReturn('partial output');
        $ssh->shouldReceive('isTimeout')->once()->andReturn(false);
        $ssh->shouldReceive('getExitStatus')->once()->andReturn(false);

        $result = $this->makeDriverWithMockedSsh($ssh)->execute('some-command');

        $this->assertTrue($result->failed());
        $this->assertNotSame(0, $result->exitCode);
        $this->assertStringContainsString('valid exit status', $result->stderr);
    }

    public function test_timeout_is_treated_as_failure_not_success(): void
    {
        $ssh = \Mockery::mock(SSH2::class);
        $ssh->shouldReceive('exec')->once()->andReturn('truncated');
        $ssh->shouldReceive('isTimeout')->once()->andReturn(true);
        // getExitStatus() must not even be consulted once a timeout is detected.
        $ssh->shouldNotReceive('getExitStatus');

        $result = $this->makeDriverWithMockedSsh($ssh)->execute('sleep 9999');

        $this->assertTrue($result->failed());
        $this->assertNotSame(0, $result->exitCode);
        $this->assertStringContainsString('timed out', $result->stderr);
        $this->assertSame('truncated', $result->stdout);
    }

    public function test_previous_successful_command_behavior_is_unchanged(): void
    {
        $ssh = \Mockery::mock(SSH2::class);
        $ssh->shouldReceive('exec')->once()->andReturn("line1\nline2\n");
        $ssh->shouldReceive('isTimeout')->once()->andReturn(false);
        $ssh->shouldReceive('getExitStatus')->once()->andReturn(0);

        $result = $this->makeDriverWithMockedSsh($ssh)->execute('cat file.txt');

        $this->assertTrue($result->succeeded());
        $this->assertFalse($result->failed());
        $this->assertSame(0, $result->exitCode);
        $this->assertSame("line1\nline2\n", $result->stdout);
        $this->assertSame('', $result->stderr);
    }
}
