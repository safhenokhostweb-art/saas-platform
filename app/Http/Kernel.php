<?php
namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $middleware = [
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        // PHASE 8.0 — Security headers on ALL responses
        \App\Http\Middleware\SecurityHeaders::class,
    ];

    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\AddNoindexHeaders::class,
            \App\Http\Middleware\DetectResellerFromDomain::class,
            \App\Http\Middleware\SetTenantContext::class,
            // PHASE 8.2 — Per-tenant maintenance isolation (must run after SetTenantContext)
            \App\Http\Middleware\CheckTenantMaintenance::class,
        ],

        'api' => [
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'admin' => \App\Http\Middleware\AdminMiddleware::class,
        'permission' => \App\Http\Middleware\CheckPermission::class,
        'reseller' => \App\Http\Middleware\EnsureUserIsReseller::class,
        'tenant.required' => \App\Http\Middleware\EnsureTenantScope::class,
        'profile.form.enabled' => \App\Http\Middleware\CheckProfileFormEnabled::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'notifications.enabled' => \App\Http\Middleware\EnsureNotificationsModuleEnabled::class,
        'wizard' => \App\Http\Middleware\EnsureSuperAdminForWizard::class,
        'tenant.api' => \App\Http\Middleware\AuthenticateTenantApi::class,
    ];
}
