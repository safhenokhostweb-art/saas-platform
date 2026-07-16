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
 * Phase 15-E — Part 5: dedicated coverage for the two new
 * CpanelSslApiAdapter methods (verifyAutoSslAvailable/verifyCertificateIssued)
 * — CpanelSslVerificationTest (Phase 15-D) already covers
 * certificateExists()/certificateStatus()/triggerAutoSslCheck().
 */
class CpanelSslAdapterTest extends TestCase
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

    public function test_verify_autossl_available_is_true_when_ssl_module_responds(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);

        $this->assertTrue($this->adapter()->verifyAutoSslAvailable($this->server()));
    }

    public function test_verify_autossl_available_is_false_when_ssl_module_errors(): void
    {
        Http::fake(['*' => Http::response(['status' => 0, 'errors' => ['Unknown UAPI function']], 200)]);

        $this->assertFalse($this->adapter()->verifyAutoSslAvailable($this->server()));
    }

    public function test_verify_autossl_available_is_false_on_transport_failure(): void
    {
        Http::fake(['*' => Http::response('Gateway Timeout', 504)]);

        $this->assertFalse($this->adapter()->verifyAutoSslAvailable($this->server()));
    }

    public function test_verify_autossl_available_never_triggers_the_mutating_check_call(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);

        $this->adapter()->verifyAutoSslAvailable($this->server());

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'start_autossl_check'));
    }

    public function test_verify_certificate_issued_is_true_for_a_listed_certificate_with_future_expiry(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => [
            ['domains' => ['cp.example.com'], 'not_after' => date('Y-m-d', strtotime('+60 days'))],
        ]], 200)]);

        $this->assertTrue($this->adapter()->verifyCertificateIssued($this->server(), 'cp.example.com'));
    }

    public function test_verify_certificate_issued_is_false_for_an_expired_certificate(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => [
            ['domains' => ['cp.example.com'], 'not_after' => date('Y-m-d', strtotime('-10 days'))],
        ]], 200)]);

        $this->assertFalse($this->adapter()->verifyCertificateIssued($this->server(), 'cp.example.com'));
    }

    public function test_verify_certificate_issued_is_false_when_no_certificate_is_listed(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);

        $this->assertFalse($this->adapter()->verifyCertificateIssued($this->server(), 'cp.example.com'));
    }

    public function test_verify_certificate_issued_is_false_on_uapi_failure(): void
    {
        Http::fake(['*' => Http::response(['status' => 0, 'errors' => ['boom']], 200)]);

        $this->assertFalse($this->adapter()->verifyCertificateIssued($this->server(), 'cp.example.com'));
    }

    public function test_verify_certificate_issued_treats_a_missing_expiry_field_as_issued(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => [
            ['domains' => ['cp.example.com']],
        ]], 200)]);

        $this->assertTrue($this->adapter()->verifyCertificateIssued($this->server(), 'cp.example.com'));
    }
}
