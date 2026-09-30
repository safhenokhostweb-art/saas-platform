<?php

namespace Tests\Feature\Seo;

use App\Models\Page;
use App\Models\Role;
use App\Models\Theme;
use App\Models\User;
use App\Services\Seo\SeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use SaaSPlatform\CoreUtils\Contracts\Services\SeoOverrideProviderInterface;
use SaaSPlatform\CoreUtils\SEO\MetaContext;
use SaaSPlatform\Modules\SeoAdvanced\Adapters\SeoOverrideProviderAdapter;
use Tests\TestCase;

/**
 * End-to-end verification of the MetaEngine head integration, asserted on
 * RENDERED HTML rather than on a MetaData object, because the failure mode
 * being guarded against is a tag that resolution got right but the Blade tree
 * then dropped, duplicated, or wrote with the wrong context.
 *
 * Every context under test is exercised through its real route and its real
 * layout:
 *   - Platform admin  → layouts.app  (the layout's own default context)
 *   - Tenant public   → layouts.app  via the public `page/{slug}` route
 *   - Preview editor  → the standalone canvas-preview document
 *   - Canvas public   → MetaEngine resolution (the public canvas render lives
 *                       in customer-site, not this app; see the class note on
 *                       test_canvas_public_context_resolves_an_indexable_directive)
 */
class MetaEngineRenderedHeadTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::create([
            'mobile' => '0912' . random_int(1000000, 9999999),
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_active' => true,
        ]);

        $role = Role::where('slug', Role::SUPER_ADMIN)->first();
        if ($role) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function theme(): Theme
    {
        return Theme::create([
            'name' => 'قالب تست متا',
            'slug' => 'meta-engine-render-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'pages' => ['home' => [
                'title' => 'صفحهٔ کانواس',
                'blocks' => [['id' => 'blk_1', 'type' => 'hero', 'props' => ['title' => 'سلام']]],
            ]],
            'header_blocks' => [],
            'footer_blocks' => [],
            'global_styles' => [],
            'settings' => ['extension_selections' => []],
        ]);
    }

    private function page(array $attributes = []): Page
    {
        // `Page` has no meta_title column: its SEO title is the page title
        // itself, which is what MetaEngine reads off the source.
        return Page::create(array_merge([
            'title' => 'دربارهٔ ما',
            'slug' => 'about-us',
            'meta_description' => 'متن یکتای صفحهٔ دربارهٔ ما برای موتور جست‌وجو.',
            'content' => ['blocks' => []],
            'is_active' => true,
        ], $attributes));
    }

    /** @return array{title:int, description:int, canonical:int, robots:int} */
    private function countSeoTags(string $html): array
    {
        return [
            'title' => preg_match_all('/<title\b/i', $html),
            'description' => preg_match_all('/<meta\s+name="description"/i', $html),
            'canonical' => preg_match_all('/<link\s+rel="canonical"/i', $html),
            'robots' => preg_match_all('/<meta\s+name="robots"/i', $html),
        ];
    }

    private function assertOneOfEachSeoTag(string $html, string $surface): void
    {
        $counts = $this->countSeoTags($html);

        $this->assertSame(1, $counts['title'], $surface . ' must render exactly one <title>');
        $this->assertSame(1, $counts['description'], $surface . ' must render exactly one <meta name="description">');
        $this->assertSame(1, $counts['canonical'], $surface . ' must render exactly one <link rel="canonical">');
        $this->assertSame(1, $counts['robots'], $surface . ' must render exactly one <meta name="robots">');
    }

    public function test_the_tenant_public_page_renders_each_head_tag_exactly_once(): void
    {
        $this->page();

        $response = $this->get('/page/about-us');

        $response->assertOk();
        $this->assertOneOfEachSeoTag($response->getContent(), 'page/{slug}');
    }

    public function test_the_tenant_public_page_renders_its_own_page_level_overrides(): void
    {
        $this->page();

        $response = $this->get('/page/about-us');

        $response->assertOk();
        $response->assertSee('<title>دربارهٔ ما</title>', false);
        $response->assertSee('name="description" content="متن یکتای صفحهٔ دربارهٔ ما برای موتور جست‌وجو."', false);
        $response->assertSee('property="og:type" content="article"', false);
    }

    public function test_the_tenant_public_page_is_indexable(): void
    {
        $this->page();

        $response = $this->get('/page/about-us');

        $response->assertOk();
        $response->assertSee('<meta name="robots" content="index, follow">', false);
        $this->assertStringNotContainsString(
            'name="robots" content="noindex, nofollow"',
            $response->getContent()
        );
    }

    public function test_an_explicit_page_robots_override_is_honoured(): void
    {
        // `Page` has no robots column, so the only way a single page can opt out
        // of its context default today is a resolved MetaData handed to the
        // layout. The real view and the real layout are rendered with one, and
        // the assertion is on the emitted HTML.
        $this->page();
        $page = Page::where('slug', 'about-us')->firstOrFail();

        $metaData = app(SeoService::class)->resolveForTenantPublic(
            $page,
            ['robots' => 'noindex, follow'],
        );

        // `layouts.app` reads the shared $errors bag, which only the HTTP
        // kernel normally populates; rendering the view directly does not.
        \Illuminate\Support\Facades\View::share('errors', new \Illuminate\Support\ViewErrorBag());

        $html = view('page.show', ['page' => $page, 'metaData' => $metaData])->render();

        $this->assertOneOfEachSeoTag($html, 'page.show with a robots override');
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html);
        $this->assertStringNotContainsString('content="index, follow"', $html);
    }

    public function test_a_platform_admin_surface_is_not_indexable_and_renders_one_of_each_tag(): void
    {
        $response = $this->actingAs($this->superAdmin())
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $this->assertOneOfEachSeoTag($response->getContent(), 'platform admin');
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_the_preview_surface_is_not_indexable_and_renders_one_of_each_tag(): void
    {
        $theme = $this->theme();

        $response = $this->actingAs($this->superAdmin())
            ->get(route('admin.theme-builder.canvas.page-preview', [$theme, 'home']));

        $response->assertOk();
        $this->assertOneOfEachSeoTag($response->getContent(), 'canvas preview');
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_the_preview_surface_renders_the_previewed_page_title(): void
    {
        $theme = $this->theme();

        $response = $this->actingAs($this->superAdmin())
            ->get(route('admin.theme-builder.canvas.page-preview', [$theme, 'home']));

        $response->assertOk();
        $response->assertSee('<title>صفحهٔ کانواس</title>', false);
    }

    public function test_a_platform_admin_screen_keeps_its_own_title_through_the_resolver(): void
    {
        // layouts.admin resolves PLATFORM_ADMIN, and the screen's `@section('title')`
        // is fed in as the title override, so wiring the layout to MetaEngine must
        // not flatten every admin screen onto one generic title.
        $response = $this->actingAs($this->superAdmin())
            ->get(route('admin.design.icons'));

        $response->assertOk();
        $this->assertOneOfEachSeoTag($response->getContent(), 'platform admin (design icons)');
        $response->assertSee('<title>کتابخانه آیکن‌ها</title>', false);
    }

    public function test_the_auth_installed_layout_is_not_indexable_and_renders_one_of_each_tag(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $this->assertOneOfEachSeoTag($response->getContent(), 'auth (login)');
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_canvas_public_context_resolves_an_indexable_directive(): void
    {
        // The public canvas render is customer-site's surface; this app only
        // owns the context definition, so the assertion is on the resolution
        // the shared component is fed with. See the class note.
        $this->assertSame('index, follow', MetaContext::CANVAS_PUBLIC->defaultRobots());

        $metaData = app(SeoService::class)->resolveForCanvasPublic(['title' => 'کانواس عمومی']);

        $this->assertSame('index, follow', $metaData->robots);
    }
    public function test_the_host_binds_the_null_object_when_no_seo_module_contributes_an_adapter(): void
    {
        $contract = SeoOverrideProviderInterface::class;

        // The contract must always resolve to SOMETHING: either the host's Null
        // Object or an installed module's adapter. Which one is bound depends on
        // whether the module package is installed in this environment; the two
        // assertions below cover each case.
        $this->assertTrue(
            app()->bound($contract),
            'SeoOverrideProviderInterface must always be bound'
        );

        $this->assertContains(get_class(app($contract)), [
            \App\Services\Seo\NullSeoOverrideProvider::class,
            SeoOverrideProviderAdapter::class,
        ]);
    }

    public function test_seo_advanced_rebinds_the_override_contract_when_the_module_is_installed(): void
    {
        $contract = SeoOverrideProviderInterface::class;

        // Regression: AppServiceProvider used a plain bind() for the Null Object
        // default. Laravel splices package-discovered providers at index 1 of the
        // provider list, i.e. BEFORE the app's own providers, so that bind() ran
        // last and overwrote SEO Advanced's adapter every time — the module was
        // installed, merged its config, registered its adapter, and none of it
        // ever reached a rendered page.
        $this->assertInstanceOf(
            SeoOverrideProviderAdapter::class,
            app($contract),
            'An installed SEO Advanced package must win the contract; the host '
            . 'default must only fill in when nothing else has bound it.'
        );
    }

    public function test_an_seo_advanced_override_reaches_the_rendered_output_when_the_adapter_is_bound(): void
    {
        if (! app(SeoOverrideProviderInterface::class) instanceof SeoOverrideProviderAdapter) {
            $this->markTestSkipped('SEO Advanced is not bound in this environment.');
        }

        // Rebind to a stub adapter so the assertion is about the wiring
        // (provider → SeoService → MetaEngine → rendered head), not about
        // whatever settings row the module happens to have.
        app()->bind(
            SeoOverrideProviderInterface::class,
            fn () => new class implements SeoOverrideProviderInterface {
                public function overrideFor(): ?array
                {
                    return [
                        'title' => 'عنوان پیش‌فرض سایت از ماژول سئو',
                        'description' => 'توضیح پیش‌فرض سایت از ماژول سئو.',
                        'robots' => 'noindex, follow',
                    ];
                }

                public function extraJsonLd(): array
                {
                    return [];
                }
            }
        );

        // A page with no description and no robots of its own, so the only
        // source of a value for those two fields is the provider.
        $this->page(['meta_description' => null]);

        $response = $this->get('/page/about-us');

        $response->assertOk();
        $this->assertOneOfEachSeoTag($response->getContent(), 'page/{slug} with a provider default');
        $response->assertSee('name="description" content="توضیح پیش‌فرض سایت از ماژول سئو."', false);
        $response->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_the_real_seo_advanced_adapter_drives_the_rendered_public_page(): void
    {
        if (! app(SeoOverrideProviderInterface::class) instanceof SeoOverrideProviderAdapter) {
            $this->markTestSkipped('SEO Advanced is not bound in this environment.');
        }

        // The module's own settings row, read by the module's own MetaBuilder
        // through its own adapter — no stubs anywhere in the chain.
        \SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedSetting::create([
            'site_id' => 1,
            'meta_title' => 'عنوان پیش‌فرض ماژول',
            'meta_description' => 'توضیح پیش‌فرض ماژول سئو.',
            'robots_directive' => 'noindex, follow',
            'og_image_url' => 'https://example.invalid/module-og.png',
            'twitter_card_type' => 'summary_large_image',
        ]);

        // A page with no description of its own, so the module's value is the
        // only candidate for that field.
        $this->page(['meta_description' => null]);

        $response = $this->get('/page/about-us');

        $response->assertOk();
        $this->assertOneOfEachSeoTag($response->getContent(), 'page/{slug} driven by the real SEO Advanced adapter');
        $response->assertSee('name="description" content="توضیح پیش‌فرض ماژول سئو."', false);
        $response->assertSee('<meta name="robots" content="noindex, follow">', false);
        // The page's own title still wins over the module's site-wide default.
        $response->assertSee('<title>دربارهٔ ما</title>', false);
    }

    public function test_the_adapters_camel_case_keys_reach_the_rendered_og_and_twitter_tags(): void
    {
        if (! app(SeoOverrideProviderInterface::class) instanceof SeoOverrideProviderAdapter) {
            $this->markTestSkipped('SEO Advanced is not bound in this environment.');
        }

        // Regression: SeoOverrideProviderInterface declares `ogImage` and
        // `twitterCard`, and SeoOverrideProviderAdapter emits exactly those
        // spellings, but MetaEngine only read `og_image` / `twitter_card`.
        // Every module value was therefore dropped on the floor: a configured
        // OG image rendered no og:image tag at all and every page fell back to
        // the "summary" Twitter card. Verified before the fix:
        // og_image="" and twitter_card="summary" with both fields configured.
        \SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedSetting::create([
            'site_id' => 1,
            'og_image_url' => 'https://example.invalid/module-og.png',
            'twitter_card_type' => 'summary_large_image',
        ]);

        $metaData = app(SeoService::class)->resolveForTenantPublic(null);

        $this->assertSame('https://example.invalid/module-og.png', $metaData->og_image);
        $this->assertSame('summary_large_image', $metaData->twitter_card);

        $this->page();

        $response = $this->get('/page/about-us');

        $response->assertOk();
        $response->assertSee('property="og:image" content="https://example.invalid/module-og.png"', false);
        $response->assertSee('name="twitter:card" content="summary_large_image"', false);
        $response->assertSee('name="twitter:image" content="https://example.invalid/module-og.png"', false);
    }

    public function test_page_data_outranks_a_site_wide_provider_default(): void
    {
        // SeoOverrideProviderInterface supplies a value for a field the host
        // has no existing default for. A page that HAS its own title must keep
        // it — otherwise installing SEO Advanced collapses every page of the
        // site onto one title.
        app()->bind(
            SeoOverrideProviderInterface::class,
            fn () => new class implements SeoOverrideProviderInterface {
                public function overrideFor(): ?array
                {
                    return ['title' => 'عنوان پیش‌فرض سایت از ماژول سئو'];
                }

                public function extraJsonLd(): array
                {
                    return [];
                }
            }
        );

        $this->page();

        $response = $this->get('/page/about-us');

        $response->assertOk();
        $this->assertOneOfEachSeoTag($response->getContent(), 'page/{slug} with a site-wide provider default');
        $response->assertSee('<title>دربارهٔ ما</title>', false);
        $this->assertStringNotContainsString('ماژول سئو', $response->getContent());
    }

    public function test_a_null_object_provider_does_not_break_resolution(): void
    {
        app()->bind(SeoOverrideProviderInterface::class, \App\Services\Seo\NullSeoOverrideProvider::class);

        $this->page();

        $response = $this->get('/page/about-us');

        $response->assertOk();
        $this->assertOneOfEachSeoTag($response->getContent(), 'page/{slug} with the Null Object');
        $response->assertSee('<title>دربارهٔ ما</title>', false);
        $response->assertSee('<meta name="robots" content="index, follow">', false);
    }

    public function test_a_throwing_override_provider_does_not_break_page_rendering(): void
    {
        app()->bind(
            SeoOverrideProviderInterface::class,
            fn () => new class implements SeoOverrideProviderInterface {
                public function overrideFor(): ?array
                {
                    // What a module adapter does when its own tables have not
                    // been migrated yet on a given host.
                    throw new \RuntimeException('seo_advanced_settings is missing');
                }

                public function extraJsonLd(): array
                {
                    return [];
                }
            }
        );

        $this->page();

        $response = $this->get('/page/about-us');

        $response->assertOk();
        $this->assertOneOfEachSeoTag($response->getContent(), 'page/{slug} with a throwing provider');
        $response->assertSee('<title>دربارهٔ ما</title>', false);
    }
}
