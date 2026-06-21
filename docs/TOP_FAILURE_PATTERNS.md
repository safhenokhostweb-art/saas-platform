# TOP FAILURE PATTERNS

> Generated: 2026-06-21 22:10
> Based on real-world validation of 5 site categories

---

## Observed Failures

### FP-001: Header Not Detected

**Severity:** HIGH  
**Affected sites:** Shopify (Landing Page)  

**Root Cause:**  
SPA sites render header via JS → raw HTML has no `<header>` tag. Playwright renders it but DOMXPath may miss non-semantic divs.

**Required Fix:**  
Improve semantic fallback in DomExtractionService: detect role="banner" + sticky/fixed nav divs. Use CSS class heuristics (e.g. `.navbar`, `.site-header`).

---

### FP-002: Footer Not Detected

**Severity:** MEDIUM  
**Affected sites:** Shopify (Landing Page)  

**Root Cause:**  
Footer in SPA sites is a React component injected after hydration. Raw HTML fetch returns empty `<footer>`.

**Required Fix:**  
Add CSS class fallback detector for `.footer`, `#footer`, `[role="contentinfo"]` in DomExtractionService::detectFooter().

---

### FP-003: Navigation Items Not Extracted

**Severity:** HIGH  
**Affected sites:** Amazon AWS (Corporate), Amazon (E-commerce), Stripe (Service/SaaS), Maktabkhooneh (Education), Shopify (Landing Page)  

**Root Cause:**  
Mega-menus and JS-driven navigation are not present in the static DOM. Nav links are loaded via API or injected by React.

**Required Fix:**  
In headless mode, use `document.querySelectorAll("nav a")` after SPA hydration. Store extracted nav in playwright payload.

---

### FP-004: Similarity Below Target

**Severity:** MEDIUM  
**Affected sites:** Shopify (Landing Page) (0% < 70%)  

**Root Cause:**  
Sites with minified CSS, Tailwind JIT, or CSS-in-JS have no extractable color variables. Color distance algorithm scores low.

**Required Fix:**  
Enhance color extraction: parse `window.getComputedStyle` on key elements (buttons, headers) in headless mode. Store in `css_variables`.

---

### FP-005: Marketplace Publish Failed

**Severity:** HIGH  
**Affected sites:** Shopify (Landing Page)  

**Root Cause:**  
DB constraint violations (missing FK, null columns) or Theme model validation failure.

**Required Fix:**  
Add try/catch with graceful degradation in marketplace publish stage. Log to `KNOWN_ISSUES.md`.

---

### FP-006: Canvas Editing Incompatible

**Severity:** HIGH  
**Affected sites:** Shopify (Landing Page)  

**Root Cause:**  
AI-generated ThemePack uses non-canonical block types not registered in BlockRegistry. Props schema mismatch.

**Required Fix:**  
Add block type normalization step in ReverseThemeReconstructorService: map unknown types to closest canonical type. Validate against BlockRegistry::getSchema().

---

### FP-007: No Responsive Support Detected

**Severity:** MEDIUM  
**Affected sites:** Amazon AWS (Corporate), Amazon (E-commerce), Stripe (Service/SaaS), Maktabkhooneh (Education), Shopify (Landing Page)  

**Root Cause:**  
ThemePack generated without responsive_settings per block. Global styles missing breakpoints.

**Required Fix:**  
In ReverseThemeReconstructorService::buildGlobalStyles(): always inject breakpoints (sm:640, md:768, lg:1024, xl:1280).

---

### FP-008: SPA Shell Detected (Raw HTML Too Small)

**Severity:** CRITICAL  
**Affected sites:** Shopify (Landing Page) (0 bytes raw HTML)  

**Root Cause:**  
Modern SPA/Next.js sites return < 30KB HTML. All content is injected by JavaScript. HTTP extraction captures empty shell.

**Required Fix:**  
Auto-trigger Playwright headless when html_size < 30KB. Already partially implemented — ensure needsHeadless() threshold is correct.

---

## General Recommendations

| Priority | Recommendation |
|----------|---------------|
| P0 | Always run Playwright for SPA/Next.js sites (auto-detect html_size < 30KB) |
| P0 | Add `document.querySelectorAll('nav a')` extraction in headless extract.mjs |
| P1 | Normalize all AI-generated block types against BlockRegistry on pack creation |
| P1 | Inject breakpoints in global_styles during reconstruction |
| P2 | Add CSS class-based header/footer detection as semantic fallback |
| P2 | Parse computed styles for color extraction in headless mode |
| P3 | Add marketplace publish dry-run mode to catch DB issues before real publish |
