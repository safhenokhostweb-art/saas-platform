<?php

namespace Tests\Feature\ExceptionHandling;

use App\Exceptions\Handler;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * 2026-11-06: an unknown api/* path (reported as "GET /api/motions/catalog
 * returns 500 server_error") already answered with status 404, but its body
 * said `server_error` / «خطای سرور. بعداً تلاش کنید.», so the caller chased a
 * crash that never happened. A 404 is `not_found`; a real failure stays
 * `server_error` with status 500.
 */
class ApiNotFoundHandlingTest extends TestCase
{
    public function test_an_unknown_api_route_is_reported_as_not_found(): void
    {
        $response = $this->getJson('/api/this-route-does-not-exist-'.uniqid());

        $response->assertNotFound()
            ->assertExactJson([
                'valid' => false,
                'error' => 'not_found',
                'message' => 'مسیر یا منبع درخواستی یافت نشد.',
            ]);
    }

    public function test_an_abort_404_inside_an_api_route_is_reported_as_not_found(): void
    {
        $response = $this->app->make(Handler::class)
            ->render(Request::create('/api/motion-runtime/manifest'), new NotFoundHttpException('No such asset.'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('not_found', json_decode($response->getContent(), true)['error']);
    }

    public function test_a_real_failure_on_an_api_route_is_still_a_server_error(): void
    {
        $response = $this->app->make(Handler::class)
            ->render(Request::create('/api/motion-runtime/manifest'), new RuntimeException('boom'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('server_error', json_decode($response->getContent(), true)['error']);
    }

    public function test_web_routes_keep_their_own_404_page(): void
    {
        $this->get('/this-page-does-not-exist-'.uniqid())->assertNotFound();
    }
}
