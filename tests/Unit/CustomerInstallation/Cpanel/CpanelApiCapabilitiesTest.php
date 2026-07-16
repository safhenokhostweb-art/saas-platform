<?php

namespace Tests\Unit\CustomerInstallation\Cpanel;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Cpanel\CpanelApiCapabilities;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use App\Services\CustomerInstallation\Ssl\CpanelSslApiAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 15-E — Part 2: CpanelApiCapabilities turns the static
 * "cPanel driver type supports X" assumption into a live, per-account
 * probe. See the class docblock for the confidence level of each method —
 * these tests verify the PROBING BEHAVIOR (success/failure/caching), not
 * that cPanel's real API matches the guessed function names (that risk is
 * unchanged from Phase 15-D/15-E's other audits).
 */
class CpanelApiCapabilitiesTest extends TestCase
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

    private function capabilities(): CpanelApiCapabilities
    {
        $vault = new CredentialVault();

        return new CpanelApiCapabilities(
            new CpanelUapiClient($vault),
            new CpanelSslApiAdapter(new CpanelUapiClient($vault)),
        );
    }

    public function test_supports_domain_info_api_is_true_when_the_query_succeeds(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);

        $this->assertTrue($this->capabilities()->supportsDomainInfoApi($this->server()));
    }

    public function test_supports_domain_info_api_is_false_when_the_query_fails(): void
    {
        Http::fake(['*' => Http::response(['status' => 0, 'errors' => ['Unknown UAPI function']], 200)]);

        $this->assertFalse($this->capabilities()->supportsDomainInfoApi($this->server()));
    }

    public function test_supports_domain_info_api_is_false_on_transport_failure(): void
    {
        Http::fake(['*' => Http::response('Gateway Timeout', 504)]);

        $this->assertFalse($this->capabilities()->supportsDomainInfoApi($this->server()));
    }

    public function test_supports_domain_info_api_result_is_cached_per_server(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);
        $capabilities = $this->capabilities();
        $server = $this->server();

        $capabilities->supportsDomainInfoApi($server);
        $capabilities->supportsDomainInfoApi($server);

        Http::assertSentCount(1);
    }

    public function test_supports_auto_ssl_api_delegates_to_the_ssl_adapter(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);

        $this->assertTrue($this->capabilities()->supportsAutoSslApi($this->server()));
    }

    public function test_supports_auto_ssl_api_is_false_when_ssl_module_is_unreachable(): void
    {
        Http::fake(['*' => Http::response(['status' => 0, 'errors' => ['boom']], 200)]);

        $this->assertFalse($this->capabilities()->supportsAutoSslApi($this->server()));
    }

    /**
     * See CpanelApiCapabilities' docblock: no independent probe exists for
     * AddonDomain specifically, so this honestly mirrors supportsDomainInfoApi().
     */
    public function test_supports_addon_domain_api_mirrors_domain_info_availability(): void
    {
        Http::fake(['*' => Http::response(['status' => 0, 'errors' => ['boom']], 200)]);

        $this->assertFalse($this->capabilities()->supportsAddonDomainApi($this->server()));
    }
}
