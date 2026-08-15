<?php

namespace App\Services;

use App\Models\Site;
use LogicException;
use SaaSPlatform\CoreUtils\Contracts\SiteContextContract;

/** Site-only context. Platform and reseller identity deliberately live elsewhere. */
final class SiteContext implements SiteContextContract
{
    private ?int $siteId = null;

    public function siteId(): ?int { return $this->siteId; }
    public function hasSite(): bool { return $this->siteId !== null; }
    public function requireSiteId(): int { return $this->siteId ?? throw new LogicException('A site context is required.'); }

    public function setSiteId(int $siteId): void
    {
        if ($siteId < 1) throw new LogicException('A site context requires a positive site identifier.');
        $this->siteId = $siteId;
    }

    public function setSite(Site $site): void { $this->setSiteId((int) $site->getKey()); }
    public function clear(): void { $this->siteId = null; }
    public function snapshot(): ?array { return $this->siteId === null ? null : ['site_id' => $this->siteId]; }
}
