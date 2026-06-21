# TOP FAILURE PATTERNS

> Generated: 2026-06-21 22:20
> Based on real-world validation of 5 site categories

---

## Observed Failures

### FP-001: Header Not Detected

**Severity:** HIGH  
**Affected sites:** Tailwind CSS (Landing Page)  

**Root Cause:**  
SPA sites render header via JS → raw HTML has no `<header>` tag. Playwright renders it but DOMXPath may miss non-semantic divs.

**Required Fix:**  
Improve semantic fallback in DomExtractionService: detect role="banner" + sticky/fixed nav divs. Use CSS class heuristics (e.g. `.navbar`, `.site-header`).

---

### FP-003: Navigation Items Not Extracted

**Severity:** HIGH  
**Affected sites:** Amazon AWS (Corporate), Amazon (E-commerce), Stripe (Service/SaaS), Maktabkhooneh (Education), Tailwind CSS (Landing Page)  

**Root Cause:**  
Mega-menus and JS-driven navigation are not present in the static DOM. Nav links are loaded via API or injected by React.

**Required Fix:**  
In headless mode, use `document.querySelectorAll("nav a")` after SPA hydration. Store extracted nav in playwright payload.

---

### FP-004: Similarity Below Target

**Severity:** MEDIUM  
**Affected sites:** Tailwind CSS (Landing Page) (42% < 65%)  

**Root Cause:**  
Sites with minified CSS, Tailwind JIT, or CSS-in-JS have no extractable color variables. Color distance algorithm scores low.

**Required Fix:**  
Enhance color extraction: parse `window.getComputedStyle` on key elements (buttons, headers) in headless mode. Store in `css_variables`.

---

### FP-007: No Responsive Support Detected

**Severity:** MEDIUM  
**Affected sites:** Amazon AWS (Corporate), Amazon (E-commerce), Stripe (Service/SaaS), Maktabkhooneh (Education), Tailwind CSS (Landing Page)  

**Root Cause:**  
ThemePack generated without responsive_settings per block. Global styles missing breakpoints.

**Required Fix:**  
In ReverseThemeReconstructorService::buildGlobalStyles(): always inject breakpoints (sm:640, md:768, lg:1024, xl:1280).

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
