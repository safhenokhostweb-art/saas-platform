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
 * Phase 15-G — Part 1/7: closes the "no retry logic anywhere" finding
 * CpanelUapiClientTest locked in during Phase 15-F. Covers exactly the
 * scenarios that phase's brief called for:
 *   - a transient transport timeout followed by success -> retried, succeeds
 *   - a transient HTTP 5xx followed by success -> retried, succeeds
 *   - an invalid/expired token (HTTP 401) -> never retried
 *   - a UAPI-level permission-denied business error (status=0) -> never retried
 *   - persistent 5xx across every attempt -> exhausts retries and throws
 *
 * All tests set uapi_retry_delay to 0 so the retry loop's usleep() does not
 * slow down the suite — the delay VALUE is not what these tests are about.
 */
class CpanelUapiRetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;

        config([
            'customer_installation.cpanel.uapi_retry_delay' => 0,
        ]);
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

    public function test_a_timeout_then_success_is_retried_and_returns_the_successful_result(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            if ($calls === 1) {
                throw new ConnectionException('cURL error 28: Operation timed out');
            }
            return Http::response(['status' => 1, 'data' => ['ok' => true]], 200);
        });

        $data = $this->client()->call($this->server(), 'DomainInfo', 'domains_data');

        $this->assertSame(['ok' => true], $data);
        $this->assertSame(2, $calls);
    }

    public function test_a_transient_500_then_success_is_retried_and_returns_the_successful_result(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push('Internal Server Error', 500)
            ->push(['status' => 1, 'data' => ['ok' => true]], 200)]);

        $data = $this->client()->call($this->server(), 'DomainInfo', 'domains_data');

        $this->assertSame(['ok' => true], $data);
        Http::assertSentCount(2);
    }

    public function test_an_invalid_token_response_is_never_retried(): void
    {
        Http::fake(['*' => Http::response('Login attempt failed.', 401)]);

        try {
            $this->client()->call($this->server(), 'DomainInfo', 'domains_data');
            $this->fail('Expected a RuntimeException for an invalid-token response.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 401', $e->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_a_uapi_permission_denied_business_error_is_never_retried(): void
    {
        // status=0 on a normal 200 response — a UAPI-reported failure, not a
        // transport failure. Must never be retried even on the first attempt.
        Http::fake(['*' => Http::response(['status' => 0, 'errors' => ['Permission denied']], 200)]);

        try {
            $this->client()->call($this->server(), 'DomainInfo', 'domains_data');
            $this->fail('Expected a RuntimeException for a UAPI permission-denied response.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Permission denied', $e->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_persistent_5xx_exhausts_the_configured_attempts_and_throws(): void
    {
        Http::fake(['*' => Http::response('Service Unavailable', 503)]);

        try {
            $this->client()->call($this->server(), 'DomainInfo', 'domains_data');
            $this->fail('Expected a RuntimeException once retries are exhausted.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 503', $e->getMessage());
        }

        Http::assertSentCount((int) config('customer_installation.cpanel.uapi_retry_attempts', 2));
    }

    public function test_a_persistent_connection_timeout_exhausts_attempts_and_throws_the_connection_exception(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $this->expectException(ConnectionException::class);

        $this->client()->call($this->server(), 'DomainInfo', 'domains_data');
    }

    /**
     * Phase 15-G — Part 6: the exception message for an HTTP-level failure
     * must never embed the full raw response body verbatim.
     */
    public function test_exception_message_truncates_a_large_raw_response_body(): void
    {
        config(['customer_installation.cpanel.uapi_retry_attempts' => 1]);
        $hugeBody = str_repeat('X', 5000);
        Http::fake(['*' => Http::response($hugeBody, 500)]);

        try {
            $this->client()->call($this->server(), 'DomainInfo', 'domains_data');
            $this->fail('Expected a RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertLessThan(500, strlen($e->getMessage()));
            $this->assertStringContainsString('HTTP 500', $e->getMessage());
        }
    }
}
