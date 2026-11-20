<?php

namespace Tests\Feature\Admin\Infrastructure;

use App\Models\CustomerRemoteServer;
use App\Models\User;
use App\Services\CustomerInstallation\CredentialVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Editing a deployment target must not demand the stored secret again.
 *
 * Regression: toggling is_platform_managed on an auth_method=key server failed
 * with «فیلد private key هنگامی که auth method برابر key است الزامی است»
 * because update() used a bare required_if:auth_method,key that ignored the
 * already-encrypted private_key_encrypted column.
 */
class CustomerRemoteServerUpdateCredentialsTest extends TestCase
{
    use RefreshDatabase;

    private const STORED_KEY = "-----BEGIN OPENSSH PRIVATE KEY-----\nstored-key-material\n-----END OPENSSH PRIVATE KEY-----";

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function admin(): User
    {
        return User::create([
            'mobile'    => '09131234568',
            'password'  => Hash::make('password'),
            'is_admin'  => true,
            'is_active' => true,
        ]);
    }

    private function vault(): CredentialVault
    {
        return app(CredentialVault::class);
    }

    private function keyServer(): CustomerRemoteServer
    {
        return CustomerRemoteServer::create([
            'label'                 => 'Platform VPS',
            'type'                  => 'ssh',
            'host'                  => 'vps.example.test',
            'port'                  => 22,
            'username'              => 'deploy',
            'auth_method'           => 'key',
            'private_key_encrypted' => $this->vault()->encrypt(self::STORED_KEY),
            'is_platform_managed'   => false,
        ]);
    }

    /** The form payload a browser sends when only the checkbox changes. */
    private function editPayload(CustomerRemoteServer $server, array $overrides = []): array
    {
        return array_merge([
            'label'               => $server->label,
            'host'                => $server->host,
            'port'                => $server->port,
            'username'            => $server->username,
            'auth_method'         => 'key',
            'is_platform_managed' => '1',
        ], $overrides);
    }

    public function test_toggling_platform_managed_on_key_server_does_not_require_private_key(): void
    {
        $server = $this->keyServer();
        $cipherBefore = $server->getRawOriginal('private_key_encrypted');

        $this->actingAs($this->admin())
            ->put(route('admin.infrastructure.customer-deployment.servers.update', $server), $this->editPayload($server))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.infrastructure.customer-deployment.servers.index'));

        $server->refresh();
        $this->assertTrue($server->is_platform_managed);
        $this->assertSame('key', $server->auth_method);
        $this->assertSame($cipherBefore, $server->getRawOriginal('private_key_encrypted'), 'stored ciphertext must be untouched');
        $this->assertSame(self::STORED_KEY, $this->vault()->decrypt($server->private_key_encrypted));
    }

    public function test_empty_private_key_field_keeps_stored_key(): void
    {
        $server = $this->keyServer();
        $cipherBefore = $server->getRawOriginal('private_key_encrypted');

        $this->actingAs($this->admin())
            ->put(route('admin.infrastructure.customer-deployment.servers.update', $server), $this->editPayload($server, ['private_key' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame($cipherBefore, $server->fresh()->getRawOriginal('private_key_encrypted'));
    }

    public function test_new_private_key_replaces_stored_key(): void
    {
        $server = $this->keyServer();
        $newKey = "-----BEGIN OPENSSH PRIVATE KEY-----\nrotated\n-----END OPENSSH PRIVATE KEY-----";

        $this->actingAs($this->admin())
            ->put(route('admin.infrastructure.customer-deployment.servers.update', $server), $this->editPayload($server, ['private_key' => $newKey]))
            ->assertSessionHasNoErrors();

        $this->assertSame($newKey, $this->vault()->decrypt($server->fresh()->private_key_encrypted));
    }

    public function test_switching_to_key_auth_without_stored_key_still_requires_private_key(): void
    {
        $server = CustomerRemoteServer::create([
            'label'              => 'Password VPS',
            'type'               => 'ssh',
            'host'               => 'pw.example.test',
            'port'               => 22,
            'username'           => 'deploy',
            'auth_method'        => 'password',
            'password_encrypted' => $this->vault()->encrypt('s3cret'),
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.infrastructure.customer-deployment.servers.update', $server), $this->editPayload($server))
            ->assertSessionHasErrors('private_key');

        $server->refresh();
        $this->assertSame('password', $server->auth_method);
        $this->assertFalse($server->is_platform_managed);
    }

    public function test_switching_to_key_auth_with_new_key_persists_auth_method(): void
    {
        $server = CustomerRemoteServer::create([
            'label'              => 'Password VPS',
            'type'               => 'ssh',
            'host'               => 'pw.example.test',
            'port'               => 22,
            'username'           => 'deploy',
            'auth_method'        => 'password',
            'password_encrypted' => $this->vault()->encrypt('s3cret'),
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.infrastructure.customer-deployment.servers.update', $server), $this->editPayload($server, ['private_key' => self::STORED_KEY]))
            ->assertSessionHasNoErrors();

        $server->refresh();
        $this->assertSame('key', $server->auth_method);
        $this->assertSame(self::STORED_KEY, $this->vault()->decrypt($server->private_key_encrypted));
    }

    public function test_password_server_edit_does_not_require_password_again(): void
    {
        $server = CustomerRemoteServer::create([
            'label'              => 'Password VPS',
            'type'               => 'ssh',
            'host'               => 'pw.example.test',
            'port'               => 22,
            'username'           => 'deploy',
            'auth_method'        => 'password',
            'password_encrypted' => $this->vault()->encrypt('s3cret'),
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.infrastructure.customer-deployment.servers.update', $server), $this->editPayload($server, ['auth_method' => 'password']))
            ->assertSessionHasNoErrors();

        $server->refresh();
        $this->assertTrue($server->is_platform_managed);
        $this->assertSame('s3cret', $this->vault()->decrypt($server->password_encrypted));
    }

    public function test_create_still_requires_private_key_for_key_auth(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.infrastructure.customer-deployment.servers.store'), [
                'label'       => 'New VPS',
                'type'        => 'ssh',
                'host'        => 'new.example.test',
                'port'        => 22,
                'username'    => 'deploy',
                'auth_method' => 'key',
            ])
            ->assertSessionHasErrors('private_key');

        $this->assertDatabaseMissing('customer_remote_servers', ['host' => 'new.example.test']);
    }

    public function test_edit_page_renders_with_guarded_private_key_inputs(): void
    {
        $server = $this->keyServer();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.infrastructure.customer-deployment.servers.edit', $server))
            ->assertOk()
            ->getContent();

        // Every private_key source is disabled/unnamed while empty, so an empty
        // later control can no longer override a pasted or migrated key.
        $this->assertStringContainsString('x-bind:disabled="!migratedPrivateKey"', $html);
        $this->assertStringContainsString(':value="priv" :disabled="!priv"', $html);
        $this->assertStringContainsString('x-bind:name="pasted.trim() !== \'\' ? \'private_key\' : null"', $html);
        $this->assertStringNotContainsString('stored-key-material', $html, 'stored key must never be rendered');
    }
}
