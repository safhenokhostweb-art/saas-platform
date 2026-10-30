# ممیزی مقایسه‌ای ویجت‌های Canvas — لوکال در برابر پروداکشن (۲۰۲۶-۱۰-۳۰)

دامنه: `packages/saas-platform/modules/theme-runtime` (رجیستری، رندرر، CSS، JS)،
ادیتور پلتفرم (`resources/views/admin/theme-builder`)، و در customer-site
`app/Services/ThemeCanvas`، پارشال style-contract، `resources/js/blocks` و
میرور vendor. روش: هش sha256 هر فایل پس از حذف CR (سرور LF است و لوکال CRLF)،
روی هر دو طرف، به‌علاوه اجرای مستقیم رندررها.

## ۱. ماتریس هم‌ترازی لوکال و سرور

| لایه | لوکال | سرور | نتیجه |
|---|---|---|---|
| کامیت saas-platform | `0d2fe3d5` (merge شاخه با `2188ffcd`) | `2188ffcd` (deploy ۲۲:۵۲) | روی مسیرهای ویجت هیچ diff کامیت‌شده‌ای نیست |
| کامیت customer-site | `7f449c9` (merge با `cd70095`) | `cd70095` (deploy ۲۲:۵۴) | روی مسیرهای ویجت هیچ diff کامیت‌شده‌ای نیست |
| پکیج theme-runtime (۷۹ فایل) | — | — | **یکسان بایت‌به‌بایت** |
| رجیستری و رندرر و CSS و JS و ادیتور پلتفرم (۱۰۵ فایل) | — | — | یکسان، به‌جز ۳ مورد WIP پایین |
| customer-site: ThemeCanvas و style-contract و JS و میرور vendor (۱۳۲ فایل) | — | — | **یکسان** (میرور بدون drift) |

**نتیجه:** روی سرور هیچ قابلیت، فیلد یا رفتاری وجود ندارد که لوکال نداشته باشد. پس
«مزیت سرور» جداگانه‌ای برای حفظ کردن وجود ندارد. هر تغییری که از این گزارش بیاید، از
مسیر عادی release می‌گذرد و چیزی را روی سرور پس نمی‌زند.

### فقط در لوکال (کامیت‌نشده؛ هیچ‌کدام ویجت یا فیلد جدیدی اضافه نمی‌کند)

| فایل | چه می‌کند | پیشنهاد |
|---|---|---|
| `resources/views/admin/theme-builder/canvas.blade.php` L3380 | در toast خطای آپلود رسانه، اگر `d.error` نبود، `d.message` (پیام اعتبارسنجی Laravel) را نشان می‌دهد | نگه‌داشتن و ارسال در WU-G |
| `app/Services/ThemeEngine/ThemeTokenRenderer.php` | تابع `sanitizeTokenValue()` روی توکن‌های داشبورد (`--platform-*`)؛ ربطی به ویجت‌ها ندارد | ناتمام است: تست‌های untracked آن قرمزند. یا کامل شود یا کنار برود |
| `app/Services/ThemeEngine/ResponsiveCssCompiler.php` و `resources/views/components/theme/node.blade.php` (untracked، ۲۰۲۶-۱۰-۲۵) | هیچ جایی ارجاع داده نشده‌اند (orphan) | stage نشوند؛ مالک تصمیم بگیرد: حذف یا اتصال |

## ۲. موجودی رجیستری canonical

- **۱۱۸ نوع بلاک**: layout ۱۸، content ۲۸، dynamic ۷، ecommerce ۶، marketing ۱۰، utility ۱۱، module ۳۸.
- هیچ فیلدی دیگر با textarea JSON ویرایش نمی‌شود (فاز 0.2 کامل است).
- فقط **۸ نوع** پراپ حرکتی (`autoplay`/`transition`/`animation`/`hover`) دارند: slider، video، blog_posts، product_slider، category_slider، testimonials_slider، module:motion، module:shop_product_slider.
- سیاست خروجی خالی: ۱۰۹ flagged، ۸ placeholder، ۱ optional (promo_banner).

## ۳. ماتریس ارزیابی تجاری

نشانه‌ها: ✅ در سطح تجاری · ⚠️ ناقص · ❌ غایب یا معیوب

| خانواده | ترنزیشن و انیمیشن | ریسپانسیو | توکن `--tb-*` | استیت‌های خطا، لودینگ و خالی |
|---|---|---|---|---|
| **اسلایدر** (`slider` و موتور `renderCarousel`) | ⚠️ فقط slide و fade، مدت‌ها inline (`.45s`/`.6s`)؛ ❌ `carousel.js` به `prefers-reduced-motion` اعتنا ندارد؛ ❌ autoplay در تب پنهان و خارج از دید ادامه می‌دهد؛ ❌ در حالت fade سوایپ غیرفعال است (`if (isFade) return`) | ✅ تعداد آیتم‌ها per-breakpoint؛ ❌ عنوان کپشن ثابت `1.75rem` و padding ثابت؛ ❌ تصویر جداگانه برای موبایل وجود ندارد | ❌ پس‌زمینه اسلاید `#111827`، کپشن `#fff`، overlay با rgba ثابت، دات غیرفعال `#d1d5db` (هم PHP L955 و هم `carousel.js` L125)، fallback تصویر با گرادیان indigo | ✅ fallback برای `onerror`، ولی با هندلر inline که با CSP سازگار نیست؛ ❌ همه اسلایدها `loading="lazy"`، حتی اسلاید اول (LCP)؛ srcset و fetchpriority نیست؛ ❌ `aria-label="اسلایدر {id}"` شناسه داخلی را لو می‌دهد (L981) |
| **هیرو** (`hero`، `hero_split`، `hero_gradient`، `hero_trust`) | ❌ هیچ انیمیشن ورودی یا پراپ حرکتی ندارند | ⚠️ اندازه تایپوگرافی fluid (`clamp`) نیست | ⚠️ پیش‌فرض‌ها توکن‌اند، ولی دکمه دوم `hero_gradient` با `rgba(255,255,255,.15/.4)` inline است | 🔴 **XSS — بخش ۴ را ببینید** |
| **بنر** (`promo_banner`) | ❌ ندارد: نه بستن (dismiss)، نه marquee، نه countdown | ✅ | ✅ از variant کلاس می‌گیرد | ✅ متن خالی یعنی خروجی خالی (policy optional) |
| **هدر** (`nav_header`، site-header.js) | ✅ sticky و shrink و transparent، overlay جستجو، کشوی سبد، focus trap؛ ✅ reduced-motion دارد | ⚠️ breakpoint منوی لمسی در JS `1025` است و در CSS `1023`/`1024` | ⚠️ `.tb-hdr-sticky.is-scrolled` و backdropها رنگ خام دارند | ✅ |
| `header_modern_v1` | ✅ CSS اختصاصی با reduced-motion | ✅ | ⚠️ chrome جدا (در roadmap §15 ثبت شده) | ✅ |
| **محصولات** (`product_slider`، `product_grid`، `module:shop_product_slider`) | ⚠️ فقط hover؛ transformهای hover زیر reduced-motion نیستند | ✅ | ⚠️ ستاره‌ها، stock و `.is-added` رنگ خام دارند | ✅ grid و blog و categories empty-state صادق دارند؛ ❌ `renderModuleProductSlider` (customer L649) با صفر محصول فقط عنوان را چاپ می‌کند (heading یتیم)؛ ❌ `renderProductSlider` (L1780) رشته خالی برمی‌گرداند |
| **سبد و فرم‌ها** (cart.js، site-forms.js) | ✅ | ✅ | ✅ | ✅ `aria-busy`، disabled، catch، aria-live؛ ⚠️ اسکلتون برای mini-cart نیست |
| **pricing.js و countdown** | ✅ | ✅ | ✅ | ⚠️ pricing.js نه live-region دارد نه catch |

### شاخص‌های کمّی سراسری

| شاخص | مقدار | ارزیابی |
|---|---|---|
| استفاده از `var(--tb-*)` در شیت مشترک | ۳۷۳ استفاده، ۳۵ توکن متمایز | ✅ |
| خطوط CSS با رنگ خام بیرون از `var()` | **۴۳** (hover shadowها، ستاره‌ها، backdropها، `.tb-bg-dark/light/gray`، `.tb-empty-state` با border `#d1d5db`) | ⚠️ |
| `style=` inline در `BlockRenderer.php` | **۹۸**؛ ۹۳ رنگ خام بیرون از `var()` در کد (بدون کامنت) در ۱۴ تابع. از این‌ها ۴۴ در `renderModulePlaceholder` (فقط پیش‌نمایش ادمین) و ۸ در `renderReferenceCounter` (بلاک مستنداتی و ثبت‌نشده) است؛ پس حدود **۳۴** رنگ به ویترین می‌رسد (جزئیات در §۷.۲) | ❌ مانع theming |
| مقادیر متمایز breakpoint | **۱۰ مقدار** (480، 500، 600، 639، 640، 767، 768، 1023، 1024، 1025)؛ صفر container query | ❌ مقیاس واحدی ندارد؛ بلاک داخل ستون نمی‌تواند خودش را تطبیق دهد |
| قواعد `prefers-reduced-motion` | ۳ در CSS و ۱ inline؛ کاروسل و hover کارت‌ها پوشش داده نشده‌اند | ⚠️ |
| `:focus-visible` در برابر `:hover` | ۴ در برابر ۳۳ | ❌ میکرواینترکشن‌ها فقط با موس دیده می‌شوند |
| تصاویر: حدود ۱۰۸ محل `<img>` | ۱۲ ویژگی lazy/srcset/decoding؛ تریت FoodMart با ۹۲ `<img>` و صفر lazy | ❌ |
| JSON-textarea | ۰ | ✅ |

## ۴. یافته بحرانی: XSS ذخیره‌شده در دکمه‌های دوم هیرو — ✅ رفع و مستقر شد (WU-A، ۲۰۲۶-۱۰-۳۱؛ §۶)

`BlockRenderer.php` در این فیلدهای `type: 'link'` به‌جای `escapeUrl()` از `escapeAttr()` استفاده می‌کند:

- `hero_split.button2_url` (L1943)
- `hero_gradient.button2_url` (L1975)
- `hero_trust.btn_primary_url` و `btn_secondary_url` (L4735، L4737)

اثبات اجرا (۲۰۲۶-۱۰-۳۰):
- در پلتفرم، `renderBlock()` خروجی `href="javascript:alert(1)"` داد، در حالی که `button_url` همان بلاک درست به `#` تبدیل شد.
- در customer-site، `ThemeCanvasRenderer::renderBlock()` برای هر سه بلاک همان `javascript:` را چاپ کرد. یعنی اعتبارسنجی render-time هم جلویش را نمی‌گیرد.

چون کد سرور یکسان است، این آسیب‌پذیری **روی ویترین زنده** فعال است. هر مسیری که تم ذخیره می‌کند (ایمپورت تم، تولیدکننده AI، seederها، API ادیتور) می‌تواند اسکریپتی بکارد که با یک کلیک اجرا می‌شود. این همان کلاس C3 در roadmap §2 است که این چهار فیلد از آن جا مانده‌اند.

## ۵. واحدهای کار پیشنهادی (به ترتیب اولویت؛ هر کدام چرخه ۸ فازی WORK_UNIT_DELIVERY_WORKFLOW)

| WU | اولویت | محتوا | پذیرش |
|---|---|---|---|
| **A** | P0 امنیت | `escapeUrl()` برای ۴ فیلد بالا. تست رگرسیون **کلاسی**: روی همه فیلدهای `type: 'link'` در `BlockRegistry::all()` مقدار `javascript:alert(1)` رندر شود و هیچ `href`/`src` با `javascript:` بیرون نیاید | تست جدید در هر دو host سبز؛ re-mirror vendor؛ `deployment.readiness --strict` بدون blocker |
| **B** | P1 اسلایدر | در `carousel.js`: احترام به reduced-motion (autoplay و transition خاموش)؛ توقف با `visibilitychange` و IntersectionObserver؛ سوایپ در حالت fade. اسلاید اول با `loading="eager"` و `fetchpriority="high"` و بقیه lazy. استایل‌های inline اسلاید و دات‌ها به کلاس‌های شیت مشترک منتقل شوند. `aria-label` از عنوان بلاک ساخته شود | هارنس CSS parity (تغییر markup: diff ساختاری corpus)؛ Playwright در ۳ عرض؛ تست reduced-motion با `emulateMedia` |
| **C** | P1 توکن | ۹۸ استایل inline و ۴۳ خط CSS به `var(--tb-*, fallback)`؛ توکن‌های کم‌شده (`--tb-overlay`، `--tb-border`، `--tb-star`، `--tb-backdrop`) در `ThemeTokenNormalizer` با کنتراست WCAG-AA | computed-style parity برای تم‌های موجود باید بدون تغییر بماند (fallback برابر مقدار قدیم) |
| **D** | P2 ریسپانسیو | یک مقیاس breakpoint مستند (480/768/1024/1280)؛ `clamp()` برای عنوان‌های hero و slider و section؛ container query برای کارت‌ها داخل `columns`/`grid` | اسکرین‌شات ۳۶۰/۷۶۸/۱۲۸۰ بدون overflow افقی |
| **E** | P2 استیت‌ها | empty-state صادق برای `renderModuleProductSlider` و `renderProductSlider` (هم‌شکل grid)؛ border کلاس `.tb-empty-state` به توکن؛ promo_banner با dismiss (localStorage) و حالت marquee/countdown اختیاری؛ اسکلتون mini-cart؛ live-region برای pricing.js | تست feature برای کاتالوگ خالی؛ axe بدون خطا |
| **F** | P2 میکرواینترکشن | برای هر `:hover` یک `:focus-visible` هم‌ارز؛ hover-transformها زیر reduced-motion؛ پراپ اختیاری `animation` (`none`/`fade-up`/`zoom`) با یک reveal مشترک بر پایه IntersectionObserver | پیش‌فرض `none` تا تم‌های موجود عوض نشوند |
| **G** | نگهداری | ارسال اصلاح toast آپلود؛ تصمیم درباره WIP سانیتایز توکن (کامل یا حذف)؛ حذف یا اتصال دو فایل orphan؛ `loading="lazy"` در تریت FoodMart | سوئیت baseline پلتفرم بدون failure جدید |

پیشنهاد ترتیب: **A** همین حالا و جدا از بقیه (کوچک و مستقل، و تنها موردی که روی پروداکشن ریسک فعال دارد). بعد **B** و **C** با هم، چون هر دو به استایل‌های inline اسلایدر دست می‌زنند و یک‌بار اجرای هارنس parity کافی است. سپس D و E و F.

> اصلاح ۲۰۲۶-۱۰-۳۱: B و C دو واحد جدا می‌مانند و هم‌پوشانی ندارند. B بخش اسلایدر و کاروسل را با توکن و fallback برابر مقدار قدیم می‌نویسد، و C آن بخش را کنار می‌گذارد (§۷).

## ۶. ثبت تحویل WU-A (۲۰۲۶-۱۰-۳۱)

| مورد | مقدار |
|---|---|
| task_id | `wu-a-link-scheme-xss` |
| تغییر | `linkHref()` برای `hero_split`/`hero_gradient.button2_url` و `hero_trust.btn_primary_url`/`btn_secondary_url`. `HtmlSanitizer::isValidUrl()` حالا scheme را پس از حذف `\t\n\r` بررسی می‌کند. **یافته اضافه**: `java<TAB>script:` از همه فراخوانی‌های `escapeUrl()` در هر دو سایت عبور می‌کرد. خط fallback پیام آپلود در `canvas.blade.php` |
| تست | `LinkFieldUnsafeSchemeSweepTest` در هر دو ریپو: همه فیلدهای `link` و فیلدهای نام‌دار URL، در سطح اول و داخل آیتم‌های repeater، با ۹ payload ناامن و یک probe سالم برای جلوگیری از عبور توخالی. روی `2188ffcd` قرمز با ۹۶ مورد؛ پس از پچ سبز |
| اعتبارسنجی | پلتفرم ۱۰۲۵ تست (فقط ۷ خطای شناخته‌شده WIP توکن)؛ customer ۸۱۰ تست (فقط ۳ خطای شناخته‌شده)؛ corpus هر دو host قبل و بعد پچ بایت‌به‌بایت یکسان؛ readiness با ۰ blocker |
| کامیت توسعه | saas `100dd85a`، customer `2b09c351` |
| کامیت release | saas `b92570d2` (base `2188ffcd`)، customer `a717b684` (وابسته به saas `b92570d2`) |
| استقرار | با تأیید مالک از Hub؛ روی سرور به‌صورت فقط‌خواندنی بررسی شد: saas `b92570d2` (۰۱:۰۹) و customer `a717b684` (۰۱:۱۰) و هر دو نسخه `HtmlSanitizer` پچ را دارند |

## ۷. آماده‌سازی واحدهای B و C

### پیش‌نیازهای مشترک (پیش از اولین ویرایش)

1. پیش از ویرایش، baseline هارنس CSS parity را از **کامیت صریح** `b92570d2` بسازید، نه از HEAD (`tests/Support/CssParity/README.md`؛ corpus و css برای هر دو host).
2. پس از **هر** ویرایش CSS پکیج، میرور vendor در customer-site دوباره نصب شود (`composer reinstall saas-platform/modules-theme-runtime`)، وگرنه after.css کهنه می‌ماند.
3. هم‌زمان هیچ نشست دیگری PHPUnit روی `customer_site_test` اجرا نکند. اگر یک spec مرورگر شکست خورد، بلاک‌های درج‌شده‌اش از fixture theme 193 پاک شوند (baseline: ۲۱ بلاک home).
4. پیش از اجرای مرورگر، latency سرور dev روی `/login` زیر ۱ ثانیه باشد.

### ۷.۱ واحد B — کنترلر اسلایدر (آماده پیاده‌سازی)

**دامنه:** موتور مشترک `renderCarousel()` (`BlockRenderer.php` L911–1011)، `renderSlider()` (L1209–1284)، `imageWithFallback()` (L1167–1182)، `resources/js/blocks/carousel.js`، و `theme-runtime-blocks.css`. موتور ۱۰ فراخواننده دارد: ۶ در پکیج (slider L1270، client_logos L2368، blog_posts L2728، product_slider L2767، category_slider L2950، testimonials_slider L3171) و ۴ override در customer-site (`ThemeCanvasRenderer` L476، L658، L1821، L1878). همه از همان موتور رد می‌شوند، پس تغییر یک‌جا اعمال می‌شود.

| # | تغییر | جزئیات |
|---|---|---|
| B1 | احترام به reduced-motion | `carousel.js` با `matchMedia('(prefers-reduced-motion: reduce)')` autoplay را خاموش می‌کند و به `change` گوش می‌دهد. transition ترَک از inline به کلاس CSS منتقل می‌شود تا `@media (prefers-reduced-motion)` بتواند آن را خنثی کند |
| B2 | توقف هوشمند | با `visibilitychange` (تب پنهان) و `IntersectionObserver` (کمتر از ۱۰٪ دیده‌شده) مکث، و پس از برگشت ادامه، مگر اینکه کاربر دستی مکث کرده باشد (`manuallyPaused`) |
| B3 | سوایپ در حالت fade | `pointerdown/up` در حالت fade هم اسلاید را عوض کند، بدون جابه‌جایی ترَک (حذف `if (isFade) return` و جایگزینی با شاخه fade) |
| B4 | LCP | در `renderSlider` تصویر اسلاید اول با `loading="eager" fetchpriority="high" decoding="async"` و بقیه `lazy`. پارامتر اختیاری `$priority` به `imageWithFallback()` اضافه شود (پیش‌فرض فعلی بدون تغییر). کاروسل‌های محصول و بلاگ دست نمی‌خورند |
| B5 | inline به کلاس | `.tb-slide`، `.tb-slide-overlay`، `.tb-slide-caption/-title/-subtitle`، `.tb-slide-cta`، `.tb-carousel-track` و `.tb-carousel--fade .tb-carousel-slide`، `.tb-carousel-dots`، `.tb-carousel-dot` و `.is-active`، `.tb-slider-boxed`. رنگ‌ها به شکل `var(--tb-…, <مقدار قدیم>)` نوشته شوند: پس‌زمینه اسلاید `#111827`، کپشن `#fff`، دات `#d1d5db`، دات فعال `var(--tb-primary,#4f46e5)`. در `carousel.js` (L125) به‌جای `style.background` کلاس `is-active` toggle شود |
| B6 | برچسب دسترس‌پذیری | `aria-label` کاروسل از پراپ `title` بلاک ساخته شود، وگرنه فقط «اسلایدر» (بدون شناسه، L981). شماره اسلایدها با `displayNumber()` به رقم فارسی |
| B7 | کپشن fluid | عنوان `clamp(1.25rem, 1rem + 2vw, 2.25rem)` و padding کپشن `clamp(1.25rem, 4vw, 2.5rem)`. بقیه اندازه‌ها بدون تغییر |

**تست‌هایی که آگاهانه به‌روز می‌شوند:**
- `saas-platform tests/Unit/ThemeBuilder/SliderRenderersTest.php` L104: `max-width:72rem` به کلاس `tb-slider-boxed` تبدیل می‌شود.
- `customer-site tests/Feature/ThemeCanvas/SliderRenderTest.php` L150: شمارش رشته دقیق `class="tb-carousel-dot"` به XPath `contains(@class,"tb-carousel-dot")` تبدیل می‌شود.

**تست‌های جدید:**
- unit: اسلاید اول eager، شناسه در `aria-label` نباشد، و `renderSlider` هیچ رنگ خامِ بیرون از `var()` نداشته باشد.
- `tests/js/carousel-motion.test.mjs`: reduced-motion، visibilitychange، و سوایپ fade.
- Playwright: spec `phase1-2-header-footer-carousel` به‌علاوه `page.emulateMedia({ reducedMotion: 'reduce' })` در ۳ عرض (۳۶۰/۷۶۸/۱۲۸۰).

**پذیرش:**
- هارنس parity کامل در هر دو host. تنها diff ساختاری مجاز، بلاک‌های اسلایدر و کاروسل است (تغییر markup).
- computed-style همه ۲۹ snapshot صفر diff داشته باشد، به‌جز `font-size` و `padding` کپشن اسلایدر (B7 تغییر بصری عمدی است و باید جداگانه در گزارش ثبت شود).
- `corpus.php` با exit 0.

### ۷.۲ واحد C — توکن‌های تجاری (آماده پیاده‌سازی، پس از B)

**قاعده صفر-diff:** هر جایگزینی به شکل `var(--tb-<token>, <مقدار قدیم>)` است. توکن‌های تازه را `ThemeTokenNormalizer` **فقط وقتی تم آن‌ها را تعریف کند** emit می‌کند؛ پس هیچ تم موجودی تغییر بصری نمی‌بیند و هارنس باید برای همه snapshotها صفر diff نشان دهد.

**توکن‌های تازه** (هیچ‌کدام امروز وجود ندارند؛ ۲۵ توکن فعلی شامل `--tb-border` و `--tb-shadow-sm/md/lg` هستند):

| توکن | مقدار fallback (همان مقدار قدیم) | مصرف |
|---|---|---|
| `--tb-overlay` | `rgba(0,0,0,.55)` | overlay تصویر و هیرو |
| `--tb-backdrop` | هر محل fallback خودش را نگه می‌دارد: جستجو `rgba(15,23,42,.55)` (L610) و کشوی سبد `rgba(15,23,42,.5)` (L701) | `.tb-search-overlay__backdrop`، `.tb-cart-drawer__backdrop` |
| `--tb-star` | `#f59e0b` | ستاره‌های امتیاز (`.tb-testimonial-stars`، `.tb-review-stars`، `.tb-product-stars`) |
| `--tb-border-strong` | `#d1d5db` | `.tb-empty-state`، inputها، `.tb-star.is-off` |
| `--tb-media-placeholder` | `linear-gradient(135deg,#eef2ff,#f5f3ff)` | `imageWithFallback()` و `*-noimg` |

**اهداف به ترتیب** (شمارش رنگ خام بیرون از `var()` در کد):

| تابع یا فایل | تعداد | نکته |
|---|---|---|
| `renderBentoGrid` | ۷ | |
| `imageWithFallback` | ۶ | گرادیان indigo به `--tb-media-placeholder` |
| `renderHeaderModernV1` | ۴ | chrome اختصاصی؛ فقط رنگ‌ها |
| `renderContactSplit` | ۳ | |
| `renderHeroGradient`، `renderStatsRow`، `renderNavFooter` | ۲+۲+۲ | |
| `renderModal`، `renderCategorySlider` | ۱+۱ | |
| `theme-runtime-blocks.css` | ۴۳ خط | فهرست در §۳ (hover shadowها، ستاره‌ها، backdropها، `.tb-bg-*`، `.tb-empty-state`) |
| `buildCssDeclarations` | ۷ | اختیاری: presetهای سایه sm/md/lg به `var(--tb-shadow-*, <قدیم>)`؛ `xl` توکن ندارد |

**بیرون از دامنه C** (عمداً):
- `renderSlider`/`renderCarousel`: در B انجام می‌شود.
- `renderModulePlaceholder` (۴۴ رنگ): فقط پیش‌نمایش ادمین است، چون customer-site بلاک‌های module را به رندرر واقعی می‌سپارد.
- `renderReferenceCounter` (۸ رنگ): بلاک مستنداتی و ثبت‌نشده.

**تست:**
- `ThemeTokenNormalizerContrastTest` برای هر توکن تازه‌ای که رنگ متن یا آیکن است (`--tb-star` روی surface، با کنتراست ≥ ۳:۱ برای عناصر غیرمتنی).
- تست unit که بشمارد هیچ رنگ خامی بیرون از `var()` در توابع هدف باقی نمانده باشد. این تست ابزار بازگشت‌ناپذیری است و از رشد دوباره رنگ‌های خام جلوگیری می‌کند.

**پذیرش:** هارنس parity در هر دو host با صفر diff computed-style و صفر diff ساختاری روی هر ۲۹ snapshot، به‌علاوه یک تم آزمایشی که همه توکن‌های تازه را تعریف می‌کند و تغییرش در اسکرین‌شات دیده می‌شود.
