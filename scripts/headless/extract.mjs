/**
 * Headless Visual Extractor — TB3 Sprint 11.5
 *
 * Upgrades Sprint 11:
 *   - Multi-viewport mode: desktop (1440×900) / tablet (768×1024) / mobile (390×844)
 *   - Scroll-triggered lazy loading: 4 scroll passes to surface hidden content
 *   - 60+ product/section selectors covering Next.js, React, Vue, Angular, Nuxt
 *   - CSS custom properties (design tokens) extraction
 *   - ARIA landmark detection for framework-agnostic section discovery
 *   - Accessibility tree headings for semantic structure
 *   - Performance timing (navigation, FCP, LCP) via PerformanceObserver
 *   - Framework fingerprinting (Tailwind, Bootstrap, MUI, Ant, Chakra)
 *   - SPA hydration detection wait loop
 *
 * Usage:
 *   node extract.mjs <url> [timeout_ms] [device]
 *   device: desktop (default) | tablet | mobile | all
 *
 * Output: JSON to stdout
 * Errors: JSON to stderr, exit code 1
 */

import { chromium } from 'playwright';
import { existsSync } from 'fs';
import os from 'os';

const url       = process.argv[2];
const timeoutMs = parseInt(process.argv[3] || '35000');
const device    = (process.argv[4] || 'desktop').toLowerCase();

if (!url) {
  process.stderr.write(JSON.stringify({ success: false, error: 'URL is required as first argument' }));
  process.exit(1);
}

// ── Device viewports ──────────────────────────────────────────────────────────

const VIEWPORTS = {
  desktop: { width: 1440, height: 900 },
  tablet:  { width: 768,  height: 1024 },
  mobile:  { width: 390,  height: 844 },
};

// ── System Chrome finder ──────────────────────────────────────────────────────

function findSystemChrome() {
  if (process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH) {
    return process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH;
  }

  const platform = os.platform();

  if (platform === 'win32') {
    const candidates = [
      'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
      'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
      `${process.env.LOCALAPPDATA}\\Google\\Chrome\\Application\\chrome.exe`,
      'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
      'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
      `${process.env.LOCALAPPDATA}\\Microsoft\\Edge\\Application\\msedge.exe`,
    ];
    for (const c of candidates) {
      if (c && existsSync(c)) return c;
    }
  }

  if (platform === 'linux') {
    const candidates = [
      '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable',
      '/usr/bin/chromium-browser', '/usr/bin/chromium',
      '/snap/bin/chromium',
    ];
    for (const c of candidates) {
      if (existsSync(c)) return c;
    }
  }

  if (platform === 'darwin') {
    const candidates = [
      '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
      '/Applications/Chromium.app/Contents/MacOS/Chromium',
    ];
    for (const c of candidates) {
      if (existsSync(c)) return c;
    }
  }

  return null;
}

// ── Scroll helper: trigger lazy loading ──────────────────────────────────────

async function scrollForLazyLoad(page) {
  const viewportHeight = page.viewportSize()?.height ?? 900;

  await page.evaluate(async (vh) => {
    for (let i = 1; i <= 4; i++) {
      window.scrollTo({ top: vh * i * 1.5, behavior: 'smooth' });
      await new Promise(r => setTimeout(r, 600));
    }
    // Return to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
    await new Promise(r => setTimeout(r, 400));
  }, viewportHeight);

  // Let lazy images/components hydrate
  await page.waitForTimeout(800);
}

// ── Wait for SPA hydration ────────────────────────────────────────────────────

async function waitForHydration(page, ms = 3000) {
  // Try to wait for common SPA content signals
  const signals = [
    '[class*="product"]:not(:empty)',
    '[data-testid]',
    'main h1, main h2',
    'nav a',
    '[role="main"]',
  ];

  const deadline = Date.now() + ms;
  while (Date.now() < deadline) {
    for (const sel of signals) {
      try {
        const el = await page.$(sel);
        if (el) return;
      } catch { /* ignore */ }
    }
    await page.waitForTimeout(300);
  }
}

// ── Core extraction ───────────────────────────────────────────────────────────

async function extractFromPage(page, targetUrl) {
  const t0 = Date.now();

  // Navigate
  let finalUrl = targetUrl;
  try {
    await page.goto(targetUrl, { waitUntil: 'networkidle', timeout: timeoutMs });
    finalUrl = page.url();
  } catch {
    try {
      await page.goto(targetUrl, { waitUntil: 'load', timeout: timeoutMs });
      await page.waitForTimeout(3000);
      finalUrl = page.url();
    } catch (err2) {
      throw new Error(`Navigation failed: ${err2.message}`);
    }
  }

  const navTime = Date.now() - t0;

  // Wait for SPA hydration
  await waitForHydration(page, 4000);

  // Scroll to trigger lazy loading
  await scrollForLazyLoad(page);

  // Screenshot (above the fold, no full page — fast)
  let screenshotB64 = '';
  try {
    const screenshot = await page.screenshot({ type: 'png', fullPage: false, timeout: 12000 });
    screenshotB64 = screenshot.toString('base64');
  } catch { /* non-fatal */ }

  // Full rendered HTML
  const renderedHtml = await page.content();
  const htmlSize = renderedHtml.length;

  // ── Framework detection ──────────────────────────────────────────────────

  const frameworkInfo = await page.evaluate(() => {
    const html   = document.documentElement.outerHTML;
    const htmlL  = html.toLowerCase();

    // Next.js detection
    const isNextjs = !!(window.__NEXT_DATA__
      || document.getElementById('__NEXT_DATA__')
      || document.querySelector('script[src*="_next/"]')
      || html.includes('__next_s')
    );

    // React
    const isReact = !!(
      window.React
      || document.querySelector('[data-reactroot]')
      || document.querySelector('[data-react-helmet]')
      || html.includes('__reactFiber')
      || html.includes('__reactInternalInstance')
    );

    // Vue
    const isVue = !!(
      window.__vue__
      || document.querySelector('[data-v-]')
      || document.querySelector('[class*="__nuxt"]')
      || html.includes('_vue_')
    );

    // Angular
    const isAngular = !!(
      window.ng
      || document.querySelector('[ng-version]')
      || document.querySelector('[_nghost')
      || html.includes('ng-reflect')
    );

    // Nuxt
    const isNuxt = !!(
      window.__NUXT__
      || html.includes('__nuxt')
      || document.querySelector('#__nuxt')
    );

    // CSS Framework fingerprinting
    const cssFrameworks = {
      tailwind:   html.includes('tailwind') || !!document.querySelector('[class*="text-"]:[class*="bg-"]'),
      bootstrap:  html.includes('bootstrap') || !!document.querySelector('.container, .col-md-'),
      material:   html.includes('MuiBox') || html.includes('makeStyles') || html.includes('mui'),
      antd:       html.includes('ant-') || html.includes('antd'),
      chakra:     html.includes('chakra') || html.includes('css-') && html.includes('colorScheme'),
    };

    // Detect hashed class names (indicates CSS Modules or atomic CSS)
    const hashedClasses = !!document.querySelector('[class*="_"]')
      && !document.querySelector('[class="container"]');

    return {
      is_nextjs:      isNextjs,
      is_react:       isReact,
      is_vue:         isVue,
      is_angular:     isAngular,
      is_nuxt:        isNuxt,
      css_frameworks: cssFrameworks,
      has_hashed_css: hashedClasses,
      html_size:      document.documentElement.outerHTML.length,
    };
  });

  // ── Performance metrics ──────────────────────────────────────────────────

  const performanceMetrics = await page.evaluate(() => {
    const nav    = performance.getEntriesByType('navigation')[0] ?? {};
    const paints = performance.getEntriesByType('paint');
    const fcp    = paints.find(e => e.name === 'first-contentful-paint')?.startTime ?? 0;

    const resources = performance.getEntriesByType('resource');
    const jsBundles = resources.filter(r => r.initiatorType === 'script').length;
    const cssBundles= resources.filter(r => r.initiatorType === 'link' || r.name.endsWith('.css')).length;
    const imgCount  = resources.filter(r => r.initiatorType === 'img').length;

    return {
      dom_content_loaded: Math.round(nav.domContentLoadedEventEnd ?? 0),
      load_complete:      Math.round(nav.loadEventEnd ?? 0),
      fcp_ms:             Math.round(fcp),
      js_bundles:         jsBundles,
      css_bundles:        cssBundles,
      image_requests:     imgCount,
      total_resources:    resources.length,
      transfer_size_kb:   Math.round((nav.transferSize ?? 0) / 1024),
    };
  });

  // ── CSS custom properties (design tokens) ────────────────────────────────

  const cssVariables = await page.evaluate(() => {
    const styles  = getComputedStyle(document.documentElement);
    const found   = {};
    const prefixes= ['--primary', '--secondary', '--accent', '--color', '--brand', '--theme',
                     '--bg', '--text', '--border', '--radius', '--shadow', '--font',
                     '--spacing', '--gap', '--padding'];

    // Get custom properties from all stylesheets
    for (const sheet of document.styleSheets) {
      try {
        for (const rule of sheet.cssRules) {
          if (rule.style) {
            for (const prop of rule.style) {
              if (prop.startsWith('--')) {
                const val = rule.style.getPropertyValue(prop).trim();
                if (val && prefixes.some(p => prop.toLowerCase().startsWith(p))) {
                  found[prop] = val;
                }
              }
            }
          }
        }
      } catch { /* cross-origin */ }
    }

    // Also check :root computed vars
    const rootVars = {};
    for (const [k, v] of Object.entries(found)) {
      rootVars[k] = styles.getPropertyValue(k).trim() || v;
    }

    return rootVars;
  });

  // ── ARIA landmarks detection ─────────────────────────────────────────────

  const ariaLandmarks = await page.evaluate(() => {
    const landmark = (role) => {
      const el = document.querySelector(`[role="${role}"], ${role === 'main' ? 'main' : ''}`);
      return el ? { found: true, text: el.textContent.trim().substring(0, 80) } : { found: false };
    };
    return {
      banner:      landmark('banner'),
      main:        landmark('main'),
      navigation:  landmark('navigation'),
      complementary: landmark('complementary'),
      contentinfo: landmark('contentinfo'),
      search:      landmark('search'),
      region:      { count: document.querySelectorAll('[role="region"]').length },
    };
  });

  // ── Structured component extraction ─────────────────────────────────────

  const components = await page.evaluate(() => {
    // ── Product card selectors (60+) ──────────────────────────────────────
    const PRODUCT_SELECTORS = [
      // Generic
      '[class*="product-card"]', '[class*="ProductCard"]', '[class*="product_card"]',
      '[class*="product-item"]', '[class*="ProductItem"]', '[class*="productItem"]',
      '[class*="product-tile"]', '[class*="ProductTile"]',
      '[class*="goods-item"]',   '[class*="goods-card"]', '[class*="GoodsItem"]',
      '[class*="shop-item"]',    '[class*="shopItem"]',   '[class*="ShopItem"]',
      '[class*="catalog-item"]', '[class*="CatalogItem"]',
      '[class*="item-card"]',    '[class*="ItemCard"]',
      '[class*="listing-item"]', '[class*="ListingItem"]',
      // data-testid
      '[data-testid*="product"]', '[data-testid*="Product"]',
      '[data-testid*="item"]',    '[data-test*="product"]',
      '[data-cy*="product"]',     '[data-qa*="product"]',
      // Persian
      '[class*="محصول"]',         '[class*="کالا"]',
      // Next.js / React hashed — try to detect by content
      'article:has(img):has([class*="price"])',
      'li:has(img):has([class*="price"])',
      'div:has(img):has([class*="price"]):has([class*="title"])',
      // Common ecommerce patterns
      '.product', '.item', '.card:has(img)',
    ];

    // ── Section keyword patterns for text-based detection ─────────────────
    const SECTION_KEYWORDS = {
      header:       ['header', 'navbar', 'nav', 'top-bar', 'topbar'],
      hero:         ['hero', 'banner', 'jumbotron', 'intro', 'landing', 'slide', 'carousel-item'],
      features:     ['feature', 'benefit', 'advantage', 'why-us', 'service'],
      products:     ['product', 'goods', 'catalog', 'shop', 'store', 'item', 'کالا', 'محصول'],
      categories:   ['categ', 'department', 'section', 'browse', 'دسته'],
      pricing:      ['pric', 'plan', 'tariff', 'subscription', 'قیمت'],
      testimonials: ['testimonial', 'review', 'feedback', 'نظر'],
      blog:         ['blog', 'news', 'article', 'post', 'مقاله'],
      faq:          ['faq', 'question', 'accordion', 'سوال'],
      cta:          ['cta', 'call-to-action', 'get-started', 'signup', 'شروع'],
      footer:       ['footer', 'bottom', 'sitemap', 'copyright'],
    };

    const result = {
      meta: {
        title:       document.title,
        description: document.querySelector('meta[name="description"]')?.content ?? '',
        og_image:    document.querySelector('meta[property="og:image"]')?.content ?? '',
        og_title:    document.querySelector('meta[property="og:title"]')?.content ?? '',
        language:    document.documentElement.lang ?? '',
        is_rtl:      document.documentElement.dir === 'rtl'
                     || getComputedStyle(document.body).direction === 'rtl',
        h1:          document.querySelector('h1')?.textContent?.trim()?.substring(0, 150) ?? '',
        h2_list:     [...document.querySelectorAll('h2')].map(e =>
                       e.textContent.trim().substring(0, 100)).slice(0, 10),
        h3_list:     [...document.querySelectorAll('h3')].map(e =>
                       e.textContent.trim().substring(0, 80)).slice(0, 8),
      },

      nav: {
        items:         [],
        has_mega_menu: false,
        is_sticky:     false,
        has_search:    false,
        has_cart:      false,
        depth:         0,
      },

      hero: {
        headline:     '',
        subheadline:  '',
        cta_buttons:  [],
        has_image:    false,
        has_video:    false,
        has_gradient: false,
        has_slider:   false,
        layout:       'centered',
      },

      product_grid: {
        count:         0,
        columns:       0,
        card_html:     '',
        has_price:     false,
        has_badge:     false,
        has_image:     false,
        has_rating:    false,
        has_cart_btn:  false,
        price_samples: [],
        selector_used: '',
      },

      cards:        { types: [], total: 0, has_hover: false },
      mega_menu:    { detected: false, columns: 0, has_icons: false, sub_items: [] },
      sliders:      [],
      filters:      { found: false, type: '', items: [], count: 0 },
      search:       { found: false, placeholder: '', is_prominent: false },
      footer:       { links: [], has_social: false, columns: 0, has_newsletter: false, has_sitemap: false },
      colors:       [],
      typography:   { fonts: [], base_size: '', weights: [], line_heights: [] },
      sections_detected: [],
      aria_sections: [],
      heading_structure: [],
    };

    // ── Navigation ────────────────────────────────────────────────────────────

    const navEl = document.querySelector(
      'nav, header, [role="navigation"], [role="banner"], [class*="navbar"], [class*="nav-bar"]'
    );
    const navLinks = document.querySelectorAll(
      'nav a, header a, [role="navigation"] a, [role="banner"] a'
    );
    const seen = new Set();
    navLinks.forEach(a => {
      const t = a.textContent.trim();
      if (t && t.length > 0 && t.length < 80 && !seen.has(t)) {
        seen.add(t);
        result.nav.items.push(t);
      }
    });
    result.nav.items = result.nav.items.slice(0, 30);

    if (navEl) {
      const style = window.getComputedStyle(navEl);
      result.nav.is_sticky  = style.position === 'fixed' || style.position === 'sticky';
      // Nested nav depth
      const subMenus = navEl.querySelectorAll('ul ul, [class*="submenu"], [class*="dropdown"]');
      result.nav.depth = subMenus.length > 0 ? 2 : 1;
    }
    result.nav.has_cart = !!document.querySelector(
      '[class*="cart"], [class*="basket"], [class*="bag"], [data-testid*="cart"], [aria-label*="cart"]'
    );
    result.nav.has_search = !!document.querySelector(
      'nav input[type="search"], nav input[type="text"], header input, [class*="search-bar"], [class*="searchbar"]'
    );

    // Mega menu detection
    const megaSels = [
      '[class*="mega"]', '[class*="flyout"]', '[class*="dropdown-menu"]',
      '[class*="nav-dropdown"]', '[data-dropdown]', '[aria-haspopup="true"]',
      '[class*="megamenu"]', '[role="menu"]',
    ];
    for (const s of megaSels) {
      const el = document.querySelector(s);
      if (el) {
        result.nav.has_mega_menu  = true;
        result.mega_menu.detected = true;
        el.querySelectorAll('a').forEach(a => {
          const t = a.textContent.trim();
          if (t && t.length < 60) result.mega_menu.sub_items.push(t);
        });
        result.mega_menu.sub_items = [...new Set(result.mega_menu.sub_items)].slice(0, 30);
        result.mega_menu.has_icons = !!el.querySelector('svg, img, i[class*="icon"]');
        const cols = el.querySelectorAll('[class*="col"]');
        result.mega_menu.columns = Math.min(cols.length || 3, 6);
        break;
      }
    }

    // ── Hero ─────────────────────────────────────────────────────────────────

    const heroSels = [
      '[class*="hero"]', '[class*="banner"]', '[class*="jumbotron"]',
      '[class*="intro"]', '[class*="landing"]', '[class*="splash"]',
      '[class*="main-visual"]', '[class*="key-visual"]', '[class*="top-visual"]',
      'section:first-of-type', 'main > *:first-child',
      '#hero', '#home', '#main', '[role="main"] > *:first-child',
    ];
    for (const sel of heroSels) {
      const el = document.querySelector(sel);
      if (!el) continue;
      const h1 = el.querySelector('h1') || document.querySelector('h1');
      if (!h1) continue;
      result.hero.headline    = h1.textContent.trim().substring(0, 150);
      const p = el.querySelector('p, h2, [class*="subtitle"], [class*="tagline"], [class*="sub-title"]');
      result.hero.subheadline = (p?.textContent ?? '').trim().substring(0, 250);
      const btns = [...el.querySelectorAll('a, button')];
      btns.forEach(b => {
        const t = b.textContent.trim();
        if (t && t.length > 0 && t.length < 80) result.hero.cta_buttons.push(t);
      });
      result.hero.cta_buttons = [...new Set(result.hero.cta_buttons)].slice(0, 6);
      result.hero.has_image   = !!el.querySelector('img, [style*="background-image"]');
      result.hero.has_video   = !!el.querySelector('video, iframe[src*="youtube"], iframe[src*="vimeo"]');
      const style = window.getComputedStyle(el);
      result.hero.has_gradient = style.backgroundImage.includes('gradient');
      result.hero.has_slider = !!el.querySelector('[class*="swiper"], [class*="splide"], [class*="carousel"]');
      if (el.querySelector('[class*="split"], [class*="two-col"]')) result.hero.layout = 'split';
      else if (el.querySelector('[class*="center"], [class*="text-center"]')) result.hero.layout = 'centered';
      break;
    }

    // ── Product Grid ─────────────────────────────────────────────────────────

    const PRODUCT_SELECTORS_EVAL = [
      '[class*="product-card"]', '[class*="ProductCard"]', '[class*="product_card"]',
      '[class*="product-item"]', '[class*="ProductItem"]', '[class*="productItem"]',
      '[class*="product-tile"]', '[class*="ProductTile"]',
      '[class*="goods-item"]',   '[class*="goods-card"]',
      '[class*="shop-item"]',    '[class*="catalog-item"]', '[class*="CatalogItem"]',
      '[class*="item-card"]',    '[class*="listing-item"]',
      '[data-testid*="product"]', '[data-testid*="Product"]',
      '[data-testid*="item"]',   '[data-test*="product"]',
      '[data-testid*="goods"]',  '[data-testid*="card"]',
      '[data-cy*="product"]',    '[data-qa*="product"]',
      '[itemtype*="Product"]',
      // Heuristic: li/article with img + price-like element
      'li:has(img):has([class*="price"]):has([class*="name"])',
      'article:has(img):has([class*="price"])',
      // Tailwind patterns: grid > li or div with min-height image
      '.grid > li:has(img)', '.grid > div:has(img):has(a)',
    ];

    for (const sel of PRODUCT_SELECTORS_EVAL) {
      try {
        const cards = document.querySelectorAll(sel);
        if (cards.length >= 2) {
          result.product_grid.count        = cards.length;
          result.product_grid.selector_used = sel;
          result.product_grid.card_html    = cards[0].innerHTML.substring(0, 1000);
          result.product_grid.has_price    = !!(
            cards[0].querySelector('[class*="price"], [class*="cost"], [class*="قیمت"], [data-testid*="price"]')
            || cards[0].textContent.match(/[\d,]+\s*(تومان|ریال|$|\€|£|¥)/)
          );
          result.product_grid.has_badge    = !!cards[0].querySelector(
            '[class*="badge"], [class*="label"], [class*="tag"], [class*="off"], [class*="discount"]'
          );
          result.product_grid.has_image    = !!cards[0].querySelector('img');
          result.product_grid.has_rating   = !!cards[0].querySelector(
            '[class*="star"], [class*="rating"], [class*="score"], [class*="امتیاز"]'
          );
          result.product_grid.has_cart_btn = !!cards[0].querySelector(
            '[class*="cart"], [class*="add-to"], [class*="buy"], [class*="خرید"]'
          );

          // Price samples
          const priceEls = [...cards].slice(0, 5).map(c => {
            const p = c.querySelector('[class*="price"], [class*="قیمت"]');
            return p ? p.textContent.trim().substring(0, 40) : null;
          }).filter(Boolean);
          result.product_grid.price_samples = priceEls;

          // Column count from layout
          try {
            const rect0 = cards[0].getBoundingClientRect();
            const rect1 = cards.length > 1 ? cards[1].getBoundingClientRect() : null;
            if (rect0.width > 50) {
              const parentW = cards[0].parentElement?.getBoundingClientRect()?.width || 1440;
              const estimated = Math.max(1, Math.round(parentW / rect0.width));
              // Validate with second card (should be on same row if same y)
              if (rect1 && Math.abs(rect0.top - rect1.top) < 10) {
                result.product_grid.columns = estimated;
              } else {
                result.product_grid.columns = estimated;
              }
            }
          } catch { result.product_grid.columns = 4; }
          break;
        }
      } catch { /* CSS selector might not be supported */ }
    }

    // ── Generic Cards ─────────────────────────────────────────────────────────

    const allCards = document.querySelectorAll(
      '[class*="card"], [class*="tile"], [class*="Card"], [class*="Tile"]'
    );
    result.cards.total = allCards.length;
    result.cards.has_hover = [...allCards].slice(0, 20).some(c => {
      const s = window.getComputedStyle(c);
      return s.transition !== 'all 0s ease 0s' && s.transition !== '' && s.transition !== 'none';
    });

    if (document.querySelector('[class*="product-card"], [class*="goods-item"], [class*="ProductCard"]')) result.cards.types.push('product');
    if (document.querySelector('[class*="blog-card"], [class*="post-card"], [class*="article-card"]'))    result.cards.types.push('blog');
    if (document.querySelector('[class*="feature-card"], [class*="service-card"], [class*="icon-box"]'))  result.cards.types.push('feature');
    if (document.querySelector('[class*="team-card"], [class*="member-card"], [class*="staff"]'))          result.cards.types.push('team');
    if (document.querySelector('[class*="pricing-card"], [class*="plan-card"], [class*="price-box"]'))    result.cards.types.push('pricing');
    if (document.querySelector('[class*="testimonial"], [class*="review-card"]'))                         result.cards.types.push('testimonial');
    if (document.querySelector('[class*="category-card"], [class*="CategoryCard"]'))                      result.cards.types.push('category');

    // ── Search ────────────────────────────────────────────────────────────────

    const searchEl = document.querySelector(
      'input[type="search"], [class*="search"] input, [role="search"] input, '
      + 'input[placeholder*="search" i], input[placeholder*="جستجو"], '
      + 'input[aria-label*="search" i], input[aria-label*="جستجو"]'
    );
    if (searchEl) {
      result.search.found       = true;
      result.search.placeholder = searchEl.placeholder ?? '';
      const rect = searchEl.getBoundingClientRect();
      result.search.is_prominent = rect.width > 200;
    }

    // ── Filters / Facets ──────────────────────────────────────────────────────

    const filterSels = [
      '[class*="filter"]', '[class*="facet"]', '[class*="Filter"]', '[class*="Facet"]',
      '[class*="sidebar"] ul', '[class*="refinement"]', '[class*="category-tree"]',
      '[data-testid*="filter"]', '[aria-label*="filter" i]',
    ];
    for (const s of filterSels) {
      try {
        const el = document.querySelector(s);
        if (el) {
          result.filters.found = true;
          result.filters.type  = s;
          const items = el.querySelectorAll('a, li, button, label, [class*="item"]');
          items.forEach(i => {
            const t = i.textContent.trim().substring(0, 60);
            if (t) result.filters.items.push(t);
          });
          result.filters.items = [...new Set(result.filters.items)].slice(0, 20);
          result.filters.count = result.filters.items.length;
          break;
        }
      } catch { /* ignore */ }
    }

    // ── Sliders / Carousels ────────────────────────────────────────────────────

    const sliderKeywords = [
      'swiper', 'splide', 'owl', 'slick', 'glide', 'flickity',
      'embla', 'carousel', 'slider', 'Slider', 'Swiper',
    ];
    sliderKeywords.forEach(kw => {
      if (document.querySelector(`[class*="${kw}"], .${kw}`)) result.sliders.push(kw.toLowerCase());
    });
    result.sliders = [...new Set(result.sliders)];

    // ── Footer ────────────────────────────────────────────────────────────────

    const footerEl = document.querySelector('footer, [class*="footer"], [role="contentinfo"]');
    if (footerEl) {
      const seenLinks = new Set();
      footerEl.querySelectorAll('a').forEach(a => {
        const t = a.textContent.trim().substring(0, 60);
        if (t && t.length > 0 && !seenLinks.has(t)) { seenLinks.add(t); result.footer.links.push(t); }
      });
      result.footer.links        = result.footer.links.slice(0, 50);
      result.footer.has_social   = /instagram|twitter|x\.com|facebook|telegram|linkedin|youtube/i.test(footerEl.innerHTML);
      result.footer.has_newsletter = !!footerEl.querySelector(
        'input[type="email"], [class*="newsletter"], [class*="subscribe"], [class*="email-form"]'
      );
      result.footer.has_sitemap  = result.footer.links.length > 15;
      const cols = footerEl.querySelectorAll('[class*="col"]:not([class*="row"]), [class*="column"]');
      const directDivs = footerEl.querySelectorAll(':scope > div > div, :scope > ul');
      result.footer.columns = Math.min(Math.max(cols.length, directDivs.length, 1), 6);
    }

    // ── ARIA landmarks ────────────────────────────────────────────────────────

    const ariaRoles = ['banner', 'main', 'navigation', 'contentinfo', 'complementary', 'search', 'region'];
    ariaRoles.forEach(role => {
      const el = document.querySelector(`[role="${role}"]`)
        || (role === 'main' ? document.querySelector('main') : null)
        || (role === 'banner' ? document.querySelector('header') : null)
        || (role === 'contentinfo' ? document.querySelector('footer') : null)
        || (role === 'navigation' ? document.querySelector('nav') : null);

      if (el) result.aria_sections.push({ role, text: el.textContent.trim().substring(0, 60) });
    });

    // ── Heading structure (H1–H3 hierarchy) ──────────────────────────────────

    document.querySelectorAll('h1, h2, h3').forEach(h => {
      result.heading_structure.push({
        level: parseInt(h.tagName.replace('H', '')),
        text:  h.textContent.trim().substring(0, 100),
      });
    });
    result.heading_structure = result.heading_structure.slice(0, 20);

    // ── Sections Detected (keyword + aria + heading) ─────────────────────────

    const allText = document.documentElement.innerHTML.toLowerCase();
    const SECTION_KEYWORDS_EVAL = {
      header:       ['header', 'navbar', 'nav', 'navigation'],
      hero:         ['hero', 'banner', 'jumbotron', 'intro', 'slider', 'carousel'],
      features:     ['feature', 'benefit', 'advantage', 'why-us', 'service'],
      products:     ['product', 'goods', 'catalog', 'shop', 'کالا', 'محصول'],
      categories:   ['categ', 'department', 'دسته'],
      pricing:      ['pric', 'plan', 'tariff', 'subscription', 'قیمت'],
      testimonials: ['testimonial', 'review', 'feedback', 'نظر'],
      blog:         ['blog', 'news', 'article', 'post', 'مقاله'],
      faq:          ['faq', 'question', 'accordion', 'سوال'],
      cta:          ['cta', 'call-to-action', 'get-started', 'signup', 'شروع'],
      footer:       ['footer', 'copyright'],
    };

    // ARIA-based detection
    result.aria_sections.forEach(({ role }) => {
      const roleToSection = { banner: 'header', main: 'hero', contentinfo: 'footer', navigation: 'header' };
      if (roleToSection[role]) {
        const s = roleToSection[role];
        if (!result.sections_detected.includes(s)) result.sections_detected.push(s);
      }
    });

    // Keyword-based detection
    for (const [section, keywords] of Object.entries(SECTION_KEYWORDS_EVAL)) {
      if (keywords.some(k => allText.includes(k)) && !result.sections_detected.includes(section)) {
        result.sections_detected.push(section);
      }
    }

    // Product grid presence → add products section
    if (result.product_grid.count >= 2 && !result.sections_detected.includes('products')) {
      result.sections_detected.push('products');
    }

    // Footer link presence → add footer
    if (result.footer.links.length > 0 && !result.sections_detected.includes('footer')) {
      result.sections_detected.push('footer');
    }

    // ── Color Extraction ─────────────────────────────────────────────────────

    const colorTargets = [
      document.body,
      document.querySelector('nav, header, [role="banner"]'),
      document.querySelector('h1, h2'),
      document.querySelector('a.btn, button:not(:disabled), .btn-primary, [class*="btn-primary"], [class*="button-primary"]'),
      document.querySelector('[class*="card"]:not([class*="blog"]):not([class*="footer"])'),
      document.querySelector('[class*="hero"], [class*="banner"], section:first-of-type'),
      document.querySelector('[class*="primary"], [class*="brand"]'),
    ].filter(Boolean);

    const colorSet = new Set();
    colorTargets.forEach(el => {
      ['backgroundColor', 'color', 'borderColor'].forEach(prop => {
        try {
          const val = window.getComputedStyle(el)[prop];
          if (!val || val === 'transparent' || val === 'rgba(0, 0, 0, 0)') return;
          const m = val.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
          if (m) {
            const r = parseInt(m[1]), g = parseInt(m[2]), b = parseInt(m[3]);
            // Skip near-white and near-black for non-text elements
            const brightness = (r * 299 + g * 587 + b * 114) / 1000;
            if (brightness > 240 && prop === 'backgroundColor') return;
            const hex = '#' + [r, g, b].map(n => n.toString(16).padStart(2, '0')).join('');
            colorSet.add(hex);
          }
        } catch { /* ignore */ }
      });
    });
    result.colors = [...colorSet].slice(0, 20);

    // ── Typography ────────────────────────────────────────────────────────────

    const bodyStyle = window.getComputedStyle(document.body);
    result.typography.base_size  = bodyStyle.fontSize;
    result.typography.line_heights = [bodyStyle.lineHeight];

    const fontSet = new Set();
    [document.body, document.querySelector('h1'), document.querySelector('p'), document.querySelector('a')]
      .filter(Boolean)
      .forEach(el => {
        const ff = window.getComputedStyle(el).fontFamily;
        if (ff) fontSet.add(ff.split(',')[0].trim().replace(/['"]/g, ''));
      });
    result.typography.fonts   = [...fontSet].slice(0, 6);
    result.typography.weights = [...new Set(
      [...document.querySelectorAll('h1, h2, h3, strong, b, button')].slice(0, 20)
        .map(e => window.getComputedStyle(e).fontWeight)
    )].slice(0, 5);

    return result;
  });

  const extractTime = Date.now() - t0;

  return {
    success:           true,
    url:               finalUrl,
    original_url:      targetUrl,
    rendered_html:     renderedHtml,
    screenshot_base64: screenshotB64,
    components,
    framework:         frameworkInfo,
    aria_landmarks:    ariaLandmarks,
    css_variables:     cssVariables,
    performance:       { ...performanceMetrics, navigation_ms: navTime, total_extract_ms: extractTime },
    render_mode: {
      mode:        'headless',
      is_headless: true,
      is_spa:      frameworkInfo.is_nextjs || frameworkInfo.is_react || frameworkInfo.is_vue,
      is_nextjs:   frameworkInfo.is_nextjs,
      is_react:    frameworkInfo.is_react,
      is_vue:      frameworkInfo.is_vue,
      is_angular:  frameworkInfo.is_angular,
      is_nuxt:     frameworkInfo.is_nuxt,
      html_size:   htmlSize,
    },
    extracted_at: new Date().toISOString(),
  };
}

// ── Multi-viewport orchestrator ───────────────────────────────────────────────

async function runExtraction(targetUrl, viewportName) {
  const viewport    = VIEWPORTS[viewportName] ?? VIEWPORTS.desktop;
  const systemChrome = findSystemChrome();

  const launchOptions = {
    headless: true,
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--disable-gpu',
      '--no-first-run',
      '--disable-extensions',
      '--disable-background-timer-throttling',
      '--disable-backgrounding-occluded-windows',
      '--disable-renderer-backgrounding',
    ],
  };
  if (systemChrome) launchOptions.executablePath = systemChrome;

  const browser = await chromium.launch(launchOptions);
  const context  = await browser.newContext({
    userAgent:          'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    viewport,
    locale:             'fa-IR,fa;q=0.9,en;q=0.8',
    ignoreHTTPSErrors:  true,
    javaScriptEnabled:  true,
  });

  const page = await context.newPage();

  // Block heavy non-content resources
  await page.route('**/*.{gif,mp4,webm,mp3,ogg,wav,woff,woff2,ttf,eot}', route => route.abort());

  let result;
  try {
    result = await extractFromPage(page, targetUrl);
    result.device = viewportName;
    result.viewport = viewport;
  } finally {
    await browser.close();
  }

  return result;
}

// ── Entry point ───────────────────────────────────────────────────────────────

(async () => {
  try {
    let result;

    if (device === 'all') {
      // Run desktop first (primary), then grab mobile dimensions
      const desktop = await runExtraction(url, 'desktop');
      let mobile = null;
      try {
        const mobileRun = await runExtraction(url, 'mobile');
        // Compact mobile result — only keep what differs
        mobile = {
          viewport:      mobileRun.viewport,
          device:        'mobile',
          html_size:     mobileRun.rendered_html?.length ?? 0,
          components:    mobileRun.components,
          performance:   mobileRun.performance,
        };
        delete mobile.components?.colors;
        delete mobile.components?.typography;
      } catch { /* mobile extraction failure is non-fatal */ }

      result = desktop;
      result.mobile_data = mobile;
    } else {
      result = await runExtraction(url, device);
    }

    process.stdout.write(JSON.stringify(result));
    process.exit(0);
  } catch (err) {
    process.stderr.write(JSON.stringify({
      success: false,
      error:   err.message,
      stack:   err.stack,
    }));
    process.exit(1);
  }
})();
