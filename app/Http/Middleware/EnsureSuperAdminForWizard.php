<?php
namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wizard access control: only Super Admin (Main Platform Admin) may access.
 * Tenant admins (customers) and Staff must not run the Wizard.
 *
 * Wizard performs structural operations: create sites, regenerate structures,
 * install template packs, apply design system, activate modules, import demo data.
 * These must be protected to prevent data corruption or unintended site resets.
 */
class EnsureSuperAdminForWizard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            abort(403, __('Unauthenticated.'));
        }

        $user = auth()->user();
        if (!$user instanceof User || !$user->isSuperAdmin()) {
            abort(403, 'فقط مدیر اصلی پلتفرم (Super Admin) به این بخش دسترسی دارد.');
        }

        return $next($request);
    }
}
