<?php

namespace Tests\Feature\ExceptionHandling;

use App\Exceptions\Handler;
use App\Models\Role;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Part 2 (7-part local audit task, 2026-09-19) — regression coverage for
 * the isolated HTTP 419 reported on the Theme Builder "sync pages" modal
 * (admin/theme-builder/{theme}/sync-pages).
 *
 * Root cause: a stale CSRF token. The modal's hidden _token input is baked
 * in at the Theme Builder index page's initial render
 * (resources/views/admin/theme-builder/index.blade.php's static @csrf); if
 * the session has since expired (config/session.php 'lifetime' = 120 min)
 * or been regenerated before the admin submits the modal, VerifyCsrfToken
 * throws TokenMismatchException. This is correct, un-weakened CSRF
 * behavior (never bypassed) — the defect was only in what the admin saw
 * afterward: Laravel's bare default 419 page, with no explanation and no
 * way back to their in-progress work.
 *
 * Laravel's own test HTTP kernel deliberately bypasses CSRF verification
 * entirely for any request made while PHPUnit is running
 * (VerifyCsrfToken::runningUnitTests()) — by design, so ordinary feature
 * tests don't need to juggle tokens. That means a real end-to-end 419
 * cannot be reproduced through $this->post(...) here. Instead, this test
 * exercises App\Exceptions\Handler::render() directly with a real
 * TokenMismatchException, which is the actual unit of behavior the fix
 * changed and the only way to genuinely prove it.
 */
class TokenMismatchHandlingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::create([
            'mobile' => '0912' . random_int(1000000, 9999999),
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_active' => true,
        ]);

        $role = Role::where('slug', Role::SUPER_ADMIN)->first();
        if ($role) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function theme(): Theme
    {
        return Theme::create([
            'name' => 'قالب تست 419',
            'slug' => 'csrf-419-test-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_DRAFT,
            'category' => Theme::CATEGORY_SAAS,
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => []]],
            'global_styles' => [],
            'settings' => [],
        ]);
    }

    public function test_token_mismatch_on_a_web_request_redirects_back_with_a_visible_flash_message_instead_of_a_bare_419_page(): void
    {
        $theme = $this->theme();
        $syncUrl = route('admin.theme-builder.sync-pages', $theme);
        $indexUrl = route('admin.theme-builder.index');

        $request = Request::create($syncUrl, 'POST', ['site_id' => '1']);
        $request->headers->set('referer', $indexUrl);
        $request->setLaravelSession($this->app['session']->driver());
        // redirect()->back() resolves the previous URL from the request
        // bound in the container, not from the $request passed to
        // render() — in real HTTP handling Laravel binds the actual
        // current request itself, so this mirrors that for the test.
        $this->app->instance('request', $request);

        $handler = $this->app->make(Handler::class);
        $response = $handler->render($request, new TokenMismatchException('CSRF token mismatch.'));

        self::assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response, 'a stale-token admin form submission must redirect back with an explanation, not render a bare 419 page');
        self::assertSame($indexUrl, $response->getTargetUrl());

        $flashed = $response->getSession()->get('error');
        self::assertNotEmpty($flashed, 'the 419 cause must remain visible to the admin, never a silently-swallowed failure');
        self::assertStringContainsString('نشست شما منقضی شده است', $flashed);

        // The stale token itself must never be re-flashed back into the form.
        self::assertArrayNotHasKey('_token', $response->getSession()->getOldInput());
    }

    public function test_token_mismatch_on_an_api_request_still_returns_the_existing_json_error_shape(): void
    {
        // Guards the pre-existing api/* JSON error contract
        // (App\Exceptions\Handler::render()'s first branch) against being
        // accidentally short-circuited by the new TokenMismatchException
        // branch added below it.
        $request = Request::create('/api/theme/install', 'POST');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->headers->set('Accept', 'application/json');
        $request->setLaravelSession($this->app['session']->driver());

        $handler = $this->app->make(Handler::class);
        $response = $handler->render($request, new TokenMismatchException('CSRF token mismatch.'));

        self::assertSame(419, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        self::assertFalse($payload['valid']);
    }

    public function test_sync_pages_route_requires_admin_auth_and_post(): void
    {
        $theme = $this->theme();

        // Unauthenticated: must not reach the controller.
        $this->post(route('admin.theme-builder.sync-pages', $theme))
            ->assertRedirect();

        // Wrong method: GET must not be routed to the same action.
        $this->actingAs($this->admin())
            ->get(route('admin.theme-builder.sync-pages', $theme))
            ->assertMethodNotAllowed();
    }
}
