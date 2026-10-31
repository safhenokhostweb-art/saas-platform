<?php

namespace Tests\Unit\DevOps;

use App\Services\DevOps\Exceptions\UnsafePrunePathException;
use App\Services\DevOps\PrunePathGuard;
use PHPUnit\Framework\TestCase;

class PrunePathGuardTest extends TestCase
{
    private PrunePathGuard $guard;

    private const ROOT = '/var/www/cust_acme_12';
    private const LOGS = '/var/www/cust_acme_12/storage/logs';

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new PrunePathGuard();
    }

    // ─── Web roots ───────────────────────────────────────────────────────────

    public function test_accepts_real_site_roots(): void
    {
        $this->assertSame(self::ROOT, $this->guard->assertSafeWebRoot(self::ROOT));
        $this->assertSame(self::ROOT, $this->guard->assertSafeWebRoot(self::ROOT . '/'));
        $this->assertSame('/home/acme/public_html', $this->guard->assertSafeWebRoot('/home/acme/public_html'));
    }

    /**
     * @dataProvider unsafeRoots
     */
    public function test_rejects_unsafe_roots(string $root): void
    {
        $this->expectException(UnsafePrunePathException::class);
        $this->guard->assertSafeWebRoot($root);
    }

    public static function unsafeRoots(): array
    {
        return [
            'filesystem root'   => ['/'],
            'empty'             => [''],
            'relative'          => ['var/www/acme'],
            'system dir'        => ['/etc'],
            'all sites'         => ['/var/www'],
            'all homes'         => ['/home'],
            'shared tmp'        => ['/tmp'],
            'too shallow'       => ['/acme'],
            'traversal'         => ['/var/www/acme/../../etc'],
            'dot segment'       => ['/var/www/./acme'],
            'double slash'      => ['/var/www//acme'],
            'newline injection' => ["/var/www/acme\n/etc"],
            'nul byte'          => ["/var/www/acme\0"],
        ];
    }

    // ─── Fixed targets ───────────────────────────────────────────────────────

    public function test_target_directory_is_built_under_root(): void
    {
        $this->assertSame(self::LOGS, $this->guard->targetDirectory(self::ROOT, 'storage/logs'));
    }

    /**
     * @dataProvider unsafeTargets
     */
    public function test_rejects_targets_outside_storage_or_overlapping_media(string $relative): void
    {
        $this->expectException(UnsafePrunePathException::class);
        $this->guard->targetDirectory(self::ROOT, $relative);
    }

    public static function unsafeTargets(): array
    {
        return [
            'traversal out'        => ['storage/../.env'],
            'outside storage'      => ['vendor'],
            'public dir'           => ['public'],
            'whole storage'        => ['storage'],
            'parent of media'      => ['storage/app'],
            'public disk'          => ['storage/app/public'],
            'inside public disk'   => ['storage/app/public/cache'],
            'uploads'              => ['storage/app/uploads'],
            'storage-like prefix'  => ['storagex/logs'],
        ];
    }

    // ─── Candidates reported by the host ─────────────────────────────────────

    public function test_accepts_plain_file_inside_target(): void
    {
        $this->assertNull($this->guard->rejectionReason(self::LOGS . '/laravel-2026-09-01.log', self::LOGS, self::ROOT));
        $this->assertNull($this->guard->rejectionReason(self::LOGS . '/archive/old.log', self::LOGS, self::ROOT));
    }

    /**
     * @dataProvider rejectedCandidates
     */
    public function test_rejects_dangerous_candidates(string $path, string $reason): void
    {
        $this->assertSame($reason, $this->guard->rejectionReason($path, self::LOGS, self::ROOT));
        $this->assertFalse($this->guard->isSafeCandidate($path, self::LOGS, self::ROOT));
    }

    public static function rejectedCandidates(): array
    {
        return [
            'traversal into media' => [self::LOGS . '/../app/public/logo.png', 'not_normalized'],
            'traversal to env'     => [self::LOGS . '/../../.env', 'not_normalized'],
            'dot segment'          => [self::LOGS . '/./x.log', 'not_normalized'],
            'relative path'        => ['storage/logs/x.log', 'not_normalized'],
            'control character'    => [self::LOGS . "/x.log\n/etc/passwd", 'not_normalized'],
            'other system file'    => ['/etc/passwd', 'outside_target'],
            'sibling site'         => ['/var/www/cust_other_13/storage/logs/x.log', 'outside_target'],
            'prefix confusion'     => [self::LOGS . '-evil/x.log', 'outside_target'],
            'target dir itself'    => [self::LOGS, 'outside_target'],
            'gitignore'            => [self::LOGS . '/.gitignore', 'gitignore'],
        ];
    }

    public function test_posix_quoting_is_platform_independent(): void
    {
        $this->assertSame("'/var/www/a b/x.log'", \App\Services\DevOps\PosixShell::quote('/var/www/a b/x.log'));
        $this->assertSame("'it'\\''s \$(rm -rf /)'", \App\Services\DevOps\PosixShell::quote("it's \$(rm -rf /)"));
    }

    public function test_protected_media_is_rejected_even_when_target_overlaps(): void
    {
        // A misconfigured target spanning storage/app must still never yield media files.
        $target = self::ROOT . '/storage/app';

        $this->assertSame('protected', $this->guard->rejectionReason($target . '/public/products/a.jpg', $target, self::ROOT));
        $this->assertSame('protected', $this->guard->rejectionReason($target . '/uploads/doc.pdf', $target, self::ROOT));
        $this->assertNull($this->guard->rejectionReason($target . '/livewire-tmp/x.tmp', $target, self::ROOT));
    }
}
