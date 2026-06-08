<?php
namespace App\Http\Controllers\Admin\Infrastructure;

use App\Http\Controllers\Controller;
use App\Models\Infrastructure\InfraServer;
use App\Models\Infrastructure\InfraDomain;
use App\Models\Infrastructure\InfraHosting;
use App\Models\Infrastructure\InfraStorageProvider;
use App\Models\Infrastructure\InfraDatabaseServer;

class InfrastructureOverviewController extends Controller
{
    public function index()
    {
        $servers        = InfraServer::where('is_active', true)->count();
        $onlineServers  = InfraServer::where('status', 'online')->count();
        $offlineServers = InfraServer::where('status', 'offline')->count();
        $domains        = InfraDomain::count();
        $expiringDomains = InfraDomain::whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', now()->addDays(30))
            ->whereDate('expires_at', '>=', now())
            ->count();
        $expiredDomains = InfraDomain::whereNotNull('expires_at')
            ->whereDate('expires_at', '<', now())
            ->count();
        $sslExpiring = InfraDomain::where('ssl_enabled', true)
            ->whereNotNull('ssl_expires_at')
            ->whereDate('ssl_expires_at', '<=', now()->addDays(14))
            ->whereDate('ssl_expires_at', '>=', now())
            ->count();
        $storageProviders = InfraStorageProvider::where('is_active', true)->count();
        $dbServers        = InfraDatabaseServer::where('is_active', true)->count();

        $recentServers = InfraServer::latest()->take(5)->get();
        $criticalDomains = InfraDomain::whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', now()->addDays(30))
            ->orderBy('expires_at')
            ->take(5)
            ->get();

        return view('admin.infrastructure.overview', compact(
            'servers', 'onlineServers', 'offlineServers',
            'domains', 'expiringDomains', 'expiredDomains',
            'sslExpiring', 'storageProviders', 'dbServers',
            'recentServers', 'criticalDomains'
        ));
    }
}
