<?php

namespace Tests\Unit\Security;

use Tests\TestCase;

/**
 * AUTH-02 (Full Product Commercial Audit, TASK 1/3): a raw `is_admin`-only
 * middleware (`App\Http\Middleware\IsAdmin`) bypasses the canonical,
 * role-aware `AuthorizationService`/`AdminMiddleware` gate. It was removed
 * once for exactly this reason, then silently reintroduced (unregistered,
 * but present) in a later commit. Canonical admin authorization is, and
 * must remain, `AdminMiddleware` (the registered `admin` route-middleware
 * alias) backed by `AuthorizationService`, never a second raw-flag class.
 *
 * This test fails loudly if the class is ever recreated, so the next
 * person to do it (for a "quick" bypass) hits this history instead of
 * silently reintroducing the same foot-gun a third time.
 */
class NoRawAdminBypassMiddlewareTest extends TestCase
{
    public function test_the_raw_is_admin_only_middleware_file_does_not_exist(): void
    {
        // Checking the file directly (not class_exists()) so this doesn't
        // depend on the classmap autoloader having been regenerated after
        // deletion, and doesn't itself trigger a fresh autoload attempt.
        $this->assertFileDoesNotExist(
            app_path('Http/Middleware/IsAdmin.php'),
            'App\Http\Middleware\IsAdmin was removed (AUTH-02) because it checks only the raw '
            . '`is_admin` flag and bypasses AuthorizationService\'s role-aware canonical gate. '
            . 'If you need admin-only route protection, use the registered `admin` middleware '
            . '(AdminMiddleware) instead of recreating this class.'
        );
    }

    public function test_the_admin_middleware_alias_resolves_to_the_canonical_admin_middleware(): void
    {
        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        $prop = new \ReflectionProperty($kernel, 'routeMiddleware');
        $prop->setAccessible(true);
        $aliases = $prop->getValue($kernel);

        $this->assertSame(\App\Http\Middleware\AdminMiddleware::class, $aliases['admin'] ?? null);
    }
}
