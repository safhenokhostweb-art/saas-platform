<?php

namespace App\Jobs;

use App\Models\SiteDomain;
use App\Services\Domains\SiteDomainProvisioner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Phase 3 — connect a site domain / issue its SSL in the background
 * (SiteDomainProvisioner). One attempt: certbot has Let's Encrypt rate
 * limits, so a failure is reported on the domain panel, never retried blindly.
 */
class SiteDomainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $siteDomainId, public readonly string $action) {}

    public function handle(SiteDomainProvisioner $provisioner): void
    {
        $row = SiteDomain::query()->find($this->siteDomainId);
        if ($row === null) {
            return;
        }

        $provisioner->run($row, $this->action);
    }

    public function failed(\Throwable $e): void
    {
        SiteDomain::query()->whereKey($this->siteDomainId)->update([
            'status'     => SiteDomain::STATUS_FAILED,
            'last_error' => 'اجرای پس‌زمینه متوقف شد: ' . mb_substr($e->getMessage(), 0, 500),
        ]);
    }
}
