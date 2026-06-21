# REVERSE ENGINEERING ACCURACY REPORT

> Generated: 2026-06-21 22:10
> Sites tested: 5 (corporate, ecommerce, service, education, landing)

---

## Aggregate Accuracy Metrics

| Metric | Value |
|--------|-------|
| Sites Tested | 5 |
| Pipeline Completed | 4/5 |
| Targets Met | 4/5 |
| Average Similarity | **67%** |
| Average Quality Score | **75/100** |
| Average Block Count | 5 blocks/site |
| Stage Completion Rate | 80% (avg stages completed) |

## Component Reconstruction Accuracy

| Component | Success Rate | Grade |
|-----------|-------------|-------|
| Header | 80% | GOOD |
| Footer | 80% | GOOD |
| Navigation | 0% | FAIL |
| Commerce | 80% | GOOD |
| Editing Compatible | 80% | GOOD |
| Installation | 80% | GOOD |
| Responsive Support | 0% | FAIL |

## Per-Site Accuracy

| Site | Category | Similarity | Header | Footer | Nav | Commerce | Edit | Install | Responsive |
|------|----------|-----------|--------|--------|-----|----------|------|---------|------------|
| Amazon AWS (Corporate) | corporate | 80% | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ | ❌ |
| Amazon (E-commerce) | ecommerce | 99% | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ | ❌ |
| Stripe (Service/SaaS) | service | 83% | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ | ❌ |
| Maktabkhooneh (Education) | education | 73% | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ | ❌ |
| Shopify (Landing Page) | landing | 0% | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

## Render Mode Impact

| Render Mode | Sites | Avg Similarity |
|-------------|-------|---------------|
| HTTP only | 3 | 51% |
| Playwright Headless | 2 | 91% |

## Accuracy by Category

| Category | Target | Achieved | Delta | Grade |
|----------|--------|----------|-------|-------|
| corporate | 65% | 80% | +15% | GOOD |
| ecommerce | 70% | 99% | +29% | EXCELLENT |
| service | 70% | 83% | +13% | GOOD |
| education | 65% | 73% | +8% | PASS |
| landing | 70% | 0% | -70% | FAIL |

## Overall Assessment

**Overall Grade:** PASS (C)

**Strengths:**
- High installation success rate — ThemePacks are canvas-compatible
- Canvas editing compatibility is high — blocks have proper schema

**Weaknesses:**
- Navigation extraction: 0% — JS-driven menus are not captured
- Average similarity 67% is below 75% — headless mode needed for more sites

---

## Certification

```
REAL_WORLD_VALIDATION_PASSED
Date: 2026-06-21 22:10
Sites: 4/5 targets met
Average similarity: 67%
```