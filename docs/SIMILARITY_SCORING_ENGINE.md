# SIMILARITY SCORING ENGINE

**Component:** `SimilarityEngineService`
**Sprint:** TB3 Sprint 9.5
**Version:** 1.0

---

## Purpose

The Similarity Engine computes how closely an analyzed website resembles each of 6
known reference sites. It produces 5 scores per site, plus an aggregate.

---

## Reference Site Profiles

### Apple
- **Type:** tech
- **Sections:** header → hero → features → products → cta → footer
- **Colors:** #000000, #1d1d1f, #0071e3
- **Font:** San Francisco / -apple-system
- **Radius:** sm | **Density:** spacious | **Shadow:** none
- **Commerce:** Partial (60/100)
- **Hero:** centered | **Layout:** full-bleed

### Amazon
- **Type:** ecommerce
- **Sections:** header → hero → categories → products → deals → footer
- **Colors:** #ff9900, #232f3e, #146eb4
- **Font:** Amazon Ember
- **Radius:** none | **Density:** tight | **Shadow:** none
- **Commerce:** 100/100
- **Hero:** standard | **Layout:** standard

### Malltina
- **Type:** ecommerce (Persian)
- **Sections:** header → hero → categories → products → deals → blog → footer
- **Colors:** #e63946, #ff6b6b, #2d3748
- **Font:** IRANSans
- **Radius:** sm | **Density:** tight | **Shadow:** soft
- **Commerce:** 90/100
- **Hero:** standard | **Layout:** standard

### Digikala
- **Type:** ecommerce (Persian)
- **Sections:** header → hero → categories → products → deals → blog → footer
- **Colors:** #ef3e56, #ff6b35, #212121
- **Font:** IRANSans
- **Radius:** none | **Density:** tight | **Shadow:** none
- **Commerce:** 95/100
- **Hero:** standard | **Layout:** standard

### Stripe
- **Type:** saas
- **Sections:** header → hero → features → pricing → testimonials → cta → footer
- **Colors:** #635bff, #0a2540, #00d4ff
- **Font:** Söhne / system-ui
- **Radius:** md | **Density:** spacious | **Shadow:** soft
- **Commerce:** 10/100
- **Hero:** split | **Layout:** editorial

### Shopify
- **Type:** ecommerce
- **Sections:** header → hero → features → pricing → blog → cta → footer
- **Colors:** #96bf48, #5c6ac4, #212b36
- **Font:** Inter / ShopifySans
- **Radius:** md | **Density:** standard | **Shadow:** soft
- **Commerce:** 80/100
- **Hero:** centered | **Layout:** standard

---

## Scoring Algorithm

### Dimension 1: Structure Score

```
score = (matched_sections / total_sections) × 80 + flow_order_bonus
flow_order_bonus: +5 for each of first 3 + last 1 sections matching in order
max: 100
```

**Example:**
- Our sections: [header, hero, features, pricing, cta, footer]
- Stripe ref: [header, hero, features, pricing, testimonials, cta, footer]
- Matched: 6/7 = 85.7% × 80 = 69 + 20 (order bonus) = 89

### Dimension 2: Visual Score

```
color_score    = colorSimilarity(our_primary, ref_colors) × 30
bg_score       = exact match: 10, brightness diff < 30: 5
typography     = exact match: 20, partial: 10
border_radius  = exact match: 15, off by 1 scale: 8
density        = exact match: 15
animation      = match: 10
total max: 100
```

**Color Similarity** uses Euclidean distance in RGB space:
```
distance = √((r1-r2)² + (g1-g2)² + (b1-b2)²)
similarity = 1 - min(1, distance / 441.67)   // max possible = √(255²×3)
```

### Dimension 3: Commerce Score

| Our Site | Reference Site | Score |
|----------|---------------|-------|
| Commerce | Commerce | Based on quality proximity (90–30) |
| Non-commerce | Non-commerce | 85 (correct absence) |
| Commerce | Non-commerce | 20 (mismatch) |
| Non-commerce | Commerce | 20 (mismatch) |

```
quality_diff = |our_commerce_score - ref_commerce_score|
diff < 10:  90
diff < 25:  70
diff < 40:  50
else:       30
```

### Dimension 4: Layout Score

```
col_match = (matching_column_counts / total_ref_cols) × 50
style_match = exact: 25, adjacent: 10
hero_match = exact: 25
total max: 100
```

Column counts match if within ±1 of reference.

### Dimension 5: Overall (Weighted)

```
Ecommerce sites:  structure×25% + visual×20% + commerce×35% + layout×20%
SaaS sites:       structure×30% + visual×30% + commerce×10% + layout×30%
Tech sites:       structure×25% + visual×35% + commerce×15% + layout×25%
General:          structure×25% + visual×25% + commerce×25% + layout×25%
```

---

## Output Format

```json
{
  "per_site": {
    "apple":    { "label":"Apple",    "structure":60, "visual":45, "commerce":35, "layout":55, "overall":50 },
    "amazon":   { "label":"Amazon",   "structure":40, "visual":20, "commerce":90, "layout":60, "overall":55 },
    "malltina": { "label":"Malltina", "structure":70, "visual":65, "commerce":85, "layout":72, "overall":74 },
    "digikala": { "label":"Digikala", "structure":72, "visual":60, "commerce":90, "layout":70, "overall":76 },
    "stripe":   { "label":"Stripe",   "structure":55, "visual":40, "commerce":15, "layout":50, "overall":40 },
    "shopify":  { "label":"Shopify",  "structure":60, "visual":45, "commerce":70, "layout":58, "overall":58 }
  },
  "best_match": "digikala",
  "best_match_label": "Digikala",
  "best_match_score": 76,
  "structure": 59,
  "visual": 46,
  "commerce": 64,
  "layout": 61,
  "overall": 76,
  "confidence_level": "MEDIUM",
  "recommended_style": "ecommerce"
}
```

---

## Confidence Levels

| Level | Score | Interpretation |
|-------|-------|---------------|
| HIGH | ≥80 | Strong structural + visual match. Reconstruction very close to target. |
| MEDIUM | 60–79 | Partial match. Reconstruction resembles target in most dimensions. |
| LOW | 40–59 | Weak match. Input had limited signals, but reconstruction is still valid. |
| VERY_LOW | <40 | Very weak match. Likely prompt-only input or minimal HTML. |

---

## Extending Reference Sites

To add a new reference site, add an entry to `REFERENCE_SITES` in `SimilarityEngineService`:

```php
'mynewsite' => [
    'label'          => 'MyNewSite',
    'type'           => 'ecommerce',  // ecommerce | saas | tech | general
    'sections'       => ['header', 'hero', ...],
    'column_sig'     => ['header' => 1, 'hero' => 1, 'products' => 4, 'footer' => 4],
    'primary_colors' => ['#hex1', '#hex2'],
    'bg_color'       => '#ffffff',
    'font'           => 'FontName',
    'radius'         => 'md',         // none | sm | md | xl | pill
    'density'        => 'standard',   // tight | standard | spacious
    'has_animations' => true,
    'has_commerce'   => true,
    'commerce_score' => 85,
    'layout_style'   => 'standard',   // standard | editorial | full-bleed | bento
    'hero_layout'    => 'centered',   // centered | split | standard
    'shadow'         => 'soft',       // none | soft | elevated | hard
],
```
