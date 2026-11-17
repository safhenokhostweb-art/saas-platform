<?php
namespace Tests;

use App\Services\CustomerInstallation\Dns\DnsResolver;
use App\Services\ThemeBuilder\ReverseEngineering\PlaywrightExtractionService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\FakeDnsResolver;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Phase 2g — a real Chromium/Playwright launch must never happen
        // inside the ordinary PHPUnit suite. A test's Theme lives inside an
        // uncommitted RefreshDatabase transaction that a real child browser
        // process reaching the app over real HTTP cannot see, so a genuine
        // launch here wouldn't just be slow — it would silently verify the
        // WRONG page (a 404, or stale committed data) instead of skipping
        // honestly. Reporting "unavailable" by default makes every
        // browser-backed check degrade to the same honest UNVERIFIED finding
        // a real environment without Node/Playwright gets. A test that wants
        // to prove real integration overrides this binding itself (see
        // CaptureThemeScreenshotsCommandTest's $this->mock() convention).
        $this->app->bind(PlaywrightExtractionService::class, fn () => new class extends PlaywrightExtractionService {
            public function isAvailable(): bool
            {
                return false;
            }
        });

        // The installation DNS preflight must never query real DNS in the
        // suite (offline runs, real domains in fixtures). Every lookup is
        // UNKNOWN, which the preflight does not block on; tests that exercise
        // DNS bind their own FakeDnsResolver.
        $this->app->instance(DnsResolver::class, new FakeDnsResolver());
    }
}
