<?php

namespace App\Jobs;

use App\Models\CustomerInstallation;
use App\Services\Onboarding\SiteTemplateBinder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * ADR-088 — pushes an onboarded site's bound theme onto the live
 * customer-site once its installation is complete. A brand-new subdomain
 * can take a few minutes to resolve and serve HTTPS, so a refused push is
 * retried with growing delays before it is left as `failed` for the admin
 * to retry from the installation page.
 */
class PushSiteTemplateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 60;

    /** Seconds before each retry of a refused push. */
    public const RETRY_DELAYS = [30, 120, 300];

    public function __construct(public readonly int $installationId) {}

    public function handle(SiteTemplateBinder $binder): void
    {
        $installation = CustomerInstallation::find($this->installationId);
        if ($installation === null || ! $installation->isComplete()) {
            return;
        }

        if ($binder->bind($installation)) {
            return;
        }

        $attempt = $this->attempts();
        if ($attempt < $this->tries) {
            $this->release(self::RETRY_DELAYS[min($attempt - 1, count(self::RETRY_DELAYS) - 1)]);
        }
    }
}
