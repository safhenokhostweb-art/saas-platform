<?php

namespace Tests\Unit\Services;

use App\Models\Module;
use App\Services\ModuleEcosystem\EntitlementResolver;
use App\Services\ModuleLicenseService;
use App\Services\SiteContext;
use LogicException;
use Mockery;
use PHPUnit\Framework\TestCase;

class ModuleEntitlementResolverContextTest extends TestCase
{
    public function test_current_site_resolution_fails_closed_without_site_context(): void
    {
        $resolver = new EntitlementResolver(
            Mockery::mock(ModuleLicenseService::class),
            new SiteContext(),
        );

        $this->expectException(LogicException::class);
        $resolver->resolveForCurrentSite(new Module());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
