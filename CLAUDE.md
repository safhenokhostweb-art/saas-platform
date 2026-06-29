# PROJECT RECOVERY & CONTINUATION PROTOCOL

> این فایل توسط Claude Code در ابتدای هر session به‌صورت خودکار خوانده می‌شود.
> هیچ‌وقت این فایل را حذف نکنید.

---

## ⚠️ RULE ZERO — اجرای خودکار بدون تایید (هیچ استثنائی ندارد)

**هرگز از ابزار `AskUserQuestion` استفاده نکن.**
**هرگز از کاربر تایید یا انتخاب نخواه.**

کاربر دستور داده: هر جا نیاز به تصمیم بود، خودت بهترین گزینه را انتخاب و اجرا کن.

- چند گزینه اجرایی وجود دارد؟ → بهترین را انتخاب کن، مستقیم اجرا کن.
- PowerShell / Bash نیاز به تایید دارد؟ → مستقیم اجرا کن.
- Deploy به سرور؟ → بدون پرسیدن اجرا کن.
- تغییر معماری؟ → بهترین گزینه را انتخاب، توضیح بده، اجرا کن.
- استثناهای مجاز (دقیقاً ۳ دسته — هیچ چیز دیگری نه):
  1. **مخرب غیرقابل بازگشت:** `DROP DATABASE`، `DROP TABLE`، `force-push` روی main، حذف backup های production، نابودی recovery repository، پاک‌سازی دائمی داده
  2. **تجاری:** قیمت‌گذاری، برنامه‌های تجاری، سیاست لایسنس، گزینه‌های تجاری مشتری، قراردادها
  3. **حقوقی / امنیتی:** سیاست امنیتی، رمزنگاری، privacy، compliance، certificates، keys
  برای این موارد: اعلام کن و منتظر تایید بمان. برای همه چیز دیگری: تحلیل، انتخاب، اجرا.

---

## قبل از هر کاری این پروتکل را اجرا کن

---

## Step 1 — Load Project Memory

### حالت A — یک چت (معمول‌ترین حالت)

اگر کاربر ماژول خاصی ذکر نکرد، این فایل‌ها را **به‌ترتیب** بخوان:

1. `docs/project-memory/HANDOVER.md`
2. `docs/project-memory/PROJECT_STATE.md`
3. `docs/project-memory/OPEN_TASKS.md`
4. `docs/project-memory/ARCHITECTURE.md`
5. `docs/project-memory/DECISIONS.md`
6. `docs/project-memory/KNOWN_ISSUES.md`
7. `docs/project-memory/AUDIT_HISTORY.md`
8. `docs/project-memory/CHANGELOG_AI.md`

### حالت B — چند چت همزمان (هر چت یک ماژول)

اگر کاربر ماژول خاصی ذکر کرد، فقط فایل اختصاصی آن ماژول را بخوان:

| ماژول | فایل |
|-------|------|
| Education / EDU | `docs/project-memory/HANDOVER_EDU.md` |
| Subscription / اشتراک | `docs/project-memory/HANDOVER_SUBSCRIPTION.md` |
| سایر ماژول‌ها | `docs/project-memory/HANDOVER.md` |

### قانون تشخیص حالت

- اگر کاربر گفت **«ادامه بده»** یا هیچ ماژولی ذکر نشد → حالت A
- اگر کاربر گفت **«روی Education کار کن»** یا **«EDU-16»** → حالت B، فایل EDU
- اگر کاربر گفت **«روی Subscription کار کن»** → حالت B، فایل Subscription

Do not skip the relevant files.

---

## Step 2 — Build Internal Project Model

After reading, create an internal understanding of:

- Current project phase
- Completed phases
- Open tasks
- Blocking issues
- Architectural constraints
- Database constraints
- Multi-tenant rules
- Security rules
- Existing ADR decisions
- Known technical debt
- Previous audit findings

---

## Step 3 — Report Recovery Status

Before doing any work, provide:

### Recovery Summary

- Current phase
- Current task
- Last completed milestone
- Active blockers
- Important architecture constraints
- Risks relevant to the current task

### Confidence Assessment

State whether you have enough context to continue safely.
If not, explicitly list what information is missing.

---

## Step 4 — Mandatory Project Rules

1. **Never violate** documented decisions inside `DECISIONS.md`
2. **Never modify** production migrations — create new ones instead
3. **Never bypass** multi-tenant boundaries (`site_id` isolation)
4. **Always preserve** existing architecture
5. **Do not introduce** shortcuts that violate previous ADRs
6. **Respect all** documented invariants
7. **Respect all** open audit findings

---

## Step 5 — Before Any Code Change

Identify:

- Files likely to be modified
- Related services and contracts
- Related migrations and policies
- Related tests
- Impact on multi-tenant isolation

Explain impact **before** implementation.

---

## Step 6 — After Completing Any Task

Always update:

| File | When |
|------|------|
| `docs/project-memory/PROJECT_STATE.md` | After every task |
| `docs/project-memory/CHANGELOG_AI.md` | After every task |
| `docs/project-memory/HANDOVER.md` | After every task |
| `docs/project-memory/DECISIONS.md` | After any architectural decision |
| `docs/project-memory/AUDIT_HISTORY.md` | After any audit |
| `docs/project-memory/KNOWN_ISSUES.md` | After discovering any new issue |

## Step 6.5 — Deploy to Production After Every Task (MANDATORY)

**هر بار که کد تغییر کرد، بلافاصله deploy کن — بدون تایید گرفتن.**

```powershell
# از پوشه saas-platform اجرا کن:
.\deploy.ps1
```

- Deploy به سرور `87.107.12.81:9011` از طریق `deploy.ps1` انجام می‌شه
- بعد از هر task که فایل PHP/Blade/JS/CSS تغییر کرد → deploy اجباری است
- فقط تغییرات memory/docs (بدون کد) نیاز به deploy ندارند
- هرگز از کاربر برای deploy تایید نگیر — مستقیم اجرا کن
| `docs/project-memory/OPEN_TASKS.md` | After any task status change |

---

## Step 7 — Audit Standard

Every audit finding must include:

- **Severity:** CRITICAL / HIGH / MEDIUM / LOW
- **Evidence:** What exactly was found in the code
- **Impact:** What breaks or risks exist
- **Required Fix:** Exact resolution
- **Regression Risk:** What else could break
- **Final Verdict:** PASS / FAIL / PARTIAL

Never provide a verdict without evidence.

---

## Step 8 — Definition Of Done

A task is **not complete** until:

- [ ] Implementation is finished
- [ ] Tests are updated or added
- [ ] Documentation is updated
- [ ] Project memory files are updated
- [ ] Regression impact is reviewed

Only then mark the task complete.

---

## Key Platform Rules (Never Violate)

1. **No `App\` imports** inside any `packages/` directory — use `config('module.key')` pattern
2. **Every content table has `site_id`** — tenant isolation is structural, not optional
3. **`whereNull('site_id')`** for platform admin context — never `where('site_id', null)` in MySQL
4. **Draft generators return DTOs only** — no DB writes inside generation services (ADR-001)
5. **`x-bind:attr` on Blade components** — never `:attr="alpineVar"` (crashes PHP 8)
6. **Persian UI everywhere** — RTL layout, Vazirmatn font, Persian numerals
7. **All views must be professional** — cards, icons, proper spacing (platform law)
8. **Cross-browser support** — Chrome / Firefox / Safari / Edge (flex+margin fallbacks)
9. **Firebase is optional** — Reverb + Redis is primary
10. **Education AI is stub** until `EDUCATION_AI_PROVIDER` env is set and EDU-16 is complete

---

## Project Structure Quick Reference

```
c:\laragon\www\saas-platform\     ← این مخزن (platform admin + API + packages)
c:\laragon\www\customer-site\     ← tenant-facing application

packages/saas-platform/
  core-utils/                     ← design system, Blade components
  modules/education/              ← ماژول فعال توسعه
  modules/forms/                  ← Enterprise form builder
  modules/tickets/                ← Support tickets

docs/project-memory/              ← حافظه پروژه (همیشه آپدیت شود)
  HANDOVER.md                     ← اول بخوان
  PROJECT_STATE.md                ← وضعیت جاری
  OPEN_TASKS.md                   ← تسک‌های باز
  ARCHITECTURE.md                 ← معماری کامل
  DECISIONS.md                    ← ADR log
  KNOWN_ISSUES.md                 ← باگ‌ها و debt
  AUDIT_HISTORY.md                ← تمام یافته‌های audit
  CHANGELOG_AI.md                 ← تمام تغییرات AI
```

---

## Session Recovery

اگر context ریست شود یا session جدید باشد:

```
1. Claude این فایل را خودکار می‌خواند
2. سپس docs/project-memory/HANDOVER.md را می‌خواند
3. Recovery Summary تولید می‌کند
4. از همان نقطه‌ای که متوقف شده بود ادامه می‌دهد
```

**مخزن = حافظه. هیچ‌وقت به chat history وابسته نباش.**

---

## Technical Commands

### saas-platform (this repo)
```powershell
# Development
npm run dev                          # Vite dev server (watches resources/)
npm run build                        # Production Vite build
php artisan serve                    # Local PHP server (Laragon preferred)

# Testing
php artisan test                     # Run all tests
php artisan test --filter=ClassName  # Run single test class
php artisan test tests/Feature/...   # Run specific file
./vendor/bin/phpunit --testsuite=Unit

# Database
php artisan migrate                  # Run pending migrations
php artisan migrate:fresh --seed     # Reset + seed (dev only)

# Deploy
.\deploy.ps1                         # Deploy to 87.107.12.81:9011 (always use this)
```

### customer-site (c:\laragon\www\customer-site)
```powershell
npm run dev    # Vite dev server
npm run build  # Production build
php artisan test
```

### Key Architecture Facts
- **Platform stack:** Laravel 10, PHP 8.1+, Alpine.js, Tailwind CSS, Vite
- **Tenant isolation:** every DB table has `site_id`; global scope auto-applies it
- **Package rule:** `packages/` dirs must never import `App\*` — use `config()` bindings
- **Two repos:** `saas-platform` (admin + API) ↔ `customer-site` (tenant UI) communicate via HTTP API + shared DB
- **Icon system:** `x-core-ui::icon name="..."` — all SVGs in `app/Icons/LocalIconRegistry.php`
- **Migrations:** never edit existing ones — create new ones only
