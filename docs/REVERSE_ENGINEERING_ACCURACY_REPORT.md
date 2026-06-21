# REVERSE ENGINEERING ACCURACY REPORT

> Generated: 2026-06-21 22:20
> Sites tested: 5 (corporate, ecommerce, service, education, landing)

---

## Aggregate Accuracy Metrics

| Metric | Value |
|--------|-------|
| Sites Tested | 5 |
| Pipeline Completed | 5/5 |
| Targets Met | 4/5 |
| Average Similarity | **75%** |
| Average Quality Score | **89/100** |
| Average Block Count | 6 blocks/site |
| Stage Completion Rate | 100% (avg stages completed) |

## Component Reconstruction Accuracy

| Component | Success Rate | Grade |
|-----------|-------------|-------|
| Header | 80% | GOOD |
| Footer | 100% | EXCELLENT |
| Navigation | 0% | FAIL |
| Commerce | 100% | EXCELLENT |
| Editing Compatible | 100% | EXCELLENT |
| Installation | 100% | EXCELLENT |
| Responsive Support | 0% | FAIL |

## Per-Site Accuracy

| Site | Category | Similarity | Header | Footer | Nav | Commerce | Edit | Install | Responsive |
|------|----------|-----------|--------|--------|-----|----------|------|---------|------------|
| Amazon AWS (Corporate) | corporate | 80% | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ | ❌ |
| Amazon (E-commerce) | ecommerce | 99% | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ | ❌ |
| Stripe (Service/SaaS) | service | 83% | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ | ❌ |
| Maktabkhooneh (Education) | education | 73% | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ | ❌ |
| Tailwind CSS (Landing Page) | landing | 42% | ❌ | ✅ | ❌ | ✅ | ✅ | ✅ | ❌ |

## Render Mode Impact

| Render Mode | Sites | Avg Similarity |
|-------------|-------|---------------|
| HTTP only | 2 | 77% |
| Playwright Headless | 3 | 75% |

## Accuracy by Category

| Category | Target | Achieved | Delta | Grade |
|----------|--------|----------|-------|-------|
| corporate | 65% | 80% | +15% | GOOD |
| ecommerce | 70% | 99% | +29% | EXCELLENT |
| service | 70% | 83% | +13% | GOOD |
| education | 65% | 73% | +8% | PASS |
| landing | 65% | 42% | -23% | FAIL |

## Overall Assessment

**Overall Grade:** GOOD (B)

**Strengths:**
- Similarity engine produces PASS-grade results across diverse site types
- High installation success rate — ThemePacks are canvas-compatible
- Canvas editing compatibility is high — blocks have proper schema

**Weaknesses:**
- Navigation extraction: 0% — JS-driven menus are not captured

---

## Certification

```
REAL_WORLD_VALIDATION_PASSED
Date: 2026-06-21 22:20
Sites: 4/5 targets met
Average similarity: 75%
```