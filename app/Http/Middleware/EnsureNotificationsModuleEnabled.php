<?php
namespace App\Http\Middleware;

use App\Helpers\PlatformModules;
use Closure;
use Illuminate\Http\Request;

class EnsureNotificationsModuleEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (!PlatformModules::isEnabled('notifications')) {
            abort(404, 'ماژول اعلان غیرفعال است.');
        }
        return $next($request);
    }
}
