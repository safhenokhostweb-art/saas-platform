import { test, expect, type Page, type Route } from '@playwright/test';
import { execFileSync } from 'node:child_process';

/**
 * Phases 1/2 (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md, ADR-067) —
 * the live behaviour of the commercial header, footer and carousels on the
 * real renderer output, served on a stub origin whose cart endpoints answer
 * like customer-site's:
 *
 *   - header: transparent over a dark hero (auto contrast), solid and
 *     shrunk once scrolled, hidden on a down-scroll and back on an up-scroll;
 *   - full-width mega menu on hover, accordion on phones, Esc closes;
 *   - mini-cart drawer from the header and from an instant add-to-cart;
 *   - RTL carousel: next arrow on the left, ArrowLeft and a rightward drag
 *     advance, never reversed;
 *   - footer: social icons, app badges, trust seals, newsletter.
 */

const ORIGIN = 'http://tb-phase12.test';
const WIDTHS = [
  { name: 'desktop', width: 1366 },
  { name: 'tablet', width: 768 },
  { name: 'mobile', width: 390 },
] as const;

function renderDocument(): string {
  const php = process.env.PHP_BINARY || 'php';
  const source = `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$r = new App\\Services\\ThemeBuilder\\BlockRenderer();
$r->setContext(['site_endpoints' => [
    'newsletter' => '/newsletter/subscribe',
    'cart' => ['page' => '/shop/cart', 'mini' => '/shop/cart/mini', 'add' => '/shop/cart/add', 'remove' => '/shop/cart/remove', 'count' => 3],
]]);
$header = $r->renderBlock(['type' => 'nav_header', 'id' => 'hdr', 'props' => [
    'logo_text' => 'فروشگاه نمونه', 'sticky' => true, 'sticky_hide_on_scroll' => true, 'transparent_over_hero' => true, 'show_cart' => true,
    'nav_links' => [
        ['label' => 'خانه', 'url' => '/'],
        ['label' => 'دسته‌بندی‌ها', 'url' => '/shop', 'mega_columns' => [
            ['title' => 'پوشاک', 'items' => [['label' => 'مردانه', 'url' => '/m', 'icon' => 'shirt'], ['label' => 'زنانه', 'url' => '/w']]],
            ['title' => 'دیجیتال', 'items' => [['label' => 'موبایل', 'url' => '/p', 'icon' => 'smartphone'], ['label' => 'لپ‌تاپ', 'url' => '/l', 'icon' => 'laptop']]],
            ['title' => 'خانه', 'items' => [['label' => 'آشپزخانه', 'url' => '/k']]],
        ], 'mega_banner_image' => '/promo.png', 'mega_banner_text' => 'حراج پاییزه', 'mega_banner_cta' => 'خرید کنید'],
    ],
]]);
$hero = $r->renderBlock(['type' => 'hero', 'id' => 'hero', 'props' => ['title' => 'بهترین‌ها برای خانهٔ شما', 'subtitle' => 'ارسال سریع به سراسر ایران', 'background' => '#1e1b4b']]);
$card = new ReflectionMethod($r, 'productCard');
$carousel = new ReflectionMethod($r, 'renderCarousel');
$cards = [];
foreach ([['کیف چرم دست‌دوز', 790000, 1000000, 'in'], ['ساعت هوشمند', 3450000, null, 'low'], ['هدفون بی‌سیم', 890000, 1100000, 'in'], ['کفش ورزشی', 1680000, null, 'out'], ['عینک آفتابی', 450000, null, 'in']] as $i => [$t, $p, $o, $s]) {
    $cards[] = $card->invoke($r, ['title' => $t, 'url' => '/p/' . ($i + 1), 'price' => $p, 'original' => $o, 'price_html' => SaaSPlatform\\ThemeRuntime\\Support\\PersianNumber::toman($p), 'original_html' => $o ? SaaSPlatform\\ThemeRuntime\\Support\\PersianNumber::toman($o) : null, 'stock' => $s, 'rating' => 4.5, 'reviews' => 20 + $i, 'product_id' => $i + 1]);
}
$products = '<section class="tb-section"><div class="tb-container"><h2 class="tb-heading tb-section-title">محصولات منتخب</h2>' . $carousel->invoke($r, $cards, 'prod', false, 5000, 'tb-product-slider', ['desktop' => 3, 'tablet' => 2, 'mobile' => 1]) . '</div></section>';
$blog = $r->renderBlock(['type' => 'blog_posts', 'id' => 'blog', 'props' => ['layout' => 'slider', 'autoplay' => false, 'title' => 'مجله']]);
$footer = $r->renderBlock(['type' => 'nav_footer', 'id' => 'ftr', 'props' => [
    'logo_text' => 'فروشگاه نمونه', 'tagline' => 'خریدی مطمئن', 'copyright' => '© ۱۴۰۵',
    'columns' => [['title' => 'راهنما', 'links' => [['label' => 'سوالات متداول', 'url' => '/faq']]]],
    'social_links' => [['url' => 'https://instagram.com/x'], ['url' => 'https://t.me/x'], ['url' => 'https://aparat.com/x']],
    'newsletter_enabled' => true,
    'app_links' => [['store' => 'bazaar', 'url' => 'https://cafebazaar.ir/app/x'], ['store' => 'myket', 'url' => 'https://myket.ir/app/x']],
    'enamad_id' => '123456', 'enamad_code' => 'AbC12',
]]);
echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="t"><style>'
    . view('theme-runtime::css.contract')->render() . view('theme-runtime::css.blocks')->render()
    . 'body{margin:0;font-family:Tahoma,sans-serif}</style></head><body>'
    . '<header class="canvas-site-header tb-site-header" role="banner" id="site-header">' . $header . '</header>'
    . '<main>' . $hero . $products . $blog . '<div style="height:120vh"></div></main>'
    . '<footer role="contentinfo">' . $footer . '</footer></body></html>';
`;
  return execFileSync(php, ['-r', source], { cwd: process.cwd(), encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 });
}

const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', 'base64');

async function serve(page: Page, html: string) {
  const state = { lines: [
    { key: '1:0', title: 'کیف چرم دست‌دوز', image: null, quantity: 2, total_html: '۱٬۵۸۰٬۰۰۰ تومان' },
    { key: '3:0', title: 'هدفون بی‌سیم', image: null, quantity: 1, total_html: '۸۹۰٬۰۰۰ تومان' },
  ] as any[], adds: [] as string[] };
  const mini = () => ({ count: state.lines.reduce((n, l) => n + l.quantity, 0), subtotal_html: '۲٬۴۷۰٬۰۰۰ تومان', items: state.lines });
  await page.route('**/*', async (route: Route) => {
    const url = new URL(route.request().url());
    if (url.origin !== ORIGIN) return route.fulfill({ contentType: 'image/png', body: PNG });
    if (url.pathname === '/') return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
    if (url.pathname === '/shop/cart/mini') return route.fulfill({ json: mini() });
    if (url.pathname === '/shop/cart/remove') {
      const key = (route.request().postData() || '').match(/name="line_key"\r\n\r\n([^\r]*)/)?.[1];
      state.lines = state.lines.filter((l) => l.key !== key);
      return route.fulfill({ json: { ok: true, message: 'محصول از سبد حذف شد.', cart: mini() } });
    }
    if (url.pathname === '/shop/cart/add') {
      const id = (route.request().postData() || '').match(/name="product_id"\r\n\r\n([^\r]*)/)?.[1] || '';
      state.adds.push(id);
      state.lines.push({ key: `${id}:0`, title: 'عینک آفتابی', image: null, quantity: 1, total_html: '۴۵۰٬۰۰۰ تومان' });
      return route.fulfill({ json: { ok: true, message: 'به سبد اضافه شد.', cart: mini() } });
    }
    if (/\.(png|jpg)$/.test(url.pathname)) return route.fulfill({ contentType: 'image/png', body: PNG });
    return route.fulfill({ contentType: 'text/html; charset=utf-8', body: '<p>page</p>' });
  });
  return state;
}

test('Phase 1/2 header, mega menu, mini-cart, RTL carousel and footer at every width', async ({ page }) => {
  test.setTimeout(180_000);
  const problems: string[] = [];
  page.on('console', (m) => { if (m.type() === 'error') problems.push(`console: ${m.text()}`); });
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));
  const html = renderDocument();
  for (const asset of ['site_header', 'cart']) expect(html.match(new RegExp(`data-tb-block-asset="${asset}"`, 'g'))?.length, asset).toBe(1);
  const state = await serve(page, html);

  const banner = page.locator('#site-header');
  const inner = page.locator('#tb-hdr .tb-nav-header-inner');

  for (const w of WIDTHS) {
    await page.setViewportSize({ width: w.width, height: 860 });
    await page.goto(`${ORIGIN}/`);

    // ── Header over the hero, then solid, shrunk, hidden, revealed ─────
    await expect(banner).toHaveClass(/tb-hdr-overlay/);
    await expect(banner).toHaveClass(/tb-hdr-sticky/);
    await expect.poll(() => banner.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe('rgba(0, 0, 0, 0)'); // after the 0.25s transition
    expect(await page.locator('#tb-hdr').evaluate((el) => getComputedStyle(el).color), 'auto contrast: light text on the dark hero').toBe('rgb(255, 255, 255)');
    const tall = (await inner.boundingBox())!.height;
    const bannerBox = (await banner.boundingBox())!;
    const heroTitle = page.locator('#tb-hero h1').first();
    expect((await heroTitle.boundingBox())!.y, 'the hero heading clears the transparent header').toBeGreaterThanOrEqual(bannerBox.y + bannerBox.height - 1);
    await page.screenshot({ path: `test-results/phase1-header-top-${w.name}.png` });

    await page.mouse.wheel(0, 300);
    await expect(banner).toHaveClass(/is-scrolled/);
    await page.waitForTimeout(350);
    expect(await banner.evaluate((el) => getComputedStyle(el).backgroundColor)).not.toBe('rgba(0, 0, 0, 0)');
    expect((await inner.boundingBox())!.height, 'shrinks when scrolled').toBeLessThan(tall);
    await page.mouse.wheel(0, 500);
    await expect(banner).toHaveClass(/is-hidden/);
    await page.mouse.wheel(0, -200);
    await expect(banner).not.toHaveClass(/is-hidden/);
    await page.evaluate(() => window.scrollTo(0, 0));
    await expect(banner).not.toHaveClass(/is-scrolled/);

    // ── Mega menu ─────────────────────────────────────────────────────
    const trigger = page.locator('#tb-hdr .tb-mega-menu-trigger');
    const panel = page.locator('#tb-hdr .tb-mega-menu-panel');
    if (w.name === 'desktop') {
      await trigger.hover();
      await expect(panel).toBeVisible();
      await expect(trigger).toHaveAttribute('aria-expanded', 'true');
      const box = await panel.boundingBox();
      expect(box!.width, 'full-width panel').toBeGreaterThan(w.width * 0.9);
      await page.mouse.move(box!.x + box!.width / 2, box!.y + 20); // across the hover bridge
      await expect(panel).toBeVisible();
      await expect(panel.locator('.tb-mega-menu-banner-cta')).toContainText('خرید کنید');
      expect(await panel.locator('.tb-mega-menu-banner-text').evaluate((el) => getComputedStyle(el).color), 'banner text is not white-on-white').not.toBe('rgb(255, 255, 255)');
      await page.screenshot({ path: `test-results/phase1-mega-${w.name}.png` });
      await page.mouse.move(5, 700);
      await expect(panel).toBeHidden();
    } else if (await trigger.isVisible()) {
      await trigger.click();
      await expect(panel).toBeVisible();
      await expect(trigger).toHaveAttribute('aria-expanded', 'true');
      await page.keyboard.press('Escape');
      await expect(panel).toBeHidden();
    }

    // ── Mini-cart drawer ──────────────────────────────────────────────
    const cartBtn = page.locator('#tb-hdr .tb-cart-trigger');
    if (await cartBtn.isVisible()) {
      await expect(cartBtn.locator('[data-tb-cart-count]')).toHaveText('۳');
      await cartBtn.click();
      const drawer = page.locator('.tb-cart-drawer [role="dialog"]');
      await expect(drawer).toBeVisible();
      await expect(page.locator('.tb-cart-drawer__item')).toHaveCount(state.lines.length);
      await expect(page.locator('.tb-cart-drawer__subtotal strong')).toHaveText('۲٬۴۷۰٬۰۰۰ تومان');
      const drawerBox = await drawer.boundingBox();
      expect(drawerBox!.x, 'RTL drawer slides in from the left').toBeLessThan(5);
      await page.waitForTimeout(320);
      await page.screenshot({ path: `test-results/phase1-cart-${w.name}.png` });
      await page.keyboard.press('Escape');
      await expect(drawer).toBeHidden();
      await expect(cartBtn).toBeFocused();
    }

    // ── RTL carousel ──────────────────────────────────────────────────
    const prod = page.locator('#tb-prod');
    await prod.scrollIntoViewIfNeeded();
    const next = prod.locator('.tb-carousel-next');
    const prev = prod.locator('.tb-carousel-prev');
    const pb = await prod.boundingBox();
    expect((await next.boundingBox())!.x, 'next sits on the left in RTL').toBeLessThan(pb!.x + pb!.width / 2);
    expect((await prev.boundingBox())!.x).toBeGreaterThan(pb!.x + pb!.width / 2);
    const tx = () => prod.locator('.tb-carousel-track').evaluate((t) => new DOMMatrixReadOnly(getComputedStyle(t).transform).m41);
    await next.click();
    await page.waitForTimeout(500);
    const afterNext = await tx();
    expect(afterNext, 'advancing in RTL moves the track right').toBeGreaterThan(0);
    await prev.click();
    await page.waitForTimeout(500);
    expect(await tx()).toBe(0);
    await prod.focus();
    await page.keyboard.press('ArrowLeft');
    await page.waitForTimeout(500);
    expect(await tx(), 'ArrowLeft advances in RTL').toBeCloseTo(afterNext, 0);
    await page.keyboard.press('ArrowRight');
    await page.waitForTimeout(500);
    const vb = await prod.locator('.tb-carousel-viewport').boundingBox();
    await page.mouse.move(vb!.x + vb!.width * 0.3, vb!.y + 60);
    await page.mouse.down();
    await page.mouse.move(vb!.x + vb!.width * 0.7, vb!.y + 64, { steps: 8 });
    await page.mouse.up();
    await page.waitForTimeout(500);
    expect(await tx(), 'a rightward drag advances in RTL').toBeCloseTo(afterNext, 0);
    await page.screenshot({ path: `test-results/phase2-products-${w.name}.png`, clip: { x: 0, y: Math.max(0, (await prod.boundingBox())!.y - 60), width: w.width, height: Math.min(640, (await prod.boundingBox())!.height + 120) } });

    // ── Instant add to cart ───────────────────────────────────────────
    await page.evaluate(() => window.scrollTo(0, 0));
    const addButton = prod.locator('[data-tb-add-to-cart][data-product-id="5"]');
    await prod.locator('.tb-carousel-dot').last().click();
    await page.waitForTimeout(500);
    const before = state.adds.length;
    await addButton.click();
    await expect(page.locator('.tb-cart-drawer [role="dialog"]')).toBeVisible();
    expect(state.adds.slice(before)).toEqual(['5']);
    await expect(page.locator('.tb-toast').last()).toHaveText('به سبد اضافه شد.');
    await page.keyboard.press('Escape');
    state.lines.pop();
    await expect(prod.locator('.tb-product-card.is-out .tb-product-add')).toHaveText('ناموجود');

    // ── Footer ────────────────────────────────────────────────────────
    const footer = page.locator('#tb-ftr');
    await footer.scrollIntoViewIfNeeded();
    await expect(footer.locator('.tb-social-icon svg')).toHaveCount(3);
    await expect(footer.locator('.tb-app-badge')).toHaveCount(2);
    await expect(footer.locator('.tb-trust-seal img')).toHaveAttribute('referrerpolicy', 'origin');
    await expect(footer.locator('form[data-tb-newsletter]')).toBeVisible();
    await footer.screenshot({ path: `test-results/phase1-footer-${w.name}.png` });

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, `no horizontal overflow at ${w.name}`).toBeLessThanOrEqual(0);
  }

  expect(problems, problems.join('\n')).toEqual([]);
});
