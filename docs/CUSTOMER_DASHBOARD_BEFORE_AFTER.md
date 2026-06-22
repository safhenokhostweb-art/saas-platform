# CUSTOMER DASHBOARD — BEFORE & AFTER
> Date: 2026-06-22
> Scope: customer-site admin dashboard

---

## BEFORE

```
╔══════════════════════════════════════════════════════════════╗
║  WELCOME CARD  (g-card, p-6 sm:p-8, mb-7)              140px║
║  ┌──────────────────────────────────────────────────────┐    ║
║  │ 🏠 Avatar (56px)  خوش آمدید، مدیر 👋              │    ║
║  │   آخرین وضعیت سایت ...                              │    ║
║  │                         ● سایت فعال (pulse dot)     │    ║
║  └──────────────────────────────────────────────────────┘    ║
╠══════════════════════════════════════════════════════════════╣
║  LIFECYCLE WIDGET (when non-active)                    ~80px ║
╠══════════════════════════════════════════════════════════════╣
║  RESOURCE WIDGET (only if >60% used)                  ~80px  ║
╠══════════════════════════════════════════════════════════════╣
║  IMPORTANT ALERTS (if any)                            ~80px  ║
╠══════════════════════════════════════════════════════════════╣
║  MODULE UPDATES BANNER (if any)                       ~70px  ║
╠══════════════════════════════════════════════════════════════╣
║  STAT TILES  (grid 2–5 cols, dash-tile, p-5, mb-8)    220px ║
║  ┌───────┐ ┌───────┐ ┌───────┐ ┌───────┐ ┌───────┐        ║
║  │  📄   │ │  ✍️   │ │  📦   │ │  🛒   │ │  ⚙️   │        ║
║  │صفحات  │ │مطالب  │ │محصول  │ │سفارش  │ │تنظیمات│        ║
║  │  12   │ │  47   │ │  83   │ │  21   │ │پیکرب. │        ║
║  │(hint) │ │(hint) │ │(hint) │ │(hint) │ │(hint) │        ║
║  └───────┘ └───────┘ └───────┘ └───────┘ └───────┘        ║
║  + 2 more tiles (لایسنس، ماژول‌ها)                         ║
╠══════════════════════════════════════════════════════════════╣
║  QUICK LINKS  (grid 2–4 cols, mb-8)                    200px ║
║  (8 links duplicating stat tile destinations)               ║
╠══════════════════════════════════════════════════════════════╣
║  RECENT ORDERS TABLE  (g-card-strong, p-6, mb-7)       250px ║
╠══════════════════════════════════════════════════════════════╣
║  MODULE SECTION  (dashboard-modules-block, mt/mb 1.5rem)     ║
║  ┌──────────────────┬──────────────────────────────────┐    ║
║  │ 🧩 ماژول‌ها     │ Table: Module/Platform/Enabled/   │    ║
║  │                  │        Status                    │    ║
║  │ Description      │  Row height: ~52px               │    ║
║  │ License state    │  No search, no pagination         │    ║
║  │ block (p-3)      │  All modules always visible      │    ║
║  └──────────────────┴──────────────────────────────────┘    ║
║  HEIGHT: ~400px for 8 modules, ~700px for 15 modules         ║
╠══════════════════════════════════════════════════════════════╣
║  SYSTEM STATUS CARD (g-card p-5 mb-2)                   80px ║
╚══════════════════════════════════════════════════════════════╝

TOTAL VIEWPORT (1080p): ~1500–1900px of content
SCROLL REQUIRED to see modules: YES (560px+ before module section)
RESOURCE ALWAYS VISIBLE: NO (only >60%)
MODULE STATS AS NUMBERS: NO
SEARCH IN MODULE TABLE: NO
PAGINATION: NO
```

---

## AFTER

```
╔══════════════════════════════════════════════════════════════╗
║  WELCOME BAR  (cd-welcome, py-2.5, mb-2)                48px ║
║  ┌─────────────────────────────────────────────────────┐     ║
║  │ [🏠] مدیر         safhenokhost.ir   ●فعال 🔑فعال 🌐│     ║
║  └─────────────────────────────────────────────────────┘     ║
╠══════════════════════════════════════════════════════════════╣
║  LIFECYCLE WIDGET (only when non-active)          0–70px     ║
╠══════════════════════════════════════════════════════════════╣
║  ALERTS / UPDATES (compact, when present)         0–40px     ║
╠══════════════════════════════════════════════════════════════╣
║  ROW 1: MODULE STATS + RESOURCES (2-col grid)        110px   ║
║  ┌─────────────────────────┬──────────────────────────┐      ║
║  │ 🧩 ماژول‌ها            │ 📊 منابع سیستم           │      ║
║  │ ┌──┐ ┌──┐ ┌──┐ ┌──┐  │ ┌──────────┐ ┌──────────┐ │      ║
║  │ │✅│ │⏸│ │⭐│ │📦│  │ │💾 فضا    │ │🗄️ DB    │ │      ║
║  │ │ 5│ │ 2│ │ 8│ │12│  │ │███░ 45% │ │██░░ 22% │ │      ║
║  │ │فع│ │غف│ │پر│ │در│  │ │1.2G/5G  │ │120M     │ │      ║
║  │ └──┘ └──┘ └──┘ └──┘  │ └──────────┘ └──────────┘ │      ║
║  └─────────────────────────┴──────────────────────────┘      ║
╠══════════════════════════════════════════════════════════════╣
║  ROW 2: CONTENT KPI CARDS  (4-col, mb-3)             80px    ║
║  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐               ║
║  │📄 صفحات│ │✍️ مطالب│ │📦 محصول│ │🛒 سفارش│               ║
║  │   12   │ │   47   │ │   83   │ │   21   │               ║
║  └────────┘ └────────┘ └────────┘ └────────┘               ║
╠══════════════════════════════════════════════════════════════╣
║  ROW 3: QUICK ACTIONS (1 chip row, mb-4)              44px   ║
║  [⚙️تنظیمات] [📄صفحات] [✍️وبلاگ] [💬پشتیبانی] [🔑لایسنس]  ║
╠══════════════════════════════════════════════════════════════╣
║  MODULE COMPACT DATA GRID  (cd-section)                      ║
║  ┌──────────────────────── [🔍 جستجو...]  ─────────────────┐ ║
║  │ ●  icon │ ماژول       │پلتفرم │وضعیت │پلن      │عمل   │ ║
║  │─────────────────────────────────────────────────────────│ ║
║  │ ● ✍️  │ وبلاگ        │✓فعال  │فعال  │پریمیوم  │هماهنگ│ ║  42px
║  │ ● 🛒  │ فروشگاه      │✓فعال  │فعال  │پریمیوم  │هماهنگ│ ║  42px
║  │ ● 📋  │ فرم‌ساز      │—      │فعال  │رایگان   │لایسنس│ ║  42px
║  │ ● ⏸  │ اعلان‌ها     │✓فعال  │غیرفع │پریمیوم  │فعال‌س│ ║  42px
║  │ ...  (10 rows max)                                       │ ║
║  ├──────────────────────────── [‹] [1] [2] [›] ────────────┤ ║
║  │ نمایش ۱۰ از ۱۵ ماژول                                   │ ║
║  └─────────────────────────────────────────────────────────┘ ║
╠══════════════════════════════════════════════════════════════╣
║  RECENT ORDERS (cd-section, compact table)             200px  ║
╚══════════════════════════════════════════════════════════════╝

TOTAL VIEWPORT (1080p): ~580px above module grid
SCROLL REQUIRED to see key info: NO (all fits above fold)
RESOURCE ALWAYS VISIBLE: YES (inline, no threshold gate)
MODULE STATS AS NUMBERS: YES (4 KPI chips)
SEARCH IN MODULE TABLE: YES (Alpine.js filter)
PAGINATION: YES (10/page, Alpine.js)
```

---

## Key Metric Comparisons

| Metric | Before | After | Delta |
|--------|--------|-------|-------|
| Welcome section height | ~140px | ~48px | **–66%** |
| Content above module section | ~560px+ | ~290px | **–48%** |
| Module table row height | ~52px | 42px | **–19%** |
| Module section with 15 modules | ~700px | 42×10 + pagination | **–70%** |
| Module stat numbers shown | 0 | 4 KPI chips | **+4** |
| Duplicate nav elements | 15 (tiles+links overlap) | 8 chips (1 row) | **–47%** |
| Resource always visible | ❌ (>60% gate) | ✅ always | Fixed |
| Search in module table | ❌ | ✅ Alpine.js | New |
| Pagination | ❌ | ✅ 10/page | New |
| Custom CSS classes | ~47 | ~38 | **–19%** |
| Animation name conflicts | possible (`pulse`) | none (prefixed) | Fixed |
| "Plan" column in table | ❌ | ✅ | New |

---

## Design Language Change

| Element | Before | After |
|---------|--------|-------|
| Card style | Glass (backdrop-blur, complex) | Flat (solid #fff, 1px border) |
| Shadow | `0 8px 32px rgba(15,23,42,0.07)` | `0 1px 3px rgba(15,23,42,0.05)` |
| Card radius | `1rem` | `0.875rem` |
| Card padding | `1.25rem–2rem` | `0.875rem–1rem` |
| Section gap | `mb-7`, `mb-8` | `mb-3` |
| Typography base | Mixed (0.72–2rem) | Scale (0.63–1.375rem) |
| Badge system | 2 classes (`dashboard-pill-*`) | 7 classes (`cd-badge-*`) |
| Hover animation | `translateY(-5px) scale(1.02)` | `box-shadow + border-color` |
| Inspiration | Custom glassmorphism | Stripe/Vercel/Linear SaaS |
