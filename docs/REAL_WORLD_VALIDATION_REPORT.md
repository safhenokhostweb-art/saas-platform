# REAL WORLD VALIDATION REPORT

> Generated: 2026-06-21 22:10
> Command: `php artisan re:validate-real-world`

---

## Pipeline Stages

| Stage | Description |
|-------|-------------|
| 1. Extraction | Playwright headless + HTTP fallback |
| 2. Theme Reconstruction | 7-phase RE pipeline (DOM→Layout→Visual→Commerce→Similarity→Pack→Quality) |
| 3. Theme Pack | ThemePack model creation with pages + blocks |
| 4. Marketplace Publish | ThemeMarketplace entry creation |
| 5. Tenant Install | Canvas data compatibility check |
| 6. Canvas Editing | Block schema + props validation |
| 7. Customer Customization | Color/font token presence + editing support |

---

## Summary Table

| Site | Category | Similarity | Quality | Stages | Status |
|------|----------|-----------|---------|--------|--------|
| Amazon AWS (Corporate) | corporate | 80% | PASS (100) | 7/7 | ✅ PASS |
| Amazon (E-commerce) | ecommerce | 99% | PARTIAL (85) | 7/7 | ✅ PASS |
| Stripe (Service/SaaS) | service | 83% | PASS (100) | 7/7 | ✅ PASS |
| Maktabkhooneh (Education) | education | 73% | PASS (92) | 7/7 | ✅ PASS |
| Shopify (Landing Page) | landing | 0% | UNKNOWN (0) | 0/7 | ❌ FAIL |

---

## [corporate] Amazon AWS (Corporate)

**URL:** `https://aws.amazon.com`  
**Render mode:** http  
**HTML size:** 405,072 bytes  
**Framework:** N/A  
**Elapsed:** 105753ms  

### Pipeline Stages

| Stage | Result |
|-------|--------|
| 1. Extraction | ✅ |
| 2. Reconstruction | ✅ |
| 3. Theme Pack | ✅ |
| 4. Marketplace | ✅ |
| 5. Tenant Install | ✅ |
| 6. Canvas Editing | ✅ |
| 7. Customization | ✅ |

### Metrics

| Metric | Value |
|--------|-------|
| Similarity Score | **80%** (target: 65%+) |
| Responsive Support | NO |
| Header Reconstructed | YES |
| Footer Reconstructed | YES |
| Navigation Reconstructed | NO |
| Commerce Reconstructed | YES (score: 60) |
| Editing Compatible | YES |
| Block Count | 6 |
| Installation Success | YES |
| Quality | PASS (100/100) |
| Primary Color | `#0066ff` |
| Primary Font | system-ui |

**Sections detected:** header, footer

> ThemePack ID: #35
> Marketplace ID: #32

---

## [ecommerce] Amazon (E-commerce)

**URL:** `https://www.amazon.com`  
**Render mode:** headless  
**HTML size:** 753,276 bytes  
**Framework:** N/A  
**Elapsed:** 161314ms  

### Pipeline Stages

| Stage | Result |
|-------|--------|
| 1. Extraction | ✅ |
| 2. Reconstruction | ✅ |
| 3. Theme Pack | ✅ |
| 4. Marketplace | ✅ |
| 5. Tenant Install | ✅ |
| 6. Canvas Editing | ✅ |
| 7. Customization | ✅ |

### Metrics

| Metric | Value |
|--------|-------|
| Similarity Score | **99%** (target: 70%+) |
| Responsive Support | NO |
| Header Reconstructed | YES |
| Footer Reconstructed | YES |
| Navigation Reconstructed | NO |
| Commerce Reconstructed | YES (score: 90) |
| Editing Compatible | YES |
| Block Count | 6 |
| Installation Success | YES |
| Quality | PARTIAL (85/100) |
| Primary Color | `#0f1111` |
| Primary Font | Arial |

**Sections detected:** header, hero, footer

> ThemePack ID: #36
> Marketplace ID: #33

---

## [service] Stripe (Service/SaaS)

**URL:** `https://stripe.com`  
**Render mode:** headless  
**HTML size:** 760,719 bytes  
**Framework:** Next.js  
**Elapsed:** 58302ms  

### Pipeline Stages

| Stage | Result |
|-------|--------|
| 1. Extraction | ✅ |
| 2. Reconstruction | ✅ |
| 3. Theme Pack | ✅ |
| 4. Marketplace | ✅ |
| 5. Tenant Install | ✅ |
| 6. Canvas Editing | ✅ |
| 7. Customization | ✅ |

### Metrics

| Metric | Value |
|--------|-------|
| Similarity Score | **83%** (target: 70%+) |
| Responsive Support | NO |
| Header Reconstructed | YES |
| Footer Reconstructed | YES |
| Navigation Reconstructed | NO |
| Commerce Reconstructed | YES (score: 65) |
| Editing Compatible | YES |
| Block Count | 6 |
| Installation Success | YES |
| Quality | PASS (100/100) |
| Primary Color | `#031323` |
| Primary Font | system-ui |

**Sections detected:** header, hero, cta, footer

> ThemePack ID: #37
> Marketplace ID: #34

---

## [education] Maktabkhooneh (Education)

**URL:** `https://maktabkhooneh.org`  
**Render mode:** http  
**HTML size:** 626,945 bytes  
**Framework:** N/A  
**Elapsed:** 102162ms  

### Pipeline Stages

| Stage | Result |
|-------|--------|
| 1. Extraction | ✅ |
| 2. Reconstruction | ✅ |
| 3. Theme Pack | ✅ |
| 4. Marketplace | ✅ |
| 5. Tenant Install | ✅ |
| 6. Canvas Editing | ✅ |
| 7. Customization | ✅ |

### Metrics

| Metric | Value |
|--------|-------|
| Similarity Score | **73%** (target: 65%+) |
| Responsive Support | NO |
| Header Reconstructed | YES |
| Footer Reconstructed | YES |
| Navigation Reconstructed | NO |
| Commerce Reconstructed | YES (score: 25) |
| Editing Compatible | YES |
| Block Count | 6 |
| Installation Success | YES |
| Quality | PASS (92/100) |
| Primary Color | `#009ca7` |
| Primary Font | sans-serif |

**Sections detected:** header, footer

> ThemePack ID: #38
> Marketplace ID: #35

---

## [landing] Shopify (Landing Page)

**URL:** `https://www.shopify.com`  
**Render mode:** http  
**HTML size:** 0 bytes  
**Framework:** N/A  
**Elapsed:** 13423ms  

### Pipeline Stages

| Stage | Result |
|-------|--------|
| 1. Extraction | ❌ |
| 2. Reconstruction | ❌ |
| 3. Theme Pack | ❌ |
| 4. Marketplace | ❌ |
| 5. Tenant Install | ❌ |
| 6. Canvas Editing | ❌ |
| 7. Customization | ❌ |

### Metrics

| Metric | Value |
|--------|-------|
| Similarity Score | **0%** (target: 70%+) |
| Responsive Support | NO |
| Header Reconstructed | NO |
| Footer Reconstructed | NO |
| Navigation Reconstructed | NO |
| Commerce Reconstructed | NO |
| Editing Compatible | NO |
| Block Count | 0 |
| Installation Success | NO |
| Quality | UNKNOWN (0/100) |
| Primary Color | `` |
| Primary Font |  |

**Sections detected:** none

### Errors

- Extraction: cURL error 35: Recv failure: Connection was reset (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://www.shopify.com


---
