<?php

namespace App\Services;

use App\Models\Site;
use Closure;
use SaaSPlatform\CoreUtils\Contracts\SiteContextContract;

/** Explicit context lifecycle for tenant-specific queued or console work. */
final class SiteContextRunner
{
    public function __construct(private readonly SiteContextContract $context) {}

    public function runForSiteId(int $siteId, Closure $callback): mixed
    {
        $site = Site::query()->findOrFail($siteId);
        $this->context->clear();
        $this->context->setSiteId((int) $site->getKey());
        try { return $callback($site); }
        finally { $this->context->clear(); }
    }

    public function runPlatform(Closure $callback): mixed
    {
        $this->context->clear();
        try { return $callback(); }
        finally { $this->context->clear(); }
    }
}
