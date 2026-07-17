<?php

namespace Tests\Unit\CustomerInstallation\Provisioning;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 15-F — Part 3/7: CpanelUapiClient itself had no dedicated test file
 * before this phase (only exercised indirectly through each Provisioner's
 * own tests). These tests close two explicitly requested scenarios that
 * were not otherwise covered: a true connection-level timeout
 * (Illuminate\Http\Client\ConnectionException, distinct from a slow-but-
 * received HTTP error response) and an invalid/expired credential (401).
 *
 * Phase 15-G — Part 1: the "no retry logic anywhere" finding this file
 * originally locked in has been fixed. See CpanelUapiRetryTest for full
 * coverage of the retry policy (success-after-retry, non-retryable errors,
 * exhaustion) — test_no_retry_is_ever_attempted_on_a_transient_failure below
 * has been updated in place rather than deleted, per its own comment.
 */
class CpanelUapiClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;

        config(['customer_installation.cpanel.uapi_retry_delay' => 0]);
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

    private function client(): CpanelUapiClient
    {
        return new CpanelUapiClient(new CredentialVault());
    }

    /**
     * A true connection-level timeout (no HTTP response ever received) —
     * distinct from every other test in this codebase's cPanel suite, which
     * simulates a received-but-erroring response (e.g. HTTP 504/500). This
     * is the shape Laravel's HTTP client actually throws when
     * ->timeout(30) is exceeded without a response.
     */
    public function test_a_true_connection_timeout_throws_connection_exception_not_runtime_exception(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $this->expectException(ConnectionException::class);

        $this->client()->call($this->server(), 'DomainInfo', 'domains_data');
    }

    public function test_invalid_or_expired_token_response_throws_a_runtime_exception_with_no_credential_in_the_message(): void
    {
        Http::fake(['*' => Http::response('Login attempt failed.', 401)]);

        try {
            $this->client()->call($this->server(), 'DomainInfo', 'domains_data');
            $this->fail('Expected a RuntimeException for an invalid-token response.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 401', $e->getMessage());
            $this->assertStringNotContainsString('the-account-password', $e->getMessage());
        }
    }

    public function test_no_retry_is_ever_attempted_on_a_transient_failure(): void
    {
        // Phase 15-G — Part 1: inverted from the Phase 15-F finding this
        // test originally locked in ("a single failure is final"). A
        // transient 5xx is now retried up to the configured attempt count
        // before giving up — see CpanelUapiRetryTest for the full policy.
        Http::fake(['*' => Http::response('Service Unavailable', 503)]);

        try {
            $this->client()->call($this->server(), 'DomainInfo', 'domains_data');
        } catch (RuntimeException) {
        }

        Http::assertSentCount((int) config('customer_installation.cpanel.uapi_retry_attempts', 2));
    }
}
