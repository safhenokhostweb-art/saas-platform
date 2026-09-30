<?php

namespace Tests\Unit\Architecture;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\BlockRenderer;
use SaaSPlatform\ThemeRuntime\DataBinding\DataFetcher;
use SaaSPlatform\ThemeRuntime\DataBinding\DataFetchResult;
use SaaSPlatform\ThemeRuntime\DataBinding\DataSourceConfig;
use SaaSPlatform\ThemeRuntime\DynamicBlockTypes;
use SaaSPlatform\ThemeRuntime\Inspector\InspectorFieldRegistry;
use SaaSPlatform\ThemeRuntime\Widget\WidgetConfig;

/**
 * Phase 3C / Milestone 2 — dynamic widget and data-binding contracts.
 */
class Phase3CDynamicContractsTest extends TestCase
{
    private const TYPES = ['dynamic_product_grid', 'dynamic_feed_slider'];

    protected function setUp(): void
    {
        parent::setUp();

        BlockRegistry::resetHostTypes();
        DynamicBlockTypes::register();
    }

    protected function tearDown(): void
    {
        BlockRegistry::resetHostTypes();

        parent::tearDown();
    }

    /** @test */
    public function dynamic_widgets_register_through_the_additive_host_extension_contract(): void
    {
        foreach (self::TYPES as $type) {
            $definition = BlockRegistry::all()[$type] ?? null;

            $this->assertIsArray($definition, "{$type} must be registered.");
            $this->assertTrue(BlockRegistry::isHostRegisteredType($type));
            $this->assertFalse(BlockRegistry::isCanonicalType($type));

            $normalized = WidgetConfig::normalize($type, $definition);
            $this->assertSame($definition, $normalized, 'WidgetConfig normalization must be lossless.');
            $this->assertSame('data_source', $normalized['data_source']['property']);
            $this->assertContains('static', $normalized['data_source']['modes']);
        }

        $this->assertSame(
            ['static', 'internal'],
            BlockRegistry::all()['dynamic_product_grid']['data_source']['modes']
        );
        $this->assertSame(
            ['static', 'api'],
            BlockRegistry::all()['dynamic_feed_slider']['data_source']['modes']
        );
    }

    /** @test */
    public function data_binding_inspector_fields_use_only_existing_primitive_controls(): void
    {
        foreach (self::TYPES as $type) {
            $schema = BlockRegistry::all()[$type]['schema'];
            InspectorFieldRegistry::normalizeDefinition(['schema' => $schema]);

            $this->assertSame('select', $schema['source_type']['type']);
            $this->assertSame(['static', 'api', 'internal'], array_keys($schema['source_type']['options']));
            $this->assertSame('string', $schema['endpoint_url']['type']);
            $this->assertSame('textarea', $schema['query_params']['type']);

            foreach (['source_type', 'endpoint_url', 'query_params'] as $property) {
                foreach (['rtl', 'ltr'] as $direction) {
                    $default = BlockRegistry::all()[$type]['defaultProps'][$property];
                    $this->assertSame(
                        $default,
                        InspectorFieldRegistry::normalizeValue(
                            $schema[$property],
                            $default,
                            ['key' => "{$type}.{$property}", 'direction' => $direction]
                        )
                    );
                }
            }
        }
    }

    /** @test */
    public function data_source_configuration_is_normalized_and_fail_closed(): void
    {
        $api = DataSourceConfig::fromArray([
            'source_type' => 'api',
            'endpoint_url' => 'https://feeds.example.test/posts',
            'query_params' => '{"limit":6,"tags":["news","fa"]}',
        ]);

        $this->assertSame('api_endpoint', $api->mode);
        $this->assertSame('https://feeds.example.test/posts', $api->endpointUrl);
        $this->assertSame(['limit' => 6, 'tags' => ['news', 'fa']], $api->queryParams);
        $this->assertSame($api->toArray(), DataSourceConfig::fromArray($api->toArray())->toArray());

        $internal = DataSourceConfig::fromArray([
            'source_type' => 'internal',
            'endpoint_url' => 'products',
            'query_params' => '{"category_id":12,"collection_slug":"new-arrivals"}',
        ]);
        $this->assertSame('internal_query', $internal->mode);
        $this->assertSame('products', $internal->endpointUrl);
        $this->assertSame([], DataSourceConfig::fromArray([
            'source_type' => 'static', 'endpoint_url' => '', 'query_params' => '{}',
        ])->queryParams);

        foreach ([
            ['source_type' => 'api', 'endpoint_url' => 'javascript:alert(1)', 'query_params' => '{}'],
            ['source_type' => 'api', 'endpoint_url' => 'http://127.0.0.1/secrets', 'query_params' => '{}'],
            ['source_type' => 'internal', 'endpoint_url' => '../admin/users', 'query_params' => '{}'],
            ['source_type' => 'internal', 'endpoint_url' => 'products', 'query_params' => '{"__proto__":"polluted"}'],
            ['source_type' => 'internal', 'endpoint_url' => 'products', 'query_params' => '<script>alert(1)</script>'],
        ] as $unsafe) {
            try {
                DataSourceConfig::fromArray($unsafe);
                $this->fail('Unsafe data-source input must be rejected.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** @test */
    public function data_fetcher_exposes_loading_empty_error_and_success_without_leaking_exceptions(): void
    {
        $config = [
            'source_type' => 'internal',
            'endpoint_url' => 'products',
            'query_params' => '{"category_id":12}',
        ];

        $this->assertSame(DataFetchResult::LOADING, (new DataFetcher)->fetch($config, 'grid')->status);

        $empty = new DataFetcher(
            internalResolver: static fn (DataSourceConfig $source): array => []
        );
        $this->assertSame(DataFetchResult::EMPTY, $empty->fetch($config, 'grid')->status);

        $failing = new DataFetcher(
            internalResolver: static fn (DataSourceConfig $source): never => throw new RuntimeException('database password')
        );
        $failure = $failing->fetch($config, 'grid');
        $this->assertSame(DataFetchResult::ERROR, $failure->status);
        $this->assertStringNotContainsString('database password', $failure->message);

        $success = new DataFetcher(
            internalResolver: static fn (DataSourceConfig $source): array => [
                ['title' => 'محصول پویا', 'url' => '/products/dynamic', 'price' => '120,000'],
            ]
        );
        $result = $success->fetch($config, 'grid');
        $this->assertSame(DataFetchResult::SUCCESS, $result->status);
        $this->assertSame('محصول پویا', $result->items[0]['title']);
    }

    /** @test */
    public function renderer_handles_dynamic_states_and_escapes_resolved_content(): void
    {
        $block = [
            'id' => 'dynamic-products',
            'type' => 'dynamic_product_grid',
            'props' => [
                'title' => 'محصولات',
                'source_type' => 'internal',
                'endpoint_url' => 'products',
                'query_params' => '{"category_id":12}',
            ],
        ];

        $loading = (new BlockRenderer)->renderBlock($block);
        $this->assertStringContainsString('data-state="loading"', $loading);
        $this->assertStringContainsString('tb-dynamic-state-loading', $loading);

        $emptyRenderer = new BlockRenderer;
        $emptyRenderer->setContext([
            'data_fetcher' => new DataFetcher(internalResolver: static fn (): array => []),
        ]);
        $this->assertStringContainsString('data-state="empty"', $emptyRenderer->renderBlock($block));

        $errorBlock = $block;
        $errorBlock['props']['endpoint_url'] = 'javascript:alert(1)';
        $error = (new BlockRenderer)->renderBlock($errorBlock);
        $this->assertStringContainsString('data-state="error"', $error);
        $this->assertStringNotContainsString('javascript:', $error);

        $successRenderer = new BlockRenderer;
        $successRenderer->setContext([
            'data_fetcher' => new DataFetcher(internalResolver: static fn (): array => [[
                'title' => '<img src=x onerror=alert(1)>Safe',
                'url' => 'javascript:alert(1)',
                'price' => '<script>alert(1)</script>',
            ]]),
        ]);
        $success = $successRenderer->renderBlock($block);
        $this->assertStringContainsString('data-state="success"', $success);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;Safe', $success);
        $this->assertStringNotContainsString('<script>', $success);
        $this->assertStringNotContainsString('href="javascript:', $success);
    }

    /** @test */
    public function milestone_two_css_is_scoped_tokenized_and_logical(): void
    {
        $css = file_get_contents(
            dirname(__DIR__, 3).'/packages/saas-platform/modules/theme-runtime/resources/css/theme-runtime-contract.css'
        );
        $this->assertNotFalse($css);
        $marker = strpos($css, 'Phase 3C / Milestone 2');
        $this->assertNotFalse($marker, 'Milestone 2 CSS scope marker must exist.');
        $scope = substr($css, $marker);

        $this->assertStringContainsString('.tb-dynamic-', $scope);
        $this->assertStringContainsString('--tb-', $scope);
        $this->assertStringContainsString('padding-inline', $scope);
        $this->assertStringContainsString('inset-inline-', $scope);
        $this->assertDoesNotMatchRegularExpression(
            '/(?:margin|padding|border|inset)-(?:left|right)\s*:/',
            $scope,
            'Dynamic widgets must use logical geometry.'
        );
    }
}
