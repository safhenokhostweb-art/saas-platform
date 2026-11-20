<?php

namespace App\Http\Controllers\Admin\Infrastructure;

use App\Http\Controllers\Controller;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\RemoteConnectionService;
use App\Services\CustomerInstallation\Enums\DriverType;
use App\Services\CustomerInstallation\Dto\DriverCapabilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use phpseclib3\Crypt\EC;

class CustomerRemoteServerAdminController extends Controller
{
    public function __construct(
        private readonly CredentialVault $vault,
        private readonly RemoteConnectionService $connectionService,
    ) {}

    public function index()
    {
        $servers = CustomerRemoteServer::withCount('installations')
            ->orderBy('label')
            ->paginate(20);

        return view('admin.infrastructure.customer-deployment.servers.index', [
            'servers'    => $servers,
            'driverMeta' => self::driverMetaMap(),
        ]);
    }

    public function create()
    {
        return view('admin.infrastructure.customer-deployment.servers.create', [
            'driverMeta' => self::driverMetaMap(),
        ]);
    }

    public function store(Request $request)
    {
        $type = $request->input('type', 'ssh');

        $validated = $request->validate(array_merge(
            [
                'label'  => 'required|string|max:100',
                'type'   => [
                    'required',
                    'in:ssh,docker,cpanel,directadmin,sftp',
                    function ($attribute, $value, $fail) {
                        $driverType = DriverType::tryFrom($value);
                        if ($driverType && !$driverType->isImplemented()) {
                            $fail("درایور «{$driverType->label()}» هنوز پیاده‌سازی نشده است. امکان ساخت مقصد استقرار از این نوع وجود ندارد.");
                        }
                    },
                ],
                'host'   => 'required|string|max:255',
                'domain' => 'nullable|string|max:255',
                'path'   => 'nullable|string|max:500',
                'is_platform_managed' => 'nullable|boolean',
            ],
            $this->credentialRules($type),
            $this->connectionRules($type),
        ));

        $data = [
            'label'       => $validated['label'],
            'type'        => $validated['type'],
            'host'        => $validated['host'],
            'port'        => (int) ($validated['port'] ?? $this->defaultPort($type)),
            'username'    => $validated['username'] ?? '',
            'auth_method' => $validated['auth_method'] ?? $this->defaultAuth($type),
            'domain'      => $validated['domain'] ?? null,
            'path'        => $validated['path'] ?? null,
            // Phase 4: only platform-owned servers receive the tenant media key.
            'is_platform_managed' => $request->boolean('is_platform_managed'),
        ];

        // Store credentials through CredentialVault (AES-256-GCM)
        if (!empty($validated['private_key'])) {
            $data['private_key_encrypted'] = $this->vault->encrypt($validated['private_key']);
        }
        if (!empty($validated['password'])) {
            $data['password_encrypted'] = $this->vault->encrypt($validated['password']);
        }
        if (!empty($validated['api_token'])) {
            $data['api_token_encrypted'] = $this->vault->encrypt($validated['api_token']);
        }

        CustomerRemoteServer::create($data);

        return redirect()
            ->route('admin.infrastructure.customer-deployment.servers.index')
            ->with('success', 'مقصد استقرار با موفقیت اضافه شد.');
    }

    public function edit(CustomerRemoteServer $server)
    {
        return view('admin.infrastructure.customer-deployment.servers.edit', [
            'server'     => $server,
            'driverMeta' => self::driverMetaMap(),
        ]);
    }

    public function update(Request $request, CustomerRemoteServer $server)
    {
        $type = $server->type;

        $validated = $request->validate(array_merge(
            [
                'label'  => 'required|string|max:100',
                'host'   => 'required|string|max:255',
                'domain' => 'nullable|string|max:255',
                'path'   => 'nullable|string|max:500',
                'is_platform_managed' => 'nullable|boolean',
            ],
            $this->credentialRules($type, optional: true, existing: $server),
            $this->connectionRules($type),
        ));

        $data = [
            'label'    => $validated['label'],
            'host'     => $validated['host'],
            'port'     => (int) ($validated['port'] ?? $server->port),
            'username' => $validated['username'] ?? $server->username,
            'auth_method' => $validated['auth_method'] ?? $server->auth_method,
            'domain'   => $validated['domain'] ?? null,
            'path'     => $validated['path'] ?? null,
            'is_platform_managed' => $request->boolean('is_platform_managed'),
        ];

        // Only overwrite credentials when new values provided
        if (!empty($validated['private_key'])) {
            $data['private_key_encrypted'] = $this->vault->encrypt($validated['private_key']);
        }
        if (!empty($validated['password'])) {
            $data['password_encrypted'] = $this->vault->encrypt($validated['password']);
        }
        if (!empty($validated['api_token'])) {
            $data['api_token_encrypted'] = $this->vault->encrypt($validated['api_token']);
        }

        $server->update($data);

        return redirect()
            ->route('admin.infrastructure.customer-deployment.servers.index')
            ->with('success', 'مقصد استقرار با موفقیت ویرایش شد.');
    }

    public function destroy(CustomerRemoteServer $server)
    {
        if ($server->installations()->whereIn('status', ['pending', 'running'])->exists()) {
            return back()->with('error', 'این مقصد در حال نصب فعال است. ابتدا نصب را متوقف کنید.');
        }

        $server->delete();

        return redirect()
            ->route('admin.infrastructure.customer-deployment.servers.index')
            ->with('success', 'مقصد استقرار حذف شد.');
    }

    public function generateSshKeyPair(Request $request): JsonResponse
    {
        try {
            $comment       = 'saas-platform-' . date('Y-m-d');
            $privateKeyObj = EC::createKey('Ed25519');

            $privateKey = $privateKeyObj->toString('OpenSSH', ['comment' => $comment]);
            $publicKey  = $privateKeyObj->getPublicKey()->toString('OpenSSH', ['comment' => $comment]);

            return response()->json([
                'success'     => true,
                'private_key' => $privateKey,
                'public_key'  => $publicKey,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'خطا در تولید کلید: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function testConnection(Request $request, CustomerRemoteServer $server)
    {
        try {
            $result = $this->connectionService->testAndRecord($server);

            if ($result->connected) {
                return response()->json([
                    'success'     => true,
                    'message'     => 'اتصال موفق',
                    'php_version' => $result->phpVersion,
                    'disk_free'   => $result->diskFreeBytes ? round($result->diskFreeBytes / 1024 / 1024 / 1024, 2) . ' GB' : null,
                    'fingerprint' => $result->fingerprint,
                    'os'          => $result->os ?? null,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result->error ?? 'خطا در اتصال',
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * A secret is required only when the chosen auth method needs it AND no
     * encrypted value is already stored. On create ($existing = null) that is
     * always the case; on edit an empty field means "keep the stored secret",
     * so the admin can change other fields (e.g. is_platform_managed) without
     * re-pasting the private key, and switching auth_method to a method that
     * has no stored secret still demands one.
     */
    private function credentialRules(string $type, bool $optional = false, ?CustomerRemoteServer $existing = null): array
    {
        $req = $optional ? 'nullable' : 'required';

        $requiredFor = fn (string $method, string $column) => Rule::requiredIf(
            fn () => request()->input('auth_method') === $method
                && empty($existing?->getRawOriginal($column))
        );

        return match($type) {
            'ssh', 'docker' => [
                'auth_method' => 'required|in:key,password',
                'private_key' => [
                    $requiredFor('key', 'private_key_encrypted'),
                    "nullable",
                    "string",
                    function ($attr, $value, $fail) {
                        if (empty($value)) return;
                        $trimmed = ltrim($value);
                        if (str_starts_with($trimmed, 'ssh-') || str_starts_with($trimmed, 'ecdsa-') || str_starts_with($trimmed, 'sk-')) {
                            $fail('مقدار وارد شده یک کلید عمومی است. لطفاً کلید خصوصی را وارد کنید (باید با -----BEGIN OPENSSH PRIVATE KEY----- شروع شود).');
                        }
                    },
                ],
                'password'    => [$requiredFor('password', 'password_encrypted'), 'nullable', 'string', 'max:500'],
            ],
            'cpanel', 'directadmin' => [
                'auth_method' => 'required|in:api_token,password',
                'api_token'   => [$requiredFor('api_token', 'api_token_encrypted'), 'nullable', 'string', 'max:500'],
                'password'    => [$requiredFor('password', 'password_encrypted'), 'nullable', 'string', 'max:500'],
            ],
            'sftp' => [
                'auth_method' => 'nullable|string',
                'password'    => "{$req}|nullable|string|max:500",
            ],
            default => [],
        };
    }

    private function connectionRules(string $type): array
    {
        return match($type) {
            'ssh', 'docker', 'sftp' => [
                'port'     => 'required|integer|min:1|max:65535',
                'username' => 'required|string|max:100',
            ],
            'cpanel', 'directadmin' => [
                'port'     => 'nullable|integer|min:1|max:65535',
                'username' => 'required|string|max:100',
            ],
            default => [],
        };
    }

    private function defaultPort(string $type): int
    {
        return match($type) {
            'cpanel'      => 2083,
            'directadmin' => 2222,
            default       => 22,
        };
    }

    private function defaultAuth(string $type): string
    {
        return match($type) {
            'cpanel', 'directadmin' => 'api_token',
            'sftp'                  => 'sftp_password',
            default                 => 'key',
        };
    }

    /**
     * Static driver UI metadata — used by views to render type cards,
     * capability badges, and dynamic form sections.
     * Changing driver metadata here is the ONLY change needed for new driver support.
     */
    public static function driverMetaMap(): array
    {
        return [
            'ssh' => [
                'label'       => 'VPS / سرور اختصاصی',
                'label_short' => 'SSH / VPS',
                'description' => 'سرور مجازی یا اختصاصی با دسترسی SSH کامل',
                'color'       => 'indigo',
                'icon'        => 'server',
                'implemented' => true,
                'auth_types'  => ['key' => 'کلید SSH', 'password' => 'رمز عبور SSH'],
                'default_port'=> 22,
                'fields'      => ['host', 'port', 'username', 'ssh_auth'],
                'capabilities'=> [
                    'queue' => true, 'supervisor' => true, 'redis' => true,
                    'ssl' => true, 'database' => true, 'storage' => true,
                    'cron' => true, 'docker' => true, 'reboot' => true,
                ],
            ],
            'directadmin' => [
                'label'       => 'DirectAdmin',
                'label_short' => 'DirectAdmin',
                'description' => 'هاستینگ اشتراکی با پنل DirectAdmin',
                'color'       => 'blue',
                'icon'        => 'panel',
                'implemented' => false,
                'auth_types'  => ['api_token' => 'API Key'],
                'default_port'=> 2222,
                'fields'      => ['host', 'port', 'username', 'api_token', 'domain', 'path'],
                'capabilities'=> [
                    'queue' => false, 'supervisor' => false, 'redis' => false,
                    'ssl' => true, 'database' => true, 'storage' => false,
                    'cron' => true, 'docker' => false, 'reboot' => false,
                ],
            ],
            'cpanel' => [
                'label'       => 'cPanel',
                'label_short' => 'cPanel',
                'description' => 'هاستینگ اشتراکی با پنل cPanel',
                'color'       => 'orange',
                'icon'        => 'panel',
                'implemented' => false,
                'auth_types'  => ['api_token' => 'API Token'],
                'default_port'=> 2083,
                'fields'      => ['host', 'port', 'username', 'api_token', 'domain', 'path'],
                'capabilities'=> [
                    'queue' => false, 'supervisor' => false, 'redis' => false,
                    'ssl' => true, 'database' => true, 'storage' => false,
                    'cron' => true, 'docker' => false, 'reboot' => false,
                ],
            ],
            'sftp' => [
                'label'       => 'SFTP خالص',
                'label_short' => 'SFTP',
                'description' => 'آپلود فایل از طریق SFTP بدون دسترسی شل',
                'color'       => 'teal',
                'icon'        => 'upload',
                'implemented' => false,
                'auth_types'  => ['sftp_password' => 'نام کاربری + رمز عبور'],
                'default_port'=> 22,
                'fields'      => ['host', 'port', 'username', 'password', 'path'],
                'capabilities'=> [
                    'queue' => false, 'supervisor' => false, 'redis' => false,
                    'ssl' => false, 'database' => false, 'storage' => false,
                    'cron' => false, 'docker' => false, 'reboot' => false,
                ],
            ],
            'docker' => [
                'label'       => 'Docker Host',
                'label_short' => 'Docker',
                'description' => 'اجرا در کانتینر Docker روی سرور راه‌دور',
                'color'       => 'cyan',
                'icon'        => 'docker',
                'implemented' => false,
                'auth_types'  => ['key' => 'کلید SSH', 'password' => 'رمز عبور SSH'],
                'default_port'=> 22,
                'fields'      => ['host', 'port', 'username', 'ssh_auth'],
                'capabilities'=> [
                    'queue' => true, 'supervisor' => false, 'redis' => true,
                    'ssl' => false, 'database' => true, 'storage' => true,
                    'cron' => true, 'docker' => true, 'reboot' => true,
                ],
            ],
        ];
    }
}
