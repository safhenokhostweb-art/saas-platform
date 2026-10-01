<?php

namespace Tests\Feature\ThemeCanvas;

use App\Services\ThemeBuilder\ThemeBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\ThemeRuntime\DefaultHomepage;
use Tests\TestCase;

class DefaultHomepagePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_template_registers_complete_homepage_and_preserves_edits_on_reload(): void
    {
        $service = app(ThemeBuilderService::class);
        $theme = $service->createTemplate(['name' => 'Default homepage', 'slug' => 'default-homepage-test']);
        $document = DefaultHomepage::document();
        self::assertEquals($document['pages'], $theme->fresh()->pages);
        self::assertEquals($document['header_blocks'], $theme->fresh()->header_blocks);
        self::assertEquals($document['footer_blocks'], $theme->fresh()->footer_blocks);
        $blocks = $theme->getPageBlocks('home');
        $blocks[0]['props']['title'] = 'عنوان ذخیره‌شده';
        $theme->setPageBlocks('home', $blocks);
        self::assertEquals($blocks, $theme->fresh()->getPageBlocks('home'));
        self::assertSame(array_column($blocks, 'id'), array_column($theme->fresh()->getPageBlocks('home'), 'id'));
        self::assertEquals($document['header_blocks'], $theme->fresh()->header_blocks);
    }

    public function test_intentionally_empty_document_is_not_rebuilt(): void
    {
        $theme = app(ThemeBuilderService::class)->createTemplate([
            'name' => 'Blank', 'slug' => 'blank-homepage-test', 'pages' => [],
        ]);
        self::assertSame([], $theme->fresh()->pages);
        self::assertSame([], $theme->fresh()->header_blocks);
        self::assertSame([], $theme->fresh()->footer_blocks);
    }
}
