<?php

namespace Tests\Feature\Infrastructure;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression guard for the three production defects the Canvas automated test
 * framework discovered on 2026-10-02 (see AI_TESTING_GUIDE.md §6). Each test
 * below pins the specific fix, so the defect cannot silently return.
 */
class CanvasProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed.lock'), '1');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    private function repoPath(string $relative): string
    {
        return dirname(__DIR__, 3) . '/' . ltrim($relative, '/');
    }

    /**
     * Source text with Blade ({{-- --}}) and PHP block comments stripped.
     *
     * Both files below explain in prose which CDN origin caused the defect, so
     * a raw substring search would match its own documentation. Stripping
     * comments keeps the assertion about CODE while still allowing the fix to
     * be explained where a future maintainer will actually read it.
     */
    private function codeOf(string $relative): string
    {
        $source = (string) file_get_contents($this->repoPath($relative));

        return (string) preg_replace(
            ['/\{\{--.*?--\}\}/s', '#/\*.*?\*/#s', '#^\s*//.*$#m'],
            '',
            $source
        );
    }

    // ── Defect 1: CSP blocked the SortableJS CDN, disabling Canvas drag & drop ──

    public function test_canvas_editor_loads_no_third_party_cdn_script(): void
    {
        $canvas = $this->codeOf('resources/views/admin/theme-builder/canvas.blade.php');

        self::assertStringNotContainsString('cdn.jsdelivr.net', $canvas);
        self::assertStringNotContainsString('Sortable.min.js', $canvas);
    }

    /**
     * The two admin pages that genuinely DO use Sortable must not reintroduce
     * the CDN: the bundled copy already provides window.Sortable, and a CDN
     * tag here is exactly the CSP violation that was found in production.
     *
     * Scoped to Sortable on purpose. page-builder-script.blade.php still
     * references cdn.jsdelivr.net for the TinyMCE `base_url`, which is a
     * separate and still-open finding: TinyMCE is NOT bundled, so removing
     * that CDN reference would break the rich-text editor. Pinning a blanket
     * "no jsdelivr anywhere" rule here would either fail on that legitimate
     * remaining reference or push someone into deleting TinyMCE.
     */
    public function test_sortable_consumers_load_no_third_party_cdn_script(): void
    {
        foreach ([
            'resources/views/admin/pages/index.blade.php',
            'resources/views/admin/pages/partials/page-builder-script.blade.php',
        ] as $view) {
            $code = $this->codeOf($view);
            self::assertStringNotContainsString('Sortable.min.js', $code, $view);
            self::assertStringNotContainsString('sortablejs', strtolower($code), $view);
        }
    }

    public function test_sortable_is_supplied_by_the_vite_bundle_instead(): void
    {
        // The CDN tag was only ever a duplicate: layouts/canvas.blade.php loads
        // app.js through @vite, and app.js imports sortablejs and assigns the
        // global. If either half of that chain regresses, drag & drop is broken
        // again and this fails instead of failing silently in production.
        $layout = $this->codeOf('resources/views/layouts/canvas.blade.php');
        self::assertStringContainsString('resources/js/app.js', $layout);

        $appJs = (string) file_get_contents($this->repoPath('resources/js/app.js'));
        self::assertStringContainsString("import Sortable from 'sortablejs'", $appJs);
        self::assertStringContainsString('window.Sortable = Sortable', $appJs);
    }

    public function test_served_csp_allows_no_third_party_script_origin(): void
    {
        // Asserted on the header the browser actually receives, not on the
        // source that builds it, so this tracks the real contract.
        $response = $this->get('/login');
        $csp = $response->headers->get('Content-Security-Policy')
            ?? $response->headers->get('Content-Security-Policy-Report-Only');

        self::assertNotNull($csp, 'No CSP header was sent at all.');
        self::assertStringNotContainsString('cdn.jsdelivr.net', $csp);
        self::assertStringNotContainsString('unpkg.com', $csp);
        self::assertStringNotContainsString('cdnjs.cloudflare.com', $csp);

        // Alpine.js compiles x-* expressions with new Function(), so
        // 'unsafe-eval' is a deliberate, documented trade-off rather than an
        // oversight. It must not be dropped without replacing Alpine.
        self::assertStringContainsString("'unsafe-eval'", $csp);
    }

    // ── Defect 3: a stored avatar path pointing at a missing file 404'd forever ──

    public function test_avatar_url_is_null_when_the_stored_file_is_missing(): void
    {
        Storage::fake('uploads');

        $user = User::create([
            'mobile'   => '09' . random_int(100000000, 999999999),
            'password' => bcrypt('password'),
            'is_admin' => false,
        ]);
        $profile = Profile::create([
            'user_id' => $user->id,
            'mobile'  => $user->mobile,
            'avatar'  => 'uploads/admin-avatars/admin_' . $user->id . '_1.png',
        ]);

        // The DB claims a file that is not on disk. Resolving a URL anyway is
        // exactly what produced a 404 on every authenticated page load; the
        // caller must be told to fall back to its initials avatar instead.
        self::assertNull($profile->avatar_url);
    }

    public function test_avatar_url_still_resolves_when_the_file_exists(): void
    {
        Storage::fake('uploads');

        $user = User::create([
            'mobile'   => '09' . random_int(100000000, 999999999),
            'password' => bcrypt('password'),
            'is_admin' => false,
        ]);
        $profile = Profile::create([
            'user_id' => $user->id,
            'mobile'  => $user->mobile,
            'avatar'  => 'uploads/admin-avatars/admin_' . $user->id . '_1.png',
        ]);
        Storage::disk('uploads')->put('admin-avatars/admin_' . $user->id . '_1.png', 'binary');

        // The existence guard must not become a blanket "hide every avatar".
        self::assertNotNull($profile->fresh()->avatar_url);
    }
}