/**
 * Headless Visual Extractor — TB3 Sprint 11
 * Uses Playwright/Chromium to render SPA/Next.js sites and extract full DOM.
 *
 * Usage: node extract.mjs <url> [timeout_ms]
 * Output: JSON to stdout
 * Errors: JSON to stderr, exit code 1
 */

import { chromium } from 'playwright';
import { existsSync } from 'fs';
import os from 'os';

const url        = process.argv[2];
const timeoutMs  = parseInt(process.argv[3] || '30000');

if (!url) {
  process.stderr.write(JSON.stringify({ success: false, error: 'URL is required as first argument' }));
  process.exit(1);
}

/**
 * Find a usable system Chrome/Edge when Playwright's own Chromium is not installed.
 * Prioritizes: env override → system Chrome → Edge.
 */
function findSystemChrome() {
  // Honor explicit env override
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

  return null; // Use Playwright's bundled Chromium
}

async function extract(targetUrl) {
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
    ],
  };

  if (systemChrome) {
    launchOptions.executablePath = systemChrome;
  }

  const browser = await chromium.launch(launchOptions);

  const context = await browser.newContext({
    userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    viewport: { width: 1440, height: 900 },
    locale: 'fa-IR,fa;q=0.9,en;q=0.8',
    ignoreHTTPSErrors: true,
  });

  const page = await context.newPage();

  // Block heavy resources not needed for DOM extraction
  await page.route('**/*.{gif,mp4,webm,mp3,ogg,wav}', route => route.abort());

  // Navigate
  let finalUrl = targetUrl;
  try {
    const response = await page.goto(targetUrl, {
      waitUntil: 'networkidle',
      timeout: timeoutMs,
    });
    finalUrl = page.url();
  } catch {
    try {
      await page.goto(targetUrl, { waitUntil: 'load', timeout: timeoutMs });
      await page.waitForTimeout(2500);
      finalUrl = page.url();
    } catch (err2) {
      throw new Error(`Navigation failed: ${err2.message}`);
    }
  }

  // Extra wait for heavy SPAs
  await page.waitForTimeout(1500);

  // Screenshot (desktop viewport, no full page — fast)
  let screenshotB64 = '';
  try {
    const screenshot = await page.screenshot({ type: 'png', fullPage: false, timeout: 10000 });
    screenshotB64 = screenshot.toString('base64');
  } catch {
    // non-fatal
  }

  // Full rendered HTML
  const renderedHtml = await page.content();

  // Detect framework
  const frameworkInfo = await page.evaluate(() => ({
    is_nextjs:  !!(window.__NEXT_DATA__ || document.getElementById('__NEXT_DATA__')),
    is_react:   !!(window.React || document.querySelector('[data-reactroot]')),
    is_vue:     !!(window.__vue__ || document.querySelector('[data-v-]')),
    is_angular: !!(window.ng || document.querySelector('[ng-version]')),
    html_size:  document.documentElement.outerHTML.length,
  }));

  // Structured component extraction
  const components = await page.evaluate(() => {
    const result = {
      meta: {
        title:       document.title,
        description: document.querySelector('meta[name="description"]')?.content ?? '',
        og_image:    document.querySelector('meta[property="og:image"]')?.content ?? '',
        og_title:    document.querySelector('meta[property="og:title"]')?.content ?? '',
        language:    document.documentElement.lang ?? '',
        is_rtl:      document.documentElement.dir === 'rtl' || getComputedStyle(document.body).direction === 'rtl',
        h1:          document.querySelector('h1')?.textContent?.trim()?.substring(0, 150) ?? '',
        h2_list:     [...document.querySelectorAll('h2')].map(e => e.textContent.trim().substring(0, 100)).slice(0, 8),
      },

      nav: {
        items:         [],
        has_mega_menu: false,
        is_sticky:     false,
        has_search:    false,
        has_cart:      false,
      },

      hero: {
        headline:     '',
        subheadline:  '',
        cta_buttons:  [],
        has_image:    false,
        has_video:    false,
        has_gradient: false,
        layout:       'centered',
      },

      product_grid: {
        count:    0,
        columns:  0,
        card_html:'',
        has_price: false,
        has_badge: false,
        has_image: false,
        has_rating:false,
      },

      cards: { types: [], total: 0, has_hover: false },

      mega_menu: {
        detected: false,
        columns:  0,
        has_icons:false,
        sub_items:[],
      },

      sliders:  [],
      filters:  { found: false, type: '', items: [] },
      search:   { found: false, placeholder: '', is_prominent: false },
      footer:   { links: [], has_social: false, columns: 0, has_newsletter: false },

      colors:   [],
      typography: { fonts: [], base_size: '', weights: [] },
      sections_detected: [],
    };

    // ── Navigation ────────────────────────────────────────────────────────────
    const navEl   = document.querySelector('nav, header, [role="navigation"]');
    const navLinks = document.querySelectorAll('nav a, header a, [role="navigation"] a');
    const seen    = new Set();
    navLinks.forEach(a => {
      const t = a.textContent.trim();
      if (t && t.length > 0 && t.length < 60 && !seen.has(t)) {
        seen.add(t);
        result.nav.items.push(t);
      }
    });
    result.nav.items = result.nav.items.slice(0, 25);

    if (navEl) {
      const style = window.getComputedStyle(navEl);
      result.nav.is_sticky  = style.position === 'fixed' || style.position === 'sticky';
    }
    result.nav.has_cart   = !!document.querySelector('[class*="cart"], [class*="basket"], [class*="bag"]');
    result.nav.has_search = !!document.querySelector('nav input[type="search"], nav input[type="text"], header input');

    // Mega menu
    const megaSels = ['[class*="mega"]', '[class*="flyout"]', '[class*="dropdown-menu"]', '[class*="nav-dropdown"]', '[data-dropdown]', '[aria-haspopup="true"]'];
    for (const s of megaSels) {
      const el = document.querySelector(s);
      if (el) {
        result.nav.has_mega_menu  = true;
        result.mega_menu.detected = true;
        const subLinks = el.querySelectorAll('a');
        subLinks.forEach(a => {
          const t = a.textContent.trim();
          if (t && t.length < 60) result.mega_menu.sub_items.push(t);
        });
        result.mega_menu.sub_items = result.mega_menu.sub_items.slice(0, 30);
        result.mega_menu.has_icons = !!el.querySelector('svg, img, i[class*="icon"]');
        const cols = el.querySelectorAll('[class*="col"]');
        result.mega_menu.columns = Math.min(cols.length || 3, 6);
        break;
      }
    }

    // ── Hero ─────────────────────────────────────────────────────────────────
    const heroSels = [
      '[class*="hero"]', '[class*="banner"]', '[class*="jumbotron"]',
      '[class*="intro"]', '[class*="landing"]', 'section:first-of-type',
      'main > *:first-child', '#hero', '#home',
    ];
    for (const sel of heroSels) {
      const el = document.querySelector(sel);
      if (!el) continue;
      const h1 = el.querySelector('h1') || document.querySelector('h1');
      if (!h1) continue;
      result.hero.headline    = h1.textContent.trim().substring(0, 150);
      const p = el.querySelector('p, h2, [class*="subtitle"], [class*="tagline"]');
      result.hero.subheadline = (p?.textContent ?? '').trim().substring(0, 250);
      const btns = [...el.querySelectorAll('a, button')];
      btns.forEach(b => {
        const t = b.textContent.trim();
        if (t && t.length > 0 && t.length < 60) result.hero.cta_buttons.push(t);
      });
      result.hero.cta_buttons = [...new Set(result.hero.cta_buttons)].slice(0, 6);
      result.hero.has_image   = !!el.querySelector('img, [style*="background-image"]');
      result.hero.has_video   = !!el.querySelector('video, iframe[src*="youtube"], iframe[src*="vimeo"]');
      const style = window.getComputedStyle(el);
      result.hero.has_gradient = style.backgroundImage.includes('gradient');
      if (el.querySelector('[class*="split"], [class*="two-col"]')) result.hero.layout = 'split';
      else if (el.querySelector('[class*="center"], [class*="text-center"]')) result.hero.layout = 'centered';
      break;
    }

    // ── Product Grid ─────────────────────────────────────────────────────────
    const productSels = [
      '[class*="product-card"]', '[class*="product-item"]', '[class*="ProductCard"]',
      '[class*="product_card"]', '[class*="goods-item"]', '[class*="goods-card"]',
      '[class*="item-card"]', '[data-testid*="product"]', '[data-test*="product"]',
      '[class*="shop-item"]', '[class*="catalog-item"]',
    ];
    for (const sel of productSels) {
      const cards = document.querySelectorAll(sel);
      if (cards.length >= 2) {
        result.product_grid.count    = cards.length;
        result.product_grid.card_html = cards[0].innerHTML.substring(0, 800);
        result.product_grid.has_price = !!cards[0].querySelector('[class*="price"], [class*="cost"], [class*="قیمت"]');
        result.product_grid.has_badge = !!cards[0].querySelector('[class*="badge"], [class*="label"], [class*="tag"]');
        result.product_grid.has_image = !!cards[0].querySelector('img');
        result.product_grid.has_rating = !!cards[0].querySelector('[class*="star"], [class*="rating"], [class*="score"]');

        // Column count heuristic
        try {
          const rect0 = cards[0].getBoundingClientRect();
          const rect1 = cards[1].getBoundingClientRect();
          if (rect0.width > 0) {
            const parentW = cards[0].parentElement?.getBoundingClientRect()?.width || 1440;
            result.product_grid.columns = Math.max(1, Math.round(parentW / rect0.width));
          }
        } catch { result.product_grid.columns = 4; }
        break;
      }
    }

    // ── Generic Cards ─────────────────────────────────────────────────────────
    const allCards = document.querySelectorAll('[class*="card"], [class*="tile"]');
    result.cards.total    = allCards.length;
    result.cards.has_hover = [...allCards].some(c => {
      const s = window.getComputedStyle(c);
      return s.transition !== 'all 0s ease 0s' && s.transition !== '';
    });
    if (document.querySelector('[class*="product-card"], [class*="goods-item"]')) result.cards.types.push('product');
    if (document.querySelector('[class*="blog-card"], [class*="post-card"], [class*="article-card"]')) result.cards.types.push('blog');
    if (document.querySelector('[class*="feature-card"], [class*="service-card"], [class*="icon-box"]')) result.cards.types.push('feature');
    if (document.querySelector('[class*="team-card"], [class*="member-card"], [class*="staff"]')) result.cards.types.push('team');
    if (document.querySelector('[class*="pricing-card"], [class*="plan-card"], [class*="price-box"]')) result.cards.types.push('pricing');
    if (document.querySelector('[class*="testimonial"], [class*="review-card"]')) result.cards.types.push('testimonial');

    // ── Search ────────────────────────────────────────────────────────────────
    const searchEl = document.querySelector('input[type="search"], [class*="search"] input, input[placeholder*="search" i], input[placeholder*="جستجو"]');
    if (searchEl) {
      result.search.found        = true;
      result.search.placeholder  = searchEl.placeholder ?? '';
      const rect = searchEl.getBoundingClientRect();
      result.search.is_prominent = rect.width > 200;
    }

    // ── Filters ───────────────────────────────────────────────────────────────
    const filterSels = ['[class*="filter"]', '[class*="facet"]', '[class*="sidebar"] ul', '[class*="refinement"]', '[class*="category-tree"]'];
    for (const s of filterSels) {
      const el = document.querySelector(s);
      if (el) {
        result.filters.found = true;
        result.filters.type  = s;
        const items = el.querySelectorAll('a, li, button, label');
        items.forEach(i => {
          const t = i.textContent.trim().substring(0, 60);
          if (t) result.filters.items.push(t);
        });
        result.filters.items = [...new Set(result.filters.items)].slice(0, 20);
        break;
      }
    }

    // ── Sliders ───────────────────────────────────────────────────────────────
    const sliderKeywords = ['swiper', 'splide', 'owl', 'slick', 'glide', 'flickity', 'embla', 'carousel', 'slider'];
    sliderKeywords.forEach(kw => {
      if (document.querySelector(`[class*="${kw}"], .${kw}`)) result.sliders.push(kw);
    });
    result.sliders = [...new Set(result.sliders)];

    // ── Footer ────────────────────────────────────────────────────────────────
    const footerEl = document.querySelector('footer, [class*="footer"]');
    if (footerEl) {
      const seenLinks = new Set();
      footerEl.querySelectorAll('a').forEach(a => {
        const t = a.textContent.trim().substring(0, 60);
        if (t && t.length > 0 && !seenLinks.has(t)) { seenLinks.add(t); result.footer.links.push(t); }
      });
      result.footer.links = result.footer.links.slice(0, 40);
      result.footer.has_social = /instagram|twitter|facebook|telegram|linkedin|youtube/i.test(footerEl.innerHTML);
      result.footer.has_newsletter = !!footerEl.querySelector('input[type="email"], [class*="newsletter"], [class*="subscribe"]');
      const cols = footerEl.querySelectorAll('[class*="col"]:not([class*="row"]), [class*="column"]');
      const directDivs = footerEl.querySelectorAll(':scope > div > div');
      result.footer.columns = Math.min(Math.max(cols.length, directDivs.length), 6);
    }

    // ── Sections Detected ────────────────────────────────────────────────────
    const sectionKeywords = {
      header:       ['header', 'nav', 'navbar'],
      hero:         ['hero', 'banner', 'jumbotron', 'intro'],
      features:     ['feature', 'benefit', 'why-us', 'advantage'],
      products:     ['product', 'goods', 'catalog', 'shop'],
      categories:   ['categor', 'department', 'section'],
      pricing:      ['pric', 'plan', 'tariff', 'subscription'],
      testimonials: ['testimonial', 'review', 'feedback'],
      blog:         ['blog', 'news', 'article', 'post'],
      faq:          ['faq', 'question', 'accordion'],
      cta:          ['cta', 'call-to-action', 'get-started', 'signup'],
      footer:       ['footer'],
    };
    const allText = document.documentElement.innerHTML.toLowerCase();
    for (const [section, keywords] of Object.entries(sectionKeywords)) {
      if (keywords.some(k => allText.includes(k))) result.sections_detected.push(section);
    }

    // ── Color Extraction ─────────────────────────────────────────────────────
    const colorEls = [
      document.body,
      document.querySelector('nav, header'),
      document.querySelector('h1'),
      document.querySelector('a.btn, button, .btn-primary, [class*="btn-primary"], [class*="button-primary"]'),
      document.querySelector('[class*="card"]:not([class*="blog"])'),
      document.querySelector('[class*="hero"], section:first-of-type'),
    ].filter(Boolean);

    const colorSet = new Set();
    colorEls.forEach(el => {
      ['backgroundColor', 'color'].forEach(prop => {
        const val = window.getComputedStyle(el)[prop];
        if (!val || val === 'transparent' || val === 'rgba(0, 0, 0, 0)') return;
        const m = val.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
        if (m) {
          const hex = '#' + [m[1], m[2], m[3]].map(n => parseInt(n).toString(16).padStart(2, '0')).join('');
          if (hex !== '#000000' || prop === 'color') colorSet.add(hex);
        }
      });
    });
    result.colors = [...colorSet].slice(0, 15);

    // ── Typography ────────────────────────────────────────────────────────────
    const bodyStyle = window.getComputedStyle(document.body);
    result.typography.base_size = bodyStyle.fontSize;
    const fontSet = new Set();
    [document.body, document.querySelector('h1'), document.querySelector('p')].filter(Boolean).forEach(el => {
      const ff = window.getComputedStyle(el).fontFamily;
      if (ff) fontSet.add(ff.split(',')[0].trim().replace(/['"]/g, ''));
    });
    result.typography.fonts   = [...fontSet].slice(0, 5);
    result.typography.weights = [...new Set(
      [...document.querySelectorAll('h1,h2,h3,strong,b,button')].map(e => window.getComputedStyle(e).fontWeight)
    )].slice(0, 5);

    return result;
  });

  await browser.close();

  return {
    success:          true,
    url:              finalUrl,
    original_url:     targetUrl,
    rendered_html:    renderedHtml,
    screenshot_base64: screenshotB64,
    components,
    framework:        frameworkInfo,
    render_mode: {
      mode:        'headless',
      is_headless: true,
      is_spa:      frameworkInfo.is_nextjs || frameworkInfo.is_react || frameworkInfo.is_vue,
      html_size:   renderedHtml.length,
    },
    extracted_at: new Date().toISOString(),
  };
}

// ── Entry point ───────────────────────────────────────────────────────────────

(async () => {
  try {
    const result = await extract(url);
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
