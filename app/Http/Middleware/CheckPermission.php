<?php
namespace App\Http\Middleware;

use App\Services\AuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * بررسی دسترسی به permission.
 * استفاده: middleware('permission:sites') یا middleware('permission:sites,customers')
 */
class CheckPermission
{
    public function __construct(
        protected AuthorizationService $auth
    ) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(403, 'ابتدا وارد شوید');
        }

        foreach ($permissions as $perm) {
            if ($this->auth->can($user, $perm)) {
                return $next($request);
            }
        }

        abort(403, 'شما دسترسی به این بخش ندارید.');
    }
}
