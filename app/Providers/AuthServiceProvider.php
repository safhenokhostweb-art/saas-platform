<?php

namespace App\Providers;

use App\Models\Site;
use App\Services\AuthorizationService;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        //
    ];

    public function boot(): void
    {
        Gate::define('access-site', function ($user, Site $site) {
            return app(AuthorizationService::class)->canAccessSite($user, $site);
        });

        Gate::define('accessWizard', function ($user) {
            return $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
        });
    }
}
