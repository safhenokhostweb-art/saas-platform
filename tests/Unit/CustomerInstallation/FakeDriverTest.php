<?php

namespace Tests\Unit\CustomerInstallation;

use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use Tests\TestCase;

class FakeDriverTest extends TestCase
{
    public function test_connect_marks_connected(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->connect();
        $this->assertTrue($driver->wasConnected());
    }

    public function test_execute_records_commands(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->connect();
        $driver->execute('ls /var/www');
        $driver->execute('php -v');

        $this->assertCount(2, $driver->getCommandLog());
        $this->assertContains('ls /var/www', $driver->getCommandLog());
    }

    public function test_execute_returns_canned_response(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('php -v', 0, 'PHP 8.2.0 (cli)');

        $result = $driver->execute('php -v');

        $this->assertTrue($result->succeeded());
        $this->assertEquals('PHP 8.2.0 (cli)', $result->stdout);
    }

    public function test_execute_defaults_to_success(): void
    {
        $driver = new FakeRemoteDriver();
        $result = $driver->execute('any command');

        $this->assertTrue($result->succeeded());
        $this->assertEquals(0, $result->exitCode);
    }

    public function test_upload_records_transfer(): void
    {
        $driver = new FakeRemoteDriver();
        $local = tempnam(sys_get_temp_dir(), 'cie_test');
        file_put_contents($local, 'test');

        $driver->upload($local, '/remote/path.zip');

        $this->assertCount(1, $driver->getUploadLog());
        $this->assertEquals('/remote/path.zip', $driver->getUploadLog()[0]['remote']);
        $this->assertTrue($driver->fileExists('/remote/path.zip'));

        unlink($local);
    }

    public function test_upload_fires_progress_callback(): void
    {
        $driver = new FakeRemoteDriver();
        $pcts = [];

        $local = tempnam(sys_get_temp_dir(), 'cie_test');
        file_put_contents($local, 'content');

        $driver->upload($local, '/remote/file.zip', function (int $pct) use (&$pcts) {
            $pcts[] = $pct;
        });

        $this->assertNotEmpty($pcts);
        $this->assertEquals(100, end($pcts));

        unlink($local);
    }

    public function test_disk_free_returns_custom_value(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->setDiskFree('/var/www', 5 * 1024 * 1024 * 1024);

        $this->assertEquals(5 * 1024 * 1024 * 1024, $driver->diskFree('/var/www'));
    }

    public function test_disconnect_marks_disconnected(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->connect();
        $driver->disconnect();
        $this->assertFalse($driver->wasConnected());
    }

    public function test_test_connection_returns_success(): void
    {
        $driver = new FakeRemoteDriver();
        $result = $driver->testConnection();

        $this->assertTrue($result->connected);
        $this->assertNotEmpty($result->phpVersion);
        $this->assertNull($result->error);
    }

    public function test_will_fail_triggers_exception(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->willFail('Network timeout');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Network timeout');
        $driver->execute('anything');
    }
}
