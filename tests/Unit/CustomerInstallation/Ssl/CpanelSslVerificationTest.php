<?php

namespace Tests\Unit\CustomerInstallation\Ssl;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use App\Services\CustomerInstallation\Ssl\CpanelSslApiAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 15-D — CpanelSslApiAdapter is the single seam between
 * CpanelSslProvisioner and the raw UAPI SSL module calls (see its docblock:
 * if the guessed function names are wrong, only this class needs to
 * change). These tests verify the adapter's own request shape and its
 * certificateExists()/certificateStatus() read-only helpers.
 */
class CpanelSslVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function server(): CustomerRemoteServer
    {
        $vault = new CredentialVault();

        return CustomerRemoteServer::create([
            'label' => 'cPanel', 'type' => 'cpanel', 'host' => 'box106.parsvds.com',
            'port' => 22, 'username' => 'johndoe', 'auth_method' => 'password',
            'password_encrypted' => $vault->encrypt('the-account-password'),
        ]);
    }

    private function adapter(): CpanelSslApiAdapter
    {
        return new CpanelSslApiAdapter(new CpanelUapiClient(new CredentialVault()));
    }

    public function test_trigger_autossl_check_calls_the_expected_uapi_endpoint(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);

        $this->adapter()->triggerAutoSslCheck($this->server(), 'cp.example.com');

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/execute/SSL/start_autossl_check')
            && $request['domain'] === 'cp.example.com');
    }

    public function test_certificate_exists_returns_true_when_domain_is_listed(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => [
            ['domains' => ['cp.example.com'], 'not_after' => '2026-09-07'],
        ]], 200)]);

        $exists = $this->adapter()->certificateExists($this->server(), 'cp.example.com');

        $this->assertTrue($exists);
    }

    public function test_certificate_exists_returns_false_when_domain_is_not_listed(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);

        $exists = $this->adapter()->certificateExists($this->server(), 'cp.example.com');

        $this->assertFalse($exists);
    }

    public function test_certificate_exists_returns_null_when_uapi_is_unreachable(): void
    {
        Http::fake(['*' => Http::response('Gateway Timeout', 504)]);

        $exists = $this->adapter()->certificateExists($this->server(), 'cp.example.com');

        $this->assertNull($exists);
    }

    public function test_certificate_status_returns_expiry_metadata_when_found(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => [
            ['domains' => ['cp.example.com'], 'not_after' => '2026-09-07'],
        ]], 200)]);

        $status = $this->adapter()->certificateStatus($this->server(), 'cp.example.com');

        $this->assertSame('2026-09-07', $status['not_after']);
    }

    public function test_certificate_status_returns_empty_array_when_no_certificate_found(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);

        $status = $this->adapter()->certificateStatus($this->server(), 'cp.example.com');

        $this->assertSame([], $status);
    }

    public function test_certificate_status_returns_null_on_uapi_failure(): void
    {
        Http::fake(['*' => Http::response(['status' => 0, 'errors' => ['boom']], 200)]);

        $status = $this->adapter()->certificateStatus($this->server(), 'cp.example.com');

        $this->assertNull($status);
    }
}
