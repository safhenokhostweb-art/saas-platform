<?php

namespace App\Http\Middleware;

use App\Services\AuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function __construct(
        protected AuthorizationService $auth
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            abort(403, 'ابتدا وارد شوید');
        }

        $user = auth()->user();
        if (!$this->auth->canAccessAdmin($user)) {
            abort(403, 'شما دسترسی ادمین ندارید');
        }

        return $next($request);
    }
}
