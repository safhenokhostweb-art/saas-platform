<?php

namespace Tests\Unit\Services;

use App\Services\SiteContext;
use LogicException;
use PHPUnit\Framework\TestCase;

class SiteContextTest extends TestCase
{
    public function test_context_has_no_implicit_platform_site(): void
    {
        $context = new SiteContext();

        self::assertFalse($context->hasSite());
        self::assertNull($context->siteId());
        self::assertNull($context->snapshot());
    }

    public function test_context_requires_and_clears_an_explicit_site_identity(): void
    {
        $context = new SiteContext();
        $context->setSiteId(42);

        self::assertTrue($context->hasSite());
        self::assertSame(42, $context->requireSiteId());
        self::assertSame(['site_id' => 42], $context->snapshot());

        $context->clear();
        self::assertFalse($context->hasSite());
        $this->expectException(LogicException::class);
        $context->requireSiteId();
    }
}
