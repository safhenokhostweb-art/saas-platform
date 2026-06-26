<?php
namespace App\Http\Middleware;

use App\Services\Installer\InstallerService;
use Closure;
use Illuminate\Http\Request;

/**
 * Blocks the installer if install.lock exists.
 * Allows: the /installer/locked page and /installer/unlock (Super Admin only).
 */
class InstallerLockMiddleware
{
    public function __construct(private InstallerService $installer) {}

    public function handle(Request $request, Closure $next)
    {
        $allowedRoutes = [
            'installer.locked',
            'installer.unlock',
            'installer.complete',
            'installer.report-download',
        ];

        if ($this->installer->isLocked() && !in_array($request->route()?->getName(), $allowedRoutes)) {
            return redirect()->route('installer.locked');
        }

        return $next($request);
    }
}
