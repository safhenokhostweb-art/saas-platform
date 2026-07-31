<?php

namespace Tests\Unit\Modules\SeoAdvanced;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use SaaSPlatform\Modules\SeoAdvanced\Http\Middleware\SeoAdvancedRedirectMiddleware;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedRedirect;
use Tests\TestCase;

/**
 * Phase 18-C — Part 2/7: SEO Redirect Runtime scenarios — proves the
 * Redirect Manager is a real runtime effect now, not just CRUD.
 */
class SeoRedirectRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private const SITE_ID = 1; // matches SeoAdvancedRedirectMiddleware::SINGLE_TENANT_SITE_ID

    private function middleware(): SeoAdvancedRedirectMiddleware
    {
        return new SeoAdvancedRedirectMiddleware();
    }

    private function passthrough()
    {
        return fn (Request $r) => 'next-called';
    }

    public function test_matching_active_redirect_returns_301_by_default(): void
    {
        SeoAdvancedRedirect::create([
            'site_id' => self::SITE_ID, 'from_path' => '/old-page', 'to_path' => '/new-page',
            'status_code' => 301, 'is_active' => true,
        ]);

        $response = $this->middleware()->handle(Request::create('/old-page', 'GET'), $this->passthrough());

        $this->assertSame(301, $response->getStatusCode());
        $this->assertStringEndsWith('/new-page', $response->headers->get('Location'));
    }

    public function test_matching_active_redirect_supports_302(): void
    {
        SeoAdvancedRedirect::create([
            'site_id' => self::SITE_ID, 'from_path' => '/temp-page', 'to_path' => '/somewhere-else',
            'status_code' => 302, 'is_active' => true,
        ]);

        $response = $this->middleware()->handle(Request::create('/temp-page', 'GET'), $this->passthrough());

        $this->assertSame(302, $response->getStatusCode());
    }

    public function test_inactive_redirect_is_not_applied(): void
    {
        SeoAdvancedRedirect::create([
            'site_id' => self::SITE_ID, 'from_path' => '/disabled-redirect', 'to_path' => '/x',
            'status_code' => 301, 'is_active' => false,
        ]);

        $result = $this->middleware()->handle(Request::create('/disabled-redirect', 'GET'), $this->passthrough());

        $this->assertSame('next-called', $result);
    }

    public function test_non_matching_path_passes_through(): void
    {
        $result = $this->middleware()->handle(Request::create('/no-such-redirect', 'GET'), $this->passthrough());

        $this->assertSame('next-called', $result);
    }

    public function test_non_get_requests_are_never_redirected(): void
    {
        SeoAdvancedRedirect::create([
            'site_id' => self::SITE_ID, 'from_path' => '/form-submit', 'to_path' => '/x',
            'status_code' => 301, 'is_active' => true,
        ]);

        $result = $this->middleware()->handle(Request::create('/form-submit', 'POST'), $this->passthrough());

        $this->assertSame('next-called', $result);
    }

    public function test_the_modules_own_admin_routes_are_never_redirected(): void
    {
        SeoAdvancedRedirect::create([
            'site_id' => self::SITE_ID, 'from_path' => '/admin/seo-advanced/1', 'to_path' => '/x',
            'status_code' => 301, 'is_active' => true,
        ]);

        $result = $this->middleware()->handle(Request::create('/admin/seo-advanced/1', 'GET'), $this->passthrough());

        $this->assertSame('next-called', $result);
    }
}
