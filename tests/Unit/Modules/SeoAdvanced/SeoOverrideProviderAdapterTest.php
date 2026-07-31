<?php

namespace Tests\Unit\Modules\SeoAdvanced;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\Modules\SeoAdvanced\Adapters\SeoOverrideProviderAdapter;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\SchemaBuilderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedSetting;
use SaaSPlatform\Modules\SeoAdvanced\Services\MetaBuilder;
use SaaSPlatform\Modules\SeoAdvanced\Services\SchemaBuilder;
use Tests\TestCase;

/**
 * Phase 18-B — Part 4/5: SeoOverrideProviderAdapter scenarios — the module
 * side of the generic SeoOverrideProviderInterface extension point.
 */
class SeoOverrideProviderAdapterTest extends TestCase
{
    use RefreshDatabase;

    private const SITE_ID = 1; // matches SeoOverrideProviderAdapter::SINGLE_TENANT_SITE_ID

    private function adapter(): SeoOverrideProviderAdapter
    {
        return new SeoOverrideProviderAdapter(new SchemaBuilder(), new MetaBuilder());
    }

    public function test_override_for_returns_null_when_no_settings_row_exists(): void
    {
        $this->assertNull($this->adapter()->overrideFor());
        $this->assertEmpty($this->adapter()->extraJsonLd());
    }

    public function test_override_for_returns_configured_fields(): void
    {
        SeoAdvancedSetting::create([
            'site_id' => self::SITE_ID, 'meta_title' => 'عنوان تست', 'meta_description' => 'توضیح تست',
            'robots_directive' => 'noindex,nofollow', 'canonical_url' => 'https://example.com/x',
            'canonical_strategy' => SeoAdvancedSetting::CANONICAL_CUSTOM,
            'og_image_url' => 'https://example.com/img.png',
        ]);

        $override = $this->adapter()->overrideFor();

        $this->assertSame('عنوان تست', $override['title']);
        $this->assertSame('توضیح تست', $override['description']);
        $this->assertSame('noindex,nofollow', $override['robots']);
        $this->assertSame('https://example.com/x', $override['canonical']);
        $this->assertSame('https://example.com/img.png', $override['ogImage']);
    }

    public function test_extra_json_ld_is_empty_unless_schema_type_is_local_business(): void
    {
        SeoAdvancedSetting::create(['site_id' => self::SITE_ID, 'schema_type' => SeoAdvancedSetting::SCHEMA_WEBSITE]);

        $this->assertEmpty($this->adapter()->extraJsonLd());
    }

    public function test_extra_json_ld_contributes_local_business_when_configured(): void
    {
        SeoAdvancedSetting::create([
            'site_id' => self::SITE_ID, 'schema_type' => SeoAdvancedSetting::SCHEMA_LOCAL_BUSINESS,
            'organization_name' => 'فروشگاه تست', 'business_address' => 'تهران',
        ]);

        $extra = $this->adapter()->extraJsonLd();

        $this->assertCount(1, $extra);
        $this->assertSame('LocalBusiness', $extra[0]['@type']);
        $this->assertSame('تهران', $extra[0]['address']);
    }

    public function test_adapter_implements_the_shared_core_utils_contract(): void
    {
        $this->assertInstanceOf(
            \SaaSPlatform\CoreUtils\Contracts\Services\SeoOverrideProviderInterface::class,
            $this->adapter()
        );
    }
}
