<?php
namespace App\Services\Installer;

/**
 * Atomic .env writer for the Platform Installer.
 * All writes are atomic (tmp → rename) to prevent partial-write corruption.
 */
class EnvironmentWriter
{
    private string $envPath;

    public function __construct()
    {
        $this->envPath = base_path('.env');
    }

    // ─── Write ───────────────────────────────────────────────────────────────

    public function write(array $values): array
    {
        if (!file_exists($this->envPath)) {
            $example = base_path('.env.example');
            if (file_exists($example)) {
                copy($example, $this->envPath);
            } else {
                return ['success' => false, 'message' => 'فایل .env و .env.example هیچکدام یافت نشدند'];
            }
        }

        $realPath = realpath($this->envPath);
        if (!$realPath || !str_starts_with($realPath, realpath(base_path()))) {
            return ['success' => false, 'message' => 'مسیر .env معتبر نیست'];
        }

        $content = file_get_contents($this->envPath);
        if ($content === false) {
            return ['success' => false, 'message' => 'خواندن .env ناموفق بود'];
        }

        foreach ($values as $key => $value) {
            $key     = strtoupper(trim((string) $key));
            $quoted  = $this->quoteValue((string) $value);
            $pattern = '/^' . preg_quote($key, '/') . '=.*/m';

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, "{$key}={$quoted}", $content);
            } else {
                $content .= PHP_EOL . "{$key}={$quoted}";
            }
        }

        return $this->atomicWrite($content);
    }

    // ─── App Key ─────────────────────────────────────────────────────────────

    public function generateAppKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    public function writeAppKey(): array
    {
        $key = $this->generateAppKey();
        $result = $this->write(['APP_KEY' => $key]);
        $result['key'] = $key;
        return $result;
    }

    // ─── Read ─────────────────────────────────────────────────────────────────

    public function get(string $key, string $default = ''): string
    {
        if (!file_exists($this->envPath)) {
            return $default;
        }
        $content = file_get_contents($this->envPath);
        if (preg_match('/^' . preg_quote(strtoupper($key), '/') . '=(.*)$/m', $content, $m)) {
            return trim(trim($m[1]), '"\'');
        }
        return $default;
    }

    public function exists(): bool
    {
        return file_exists($this->envPath);
    }

    // ─── Database config shortcut ─────────────────────────────────────────────

    public function writeDatabaseConfig(array $db): array
    {
        return $this->write([
            'DB_HOST'     => $db['host']     ?? '127.0.0.1',
            'DB_PORT'     => $db['port']     ?? '3306',
            'DB_DATABASE' => $db['database'] ?? '',
            'DB_USERNAME' => $db['username'] ?? '',
            'DB_PASSWORD' => $db['password'] ?? '',
        ]);
    }

    public function writeRedisConfig(array $redis): array
    {
        return $this->write([
            'REDIS_HOST'     => $redis['host']     ?? '127.0.0.1',
            'REDIS_PORT'     => $redis['port']     ?? '6379',
            'REDIS_PASSWORD' => $redis['password'] ?? '',
        ]);
    }

    public function writeMailConfig(array $mail): array
    {
        return $this->write([
            'MAIL_MAILER'       => $mail['mailer']   ?? 'smtp',
            'MAIL_HOST'         => $mail['host']     ?? '',
            'MAIL_PORT'         => $mail['port']     ?? '587',
            'MAIL_USERNAME'     => $mail['username'] ?? '',
            'MAIL_PASSWORD'     => $mail['password'] ?? '',
            'MAIL_ENCRYPTION'   => $mail['encryption'] ?? 'tls',
            'MAIL_FROM_ADDRESS' => $mail['from_address'] ?? '',
            'MAIL_FROM_NAME'    => $mail['from_name']    ?? 'پلتفرم',
        ]);
    }

    public function writeArvanConfig(array $arvan): array
    {
        return $this->write([
            'ARVAN_ACCESS_KEY'  => $arvan['access_key']  ?? '',
            'ARVAN_SECRET_KEY'  => $arvan['secret_key']  ?? '',
            'ARVAN_BUCKET'      => $arvan['bucket']      ?? '',
            'ARVAN_ENDPOINT'    => $arvan['endpoint']    ?? 'https://s3.ir-thr-at1.arvanstorage.ir',
            'ARVAN_REGION'      => $arvan['region']      ?? 'ir-thr-at1',
        ]);
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    private function atomicWrite(string $content): array
    {
        $tmp = $this->envPath . '.tmp.' . getmypid();
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            return ['success' => false, 'message' => 'نوشتن فایل موقت ناموفق بود'];
        }
        if (!rename($tmp, $this->envPath)) {
            @unlink($tmp);
            return ['success' => false, 'message' => 'جایگزینی .env ناموفق بود'];
        }
        return ['success' => true, 'message' => '.env با موفقیت بروزرسانی شد'];
    }

    private function quoteValue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (preg_match('/[\s"\'\\\\#]/', $value)) {
            return '"' . addslashes($value) . '"';
        }
        return $value;
    }
}
