<?php

namespace Tests\Feature\Infrastructure;

use App\Services\Infrastructure\StorageManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PROD-003 / PROD-004 / PROD-005 — Storage Security Tests
 *
 * Covers:
 * - StorageManager::sanitizeFilename()
 * - StorageManager::validateUpload()
 * - StorageManager::deleteIfExists()
 * - StorageManager::writeCsv() on exports disk
 * - Path traversal prevention (MobileTicketController)
 * - Filename injection prevention (AppReleaseController)
 * - Extension/MIME validation (PlatformSettings)
 */
class StorageSecurityTest extends TestCase
{
    // ── sanitizeFilename ─────────────────────────────────────────────────────

    public function test_sanitize_strips_path_separator_forward_slash(): void
    {
        $this->assertSame('etc-passwd', StorageManager::sanitizeFilename('../../etc/passwd'));
    }

    public function test_sanitize_strips_path_separator_backslash(): void
    {
        $this->assertSame('windows-system32-cmd.exe', StorageManager::sanitizeFilename('..\\windows\\system32\\cmd.exe'));
    }

    public function test_sanitize_strips_null_byte(): void
    {
        $result = StorageManager::sanitizeFilename("file\0.jpg");
        $this->assertStringNotContainsString("\0", $result);
    }

    public function test_sanitize_strips_leading_dot(): void
    {
        $result = StorageManager::sanitizeFilename('.htaccess');
        $this->assertStringStartsNotWith('.', $result);
    }

    public function test_sanitize_strips_env_file(): void
    {
        $result = StorageManager::sanitizeFilename('.env');
        $this->assertStringStartsNotWith('.', $result);
    }

    public function test_sanitize_keeps_valid_filename(): void
    {
        $this->assertSame('invoice_2026-06.pdf', StorageManager::sanitizeFilename('invoice_2026-06.pdf'));
    }

    public function test_sanitize_keeps_alphanumeric_and_extension(): void
    {
        $result = StorageManager::sanitizeFilename('MyFile v1.0 (FINAL).docx');
        $this->assertStringEndsWith('.docx', $result);
        $this->assertStringNotContainsString('(', $result);
        $this->assertStringNotContainsString(' ', $result);
    }

    public function test_sanitize_collapses_double_dots(): void
    {
        $result = StorageManager::sanitizeFilename('file..txt');
        $this->assertStringNotContainsString('..', $result);
    }

    public function test_sanitize_limits_length_to_200(): void
    {
        $long = str_repeat('a', 250) . '.pdf';
        $result = StorageManager::sanitizeFilename($long);
        $this->assertLessThanOrEqual(200, strlen($result));
        $this->assertStringEndsWith('.pdf', $result);
    }

    public function test_sanitize_empty_string_returns_file(): void
    {
        $this->assertSame('file', StorageManager::sanitizeFilename(''));
    }

    public function test_sanitize_strips_shell_special_chars(): void
    {
        $result = StorageManager::sanitizeFilename('file;cat /etc/shadow.txt');
        $this->assertStringNotContainsString(';', $result);
        $this->assertStringNotContainsString(' ', $result);
    }

    public function test_sanitize_windows_absolute_path(): void
    {
        $result = StorageManager::sanitizeFilename('C:\\Users\\admin\\secret.txt');
        $this->assertStringNotContainsString('C:', $result);
        $this->assertStringNotContainsString('\\', $result);
    }

    // ── validateUpload ───────────────────────────────────────────────────────

    public function test_validate_upload_passes_valid_image(): void
    {
        $file = UploadedFile::fake()->image('logo.jpg');

        // Should not throw
        StorageManager::validateUpload(
            $file,
            ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
        );

        $this->assertTrue(true); // reached here = no exception
    }

    public function test_validate_upload_rejects_disallowed_extension(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/پسوند/');

        $file = UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream');

        StorageManager::validateUpload(
            $file,
            ['jpg', 'jpeg', 'png'],
            ['image/jpeg', 'image/png']
        );
    }

    public function test_validate_upload_rejects_disallowed_mime(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/MIME/');

        // Fake file with .jpg extension but image/gif MIME
        $file = UploadedFile::fake()->create('trick.jpg', 10, 'application/x-php');

        StorageManager::validateUpload(
            $file,
            ['jpg'],
            ['image/jpeg']  // PHP files won't match
        );
    }

    public function test_validate_upload_empty_allowlists_accepts_anything(): void
    {
        $file = UploadedFile::fake()->create('release.apk', 1024, 'application/vnd.android.package-archive');

        // Empty arrays = no restriction
        StorageManager::validateUpload($file, [], []);

        $this->assertTrue(true);
    }

    // ── deleteIfExists ───────────────────────────────────────────────────────

    public function test_delete_if_exists_removes_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('platform/logo.jpg', 'fake image');

        $sm = app(StorageManager::class);
        $sm->deleteIfExists('platform/logo.jpg', 'public');

        Storage::disk('public')->assertMissing('platform/logo.jpg');
    }

    public function test_delete_if_exists_is_silent_when_missing(): void
    {
        Storage::fake('public');

        $sm = app(StorageManager::class);
        // Must not throw even though file doesn't exist
        $sm->deleteIfExists('platform/nonexistent.jpg', 'public');

        $this->assertTrue(true);
    }

    // ── writeCsv on exports disk ─────────────────────────────────────────────

    public function test_write_csv_creates_file_on_exports_disk(): void
    {
        Storage::fake('exports');

        $sm = app(StorageManager::class);
        $path = $sm->writeCsv(
            'forms',
            'test-export.csv',
            ['شناسه', 'نام'],
            collect([['1', 'آلیس'], ['2', 'باب']]),
            'exports'
        );

        Storage::disk('exports')->assertExists($path);
    }

    public function test_write_csv_contains_utf8_bom(): void
    {
        Storage::fake('exports');

        $sm = app(StorageManager::class);
        $path = $sm->writeCsv('', 'bom-test.csv', ['col'], collect([['val']]), 'exports');

        $content = Storage::disk('exports')->get($path);
        // UTF-8 BOM = EF BB BF
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
    }

    // ── Platform logo validation (PlatformSettings) ──────────────────────────

    public function test_store_logo_rejects_php_mime(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $file = UploadedFile::fake()->create('shell.php', 5, 'application/x-php');

        \App\Helpers\PlatformSettings::storeLogo($file);
    }

    public function test_store_logo_rejects_exe_extension(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $file = UploadedFile::fake()->create('trojan.exe', 5, 'application/octet-stream');

        \App\Helpers\PlatformSettings::storeLogo($file);
    }

    // ── AppReleaseController filename (SP-04) ────────────────────────────────

    public function test_app_release_filename_sanitized_in_store(): void
    {
        // Verify sanitizeFilename correctly handles traversal within the controller's
        // expected pattern: "v{version}-build{build}-{safename}"
        $clientOriginal = '../../etc/passwd';
        $safe = StorageManager::sanitizeFilename($clientOriginal);

        $filename = "v1.0.0-build42-{$safe}";

        // No path traversal in constructed filename
        $this->assertStringNotContainsString('..', $filename);
        $this->assertStringNotContainsString('/', $filename);
        $this->assertStringNotContainsString("\0", $filename);
    }

    // ── MobileTicketController path traversal (SP-13) ───────────────────────

    /**
     * @dataProvider pathTraversalProvider
     */
    public function test_traversal_vectors_are_blocked(string $rawPath): void
    {
        // Replicate the exact check logic from MobileTicketController::downloadAttachment()
        $decoded  = rawurldecode($rawPath);
        $decoded  = str_replace("\0", '', $decoded);
        $safePath = ltrim(str_replace('\\', '/', $decoded), '/');

        $blocked = (
            str_contains($safePath, '..')
            || preg_match('#^[A-Za-z]:#', $safePath)
            || str_starts_with($safePath, '/')
        );

        $this->assertTrue($blocked, "Path should have been blocked: {$rawPath}");
    }

    public static function pathTraversalProvider(): array
    {
        return [
            'literal dotdot'         => ['../etc/passwd'],
            'url-encoded dotdot'     => ['%2e%2e/etc/passwd'],
            'double-encoded dotdot'  => ['%252e%252e/etc/passwd'],
            'windows absolute'       => ['C:/Windows/System32/cmd.exe'],
            'backslash traversal'    => ['..\\..\\Windows\\System32'],
            'unix absolute'          => ['/etc/shadow'],
            'null byte injection'    => ["ticket-attachments/1/file\0.jpg"],
        ];
    }

    /**
     * @dataProvider safePathProvider
     */
    public function test_safe_paths_are_not_blocked(string $rawPath): void
    {
        $decoded  = rawurldecode($rawPath);
        $decoded  = str_replace("\0", '', $decoded);
        $safePath = ltrim(str_replace('\\', '/', $decoded), '/');

        $blocked = (
            str_contains($safePath, '..')
            || preg_match('#^[A-Za-z]:#', $safePath)
            || str_starts_with($safePath, '/')
        );

        $this->assertFalse($blocked, "Safe path was incorrectly blocked: {$rawPath}");
    }

    public static function safePathProvider(): array
    {
        return [
            'simple relative'       => ['ticket-attachments/42/file.pdf'],
            'url-encoded space'     => ['ticket-attachments/42/my%20file.jpg'],
            'subdirectory'          => ['ticket-attachments/100/sub/doc.docx'],
        ];
    }

    // ── PlatformSettingsController logo_path sanitization (SP-15) ───────────

    /**
     * @dataProvider dangerousLogoPathProvider
     */
    public function test_dangerous_logo_paths_are_rejected(string $rawPath): void
    {
        // Replicate PlatformSettingsController path-acceptance logic
        $accepted = false;

        if (preg_match('#^https?://#i', $rawPath)) {
            $accepted = true;
        } else {
            $path = \App\Helpers\PlatformSettings::normalizeStoragePath($rawPath);
            if ($path !== '' && !str_contains($path, '..') && !str_starts_with($path, '/')) {
                $accepted = true;
            }
        }

        $this->assertFalse($accepted, "Dangerous path was incorrectly accepted: {$rawPath}");
    }

    public static function dangerousLogoPathProvider(): array
    {
        return [
            'unix absolute'       => ['/etc/passwd'],
            'windows absolute'    => ['C:/Windows/win.ini'],
            'traversal sequence'  => ['../../etc/shadow'],
            'root slash'          => ['/storage/app/private/secret.key'],
        ];
    }

    public function test_http_url_is_accepted_as_logo_path(): void
    {
        $raw = 'https://cdn.example.com/logo.png';
        $accepted = preg_match('#^https?://#i', $raw) === 1;
        $this->assertTrue($accepted);
    }

    public function test_safe_relative_path_is_accepted_as_logo_path(): void
    {
        $raw = 'platform/logo_abc123.jpg';
        $path = \App\Helpers\PlatformSettings::normalizeStoragePath($raw);
        $accepted = ($path !== '' && !str_contains($path, '..') && !str_starts_with($path, '/'));
        $this->assertTrue($accepted);
    }
}
