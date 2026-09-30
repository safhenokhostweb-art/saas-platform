<?php
namespace Tests\Feature\Seo;

use Tests\TestCase;
use App\Models\User;
use App\Services\Seo\SeoService;
use SaaSPlatform\CoreUtils\SEO\MetaContext;
use SaaSPlatform\CoreUtils\SEO\MetaData;

class MetaEngineIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function createAdminUser(): User
    {
        return User::factory()->create([
            'is_admin' => true,
        ]);
    }

    /** @test */
    public function meta_engine_resolves_correctly_for_platform_admin_context(): void
    {
        $seoService = app(SeoService::class);
        $metaData = $seoService->resolveForPlatformAdmin();

        $this->assertInstanceOf(MetaData::class, $metaData);
        $this->assertEquals('noindex, nofollow', $metaData->robots);
        $this->assertEquals('fa', $metaData->language);
        $this->assertEquals('rtl', $metaData->direction);
    }

    /** @test */
    public function meta_engine_resolves_correctly_for_tenant_public_context(): void
    {
        $seoService = app(SeoService::class);
        $metaData = $seoService->resolveForTenantPublic();

        $this->assertInstanceOf(MetaData::class, $metaData);
        $this->assertEquals('index, follow', $metaData->robots);
        $this->assertEquals('fa', $metaData->language);
        $this->assertEquals('rtl', $metaData->direction);
    }

    /** @test */
    public function meta_engine_resolves_correctly_for_preview_context(): void
    {
        $seoService = app(SeoService::class);
        $metaData = $seoService->resolveForPreview();

        $this->assertInstanceOf(MetaData::class, $metaData);
        $this->assertEquals('noindex, nofollow', $metaData->robots);
        $this->assertEquals('fa', $metaData->language);
        $this->assertEquals('rtl', $metaData->direction);
    }

    /** @test */
    public function meta_engine_resolves_correctly_for_canvas_public_context(): void
    {
        $seoService = app(SeoService::class);
        $metaData = $seoService->resolveForCanvasPublic();

        $this->assertInstanceOf(MetaData::class, $metaData);
        $this->assertEquals('index, follow', $metaData->robots);
        $this->assertEquals('fa', $metaData->language);
        $this->assertEquals('rtl', $metaData->direction);
    }

    /** @test */
    public function meta_engine_resolves_with_overrides(): void
    {
        $seoService = app(SeoService::class);
        $metaData = $seoService->resolveForTenantPublic(
            source: ['title' => 'Custom Title', 'meta_description' => 'Custom Description'],
            overrides: ['title' => 'Override Title', 'description' => 'Override Description']
        );

        $this->assertEquals('Override Title', $metaData->title);
        $this->assertEquals('Override Description', $metaData->description);
    }

    /** @test */
    public function seo_service_resolves_overrides_from_provider(): void
    {
        $seoService = app(SeoService::class);
        $overrideProvider = app(\SaaSPlatform\CoreUtils\Contracts\Services\SeoOverrideProviderInterface::class);

        // Null provider should return null overrides
        $this->assertNull($overrideProvider->overrideFor());

        $metaData = $seoService->resolveForTenantPublic();

        $this->assertInstanceOf(MetaData::class, $metaData);
        $this->assertNotEmpty($metaData->title);
    }

    /** @test */
    public function meta_data_to_array_returns_all_fields(): void
    {
        $metaData = new MetaData(
            title: 'Test Title',
            description: 'Test Description',
            canonical: 'https://example.com/test',
            language: 'fa',
            direction: 'rtl',
            robots: 'index, follow',
            og_image: 'https://example.com/image.jpg',
            og_type: 'article',
            twitter_card: 'summary_large_image',
        );

        $array = $metaData->toArray();

        $this->assertEquals('Test Title', $array['title']);
        $this->assertEquals('Test Description', $array['description']);
        $this->assertEquals('https://example.com/test', $array['canonical']);
        $this->assertEquals('fa', $array['language']);
        $this->assertEquals('rtl', $array['direction']);
        $this->assertEquals('index, follow', $array['robots']);
        $this->assertEquals('https://example.com/image.jpg', $array['og_image']);
        $this->assertEquals('article', $array['og_type']);
        $this->assertEquals('summary_large_image', $array['twitter_card']);
    }

    /** @test */
    public function meta_context_default_robots(): void
    {
        // Admin contexts should be noindex
        $this->assertEquals('noindex, nofollow', MetaContext::PLATFORM_ADMIN->defaultRobots());
        $this->assertEquals('noindex, nofollow', MetaContext::TENANT_ADMIN->defaultRobots());
        $this->assertEquals('noindex, nofollow', MetaContext::PREVIEW_EDITOR->defaultRobots());
        $this->assertEquals('noindex, nofollow', MetaContext::AUTH_INSTALLED->defaultRobots());

        // Public contexts should be index, follow
        $this->assertEquals('index, follow', MetaContext::PLATFORM_PUBLIC->defaultRobots());
        $this->assertEquals('index, follow', MetaContext::TENANT_PUBLIC->defaultRobots());
        $this->assertEquals('index, follow', MetaContext::CANVAS_PUBLIC->defaultRobots());
    }

    /** @test */
    public function meta_context_is_platform_and_tenant(): void
    {
        $this->assertTrue(MetaContext::PLATFORM_ADMIN->isPlatform());
        $this->assertTrue(MetaContext::PLATFORM_PUBLIC->isPlatform());
        $this->assertFalse(MetaContext::TENANT_ADMIN->isPlatform());
        $this->assertFalse(MetaContext::TENANT_PUBLIC->isPlatform());

        $this->assertFalse(MetaContext::PLATFORM_ADMIN->isTenant());
        $this->assertFalse(MetaContext::PLATFORM_PUBLIC->isTenant());
        $this->assertTrue(MetaContext::TENANT_ADMIN->isTenant());
        $this->assertTrue(MetaContext::TENANT_PUBLIC->isTenant());
        $this->assertTrue(MetaContext::CANVAS_PUBLIC->isTenant());
    }
}