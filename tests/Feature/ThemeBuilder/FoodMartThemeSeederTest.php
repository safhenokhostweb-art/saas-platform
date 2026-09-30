<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Services\ThemeBuilder\BlockRegistry;
use App\Services\ThemeBuilder\BlockRenderer;
use App\Services\ThemeBuilder\FoodMart\FoodMartBlockRenderer;
use Database\Seeders\FoodMartThemeSeeder;
use ReflectionClass;
use SaaSPlatform\ThemeRuntime\FoodMartBlockTypes;
use Tests\TestCase;

/**
 * Phase 1 skeleton verification ONLY. Deliberately does NOT use
 * RefreshDatabase, does NOT call $this->seed(FoodMartThemeSeeder::class),
 * and never instantiates/executes FoodMartThemeSeeder::run() — Phase 1
 * makes zero database writes of any kind. Every assertion here inspects
 * either the seeder's pure identity() metadata, its own source file, or
 * other static/reflective repository state.
 */
class FoodMartThemeSeederTest extends TestCase
{
    public function test_foodmart_seeder_class_exists_and_extends_seeder(): void
    {
        self::assertTrue(class_exists(FoodMartThemeSeeder::class));
        self::assertTrue(is_subclass_of(FoodMartThemeSeeder::class, \Illuminate\Database\Seeder::class));
    }

    public function test_expected_foodmart_identity_is_defined(): void
    {
        $identity = FoodMartThemeSeeder::identity();

        self::assertSame('FoodMart', $identity['name']);
        self::assertSame('foodmart', $identity['slug']);
        self::assertSame(Theme::CATEGORY_STORE, $identity['category']);
        self::assertSame(Theme::TYPE_FULL, $identity['type']);
        self::assertSame(Theme::SOURCE_SEEDER, $identity['source']);
    }

    public function test_theme_slug_is_foodmart(): void
    {
        self::assertSame('foodmart', FoodMartThemeSeeder::SLUG);
        self::assertSame('foodmart', FoodMartThemeSeeder::identity()['slug']);
    }

    public function test_it_is_intended_as_a_shared_platform_theme_not_site_scoped(): void
    {
        // site_id === null is the Theme model's own convention for "shared
        // platform template" (Theme::scopeTemplates()); a non-null site_id
        // would make it a tenant-specific clone, which FoodMart must not be.
        self::assertNull(FoodMartThemeSeeder::identity()['site_id']);
    }

    /**
     * The seeder's own executable source with all comments/docblocks
     * stripped. The class's docblocks intentionally NAME the lifecycle
     * services it deliberately does NOT call (for documentation purposes,
     * matching this repo's existing seeder-docblock style) — a raw
     * substring search would false-positive on that prose. Stripping
     * comments first means these assertions only ever see real code, so
     * they verify actual absence of use, not merely absence of the word.
     */
    private function seederExecutableSource(): string
    {
        $path = (new ReflectionClass(FoodMartThemeSeeder::class))->getFileName();
        self::assertNotFalse($path, 'could not locate FoodMartThemeSeeder source file');

        $code = '';
        foreach (token_get_all(file_get_contents($path)) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }

    public function test_phase1_seeder_does_not_publish_the_theme(): void
    {
        $source = $this->seederExecutableSource();

        self::assertStringNotContainsString('ThemeBuilderService', $source);
        self::assertStringNotContainsString('ThemeReleaseService', $source);
        self::assertStringNotContainsString('ThemeCommercialQualityGate', $source);
        self::assertStringNotContainsString('->publish(', $source);
        self::assertStringNotContainsString('STATUS_PUBLISHED', $source, 'Phase 1 must never set status to published');
    }

    public function test_phase1_seeder_does_not_assign_the_theme_to_a_site(): void
    {
        self::assertStringNotContainsString('PublishedThemeAssignmentService', $this->seederExecutableSource());
    }

    public function test_phase1_seeder_does_not_install_the_theme_to_customer_site(): void
    {
        $source = $this->seederExecutableSource();

        self::assertStringNotContainsString('ThemeInstallGateway', $source);
        self::assertStringNotContainsString('ThemeInstanceRevision', $source);
    }

    public function test_golden_reference_is_not_used_as_a_runtime_dependency(): void
    {
        $source = $this->seederExecutableSource();

        self::assertStringNotContainsString('_reference-themes', $source);
        self::assertStringNotContainsString('FoodMart-main', $source);
    }

    public function test_no_marketplace_listing_is_created_in_phase1(): void
    {
        self::assertStringNotContainsString('ThemeMarketplace', $this->seederExecutableSource());
    }

    /**
     * Phase 1 guarded that NO foodmart_* registry type existed yet. Phase 3
     * (user-approved shared theme-runtime change) moved the canonical
     * foodmart_* definitions into SaaSPlatform\ThemeRuntime\FoodMartBlockTypes
     * and registers them from the package's own service provider through the
     * generic registerHostType() seam — both hosts consume them with no
     * FoodMart detection in either host's generic code. This test now asserts
     * that approved architecture: types are HOST-registered (never canonical)
     * and come from the shared package definitions, not a local re-declaration.
     */
    public function test_foodmart_block_types_are_host_registered_from_shared_package(): void
    {
        foreach (FoodMartBlockTypes::TYPES as $type => $definition) {
            self::assertArrayHasKey($type, BlockRegistry::all(), "{$type} must be registered by the shared package provider");
            self::assertTrue(BlockRegistry::isHostRegisteredType($type), "{$type} must be host-registered, not canonical");
            self::assertFalse(BlockRegistry::isCanonicalType($type), "{$type} must not shadow a canonical type");
            self::assertSame($definition, BlockRegistry::all()[$type], "{$type} must match the shared package definition verbatim");
        }
    }

    /**
     * Phase 1 guarded that BlockRenderer had no renderFoodmart*() methods yet.
     * Phase 3 (user-approved shared theme-runtime change) moved the canonical
     * renderFoodmart*() markup into the shared package
     * (SaaSPlatform\ThemeRuntime\FoodMartBlockRendererTrait, consumed by the
     * shared base BlockRenderer), so saas Builder preview AND customer-site
     * tenant runtime render from ONE implementation. This test now asserts
     * that approved architecture: the methods exist ONLY via the shared
     * trait — the declaring class is the shared trait itself, and the
     * saas-local FoodMart renderer subclass adds no FoodMart markup of its own.
     */
    public function test_foodmart_renderer_methods_are_canonical_in_shared_package(): void
    {
        $methods = (new ReflectionClass(BlockRenderer::class))->getMethods();

        $foodmartMethods = array_values(array_filter(
            $methods,
            fn (\ReflectionMethod $m) => stripos($m->getName(), 'foodmart') !== false
        ));

        self::assertNotEmpty($foodmartMethods, 'canonical shared renderFoodmart*() methods must exist');

        // Reflection reports a trait method's declaring class as the class that
        // USES the trait — the trait is used by the SHARED base
        // SaaSPlatform\ThemeRuntime\BlockRenderer, and the saas App subclass
        // inherits it. Assert the shared base uses the canonical trait and
        // every foodmart method resolves to the shared base (i.e. the
        // saas-side subclass adds no FoodMart markup of its own).
        self::assertContains(
            'SaaSPlatform\\ThemeRuntime\\FoodMartBlockRendererTrait',
            (new ReflectionClass(\SaaSPlatform\ThemeRuntime\BlockRenderer::class))->getTraitNames(),
            'the shared base BlockRenderer must use the canonical FoodMartBlockRendererTrait'
        );
        foreach ($foodmartMethods as $method) {
            self::assertSame(
                'SaaSPlatform-ThemeRuntime-BlockRenderer',
                str_replace('\\', '-', $method->getDeclaringClass()->getName()),
                "{$method->getName()} must come from the shared base BlockRenderer (via the canonical trait), not a saas-local class"
            );
        }

        // The saas-local compatibility subclass must add NO FoodMart markup of its own.
        $saasLocalMethods = (new ReflectionClass(FoodMartBlockRenderer::class))->getMethods();
        $declaredLocally = array_filter(
            $saasLocalMethods,
            fn (\ReflectionMethod $m) => stripos($m->getName(), 'foodmart') !== false
                && $m->getDeclaringClass()->getName() === FoodMartBlockRenderer::class
        );
        self::assertSame([], array_keys($declaredLocally), 'saas-local FoodMart renderer must stay a thin subclass with zero markup');
    }

    public function test_no_foodmart_css_skin_exists_yet(): void
    {
        $skinPath = base_path('packages/saas-platform/modules/theme-runtime/resources/css/skins/foodmart.css');

        self::assertFileDoesNotExist($skinPath);
    }
}
