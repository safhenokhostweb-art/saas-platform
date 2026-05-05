# Enterprise SaaS Builder — Upgrade Roadmap

> **Objective:** Transform modules into production-grade, enterprise-level components.  
> **Principle:** Upgrade in phases; avoid breaking tenant data; maintain backward compatibility.

---

## Phase 1 — Notifications & Core UX (Weeks 1–2)

**Goal:** Enterprise-grade announcements and notification center.

| Task | Where | Deliverable |
|------|--------|-------------|
| Announcements: scheduling & expiration | Platform | `starts_at` / `expires_at` on send; UI in bulk create |
| Announcements: priority levels | Platform + API | `priority` (low/normal/high/urgent); SiteConfig or notifications API |
| Announcements: read tracking (platform) | Platform | Store read per site/user for platform notifications |
| Notification: attachments | Platform + Customer-site | Optional attachment URL or file_id; show in inbox |
| Notification preferences matrix | Customer-site | In-app / Email / SMS toggles per notification type; save to user |
| Optional email/SMS on send | Customer-site | When sending to users, optional “also send email” / “also send SMS” using templates |

**Dependencies:** None.  
**Risks:** None if additive (new columns nullable).

---

## Phase 2 — Commerce & Invoicing (Weeks 2–4)

**Goal:** Invoicing and shop ready for production.

| Task | Where | Deliverable |
|------|--------|-------------|
| Invoice: PDF export | Customer-site | DomPDF or similar; route `invoice/{id}/pdf` |
| Invoice: tax & discount fields | Both | `tax_amount`, `discount_amount` or line-level; migration |
| Invoice: recurring flag | Customer-site | `is_recurring`, `recurring_interval`; cron to generate next |
| Shop: coupon in checkout | Customer-site | Apply coupon code; validate min_order, expiry; reduce total |
| Shop: variants/attributes | Customer-site | ProductVariant model; options (size, color); stock per variant |
| Shop: inventory control | Customer-site | Stock field; decrement on order; low_stock alert |
| Payment: transaction log | Platform | `payment_transactions` (gateway, amount, status, ref); link to invoice |
| Payment: failover | Platform | If primary gateway fails, try next active gateway |

**Dependencies:** Payment gateways already exist.  
**Risks:** Migrations for new tables; backfill existing orders if needed.

---

## Phase 3 — LMS & Content (Weeks 4–6)

**Goal:** Courses and blog at professional level.

| Task | Where | Deliverable |
|------|--------|-------------|
| Courses: lessons/chapters | Customer-site | Lesson model (course_id, order, title, content, video_url, duration) |
| Courses: progress tracking | Customer-site | UserLessonProgress; mark complete; progress % on dashboard |
| Courses: enrollment (free/paid) | Customer-site | Enroll button; paid → checkout; free → direct enroll |
| Courses: certificates | Customer-site | Certificate template; generate on 100% completion |
| Blog: tags | Customer-site | Tag model; many-to-many with posts; filter by tag |
| Blog: scheduled publishing | Customer-site | `published_at`; scope scheduled; scheduler or cron |
| Blog: featured image per post | Customer-site | `featured_image`; already may exist—verify and use in cards |
| Blog: comments (optional) | Customer-site | PostComment model; moderate; show under post |

**Dependencies:** Shop for paid courses.  
**Risks:** Large content models; index for performance.

---

## Phase 4 — Advertising & Media (Weeks 6–7)

**Goal:** Ad platform and media library.

| Task | Where | Deliverable |
|------|--------|-------------|
| Ads: impression & click tracking | Customer-site | `advertisement_impressions`, `advertisement_clicks`; log on view/click |
| Ads: campaign scheduling | Customer-site | `start_at`, `end_at` on Advertisement; filter active |
| Ads: rotation rules | Customer-site | Weight or random; limit per zone per session |
| Media library: model & folders | Customer-site | Media model (path, type, size, folder_id); Folder model (name, parent_id) |
| Media library: upload API | Customer-site | Store with folder; return URL; use in forms/posts/products |
| Media library: image optimization | Customer-site | Optional resize on upload; keep originals |

**Dependencies:** None.  
**Risks:** Storage growth; consider CDN later.

---

## Phase 5 — Forms, Captcha, SEO (Weeks 7–8)

**Goal:** Form builder and SEO at enterprise level.

| Task | Where | Deliverable |
|------|--------|-------------|
| Forms: conditional logic | Platform | Rule engine (show field X when Y = Z); store in form_fields.options |
| Forms: email on submit | Platform + Customer-site | Configurable “send email to” and template; queue job |
| Forms: analytics | Platform | FormSubmission count; optional view count (track on front) |
| Captcha: reCAPTCHA option | Platform | Add type `recaptcha`; site key/secret; render in forms |
| Captcha: rate limiting | Customer-site | Throttle by IP for form submit (e.g. 5/min) |
| SEO: auto-meta from content | Customer-site | First paragraph or excerpt for description; title from post/product title |
| SEO: scoring suggestions | Customer-site | Simple checks (title length, meta desc, OG image); display in admin |

**Dependencies:** None.  
**Risks:** Conditional logic can be complex; start with simple rules.

---

## Phase 6 — Messaging & Support (Weeks 8–10)

**Goal:** Omnichannel-ready messaging and support.

| Task | Where | Deliverable |
|------|--------|-------------|
| Messaging: connector abstraction | Customer-site | Interface `ChannelConnector`; implement WhatsApp (existing), Telegram (new) |
| Messaging: single inbox UI | Customer-site | One view listing conversations from all channels; filter by channel |
| Messaging: auto-reply | Customer-site | Rule: when message contains X, reply Y; store in DB |
| Support: attachments | Platform | Attachments on ticket and reply; store path or media_id |
| Support: SLA timers | Platform | `first_response_due_at`, `resolution_due_at`; show in ticket view |
| Support: KB link | Customer-site | Optional “knowledge base” URL or simple KB table; link from ticket form |

**Dependencies:** Secure token storage for connectors.  
**Risks:** Third-party API limits (WhatsApp, Telegram).

---

## Phase 7 — Analytics, Referral, Reviews (Weeks 10–12)

**Goal:** Reporting and social proof.

| Task | Where | Deliverable |
|------|--------|-------------|
| Analytics: event model | Customer-site | Event (name, user_id, payload, created_at); generic tracking |
| Analytics: sales report | Customer-site | Orders by date range; revenue; export CSV |
| Analytics: dashboard widgets | Customer-site | Orders today, revenue month; configurable widgets |
| Referral: reward rules | Customer-site | ReferralReward model; on signup or purchase; grant coupon or credit |
| Referral: frontend link | Customer-site | “Refer a friend” page with unique link; track referral_id in session |
| Reviews: star rating | Customer-site | Rating 1–5; average on product; sort by rating/date |
| Reviews: verified purchase | Customer-site | If order exists for user+product, show badge |
| Reviews: frontend submit | Customer-site | Form on product page (when purchased); moderate |

**Dependencies:** Shop, orders.  
**Risks:** Referral fraud; add simple checks (same IP, etc.).

---

## Phase 8 — Currency, SMS, Search, Misc (Weeks 12–14)

**Goal:** Polish and remaining modules.

| Task | Where | Deliverable |
|------|--------|-------------|
| Currency: Rial/Toman toggle | Platform + Customer-site | Setting `currency_display` (rial/toman); helper uses it |
| Currency: per-tenant from platform | Platform | site_settings.currency_display; API in site-config |
| SMS: send service & templates | Customer-site | SmsService::send($to, $template, $data); Template model |
| SMS: delivery log | Customer-site | sms_logs table; status, provider_response |
| Search: index table | Customer-site | search_index (entity_type, entity_id, title, body, meta); index on submit/publish |
| Search: typo tolerance | Customer-site | Optional: soundex or simple Levenshtein; or use engine (Scout/Meilisearch) later |
| Business card: QR | Customer-site | QR code URL; generate with library (endroid/qr-code) |
| Social links: placement | Customer-site | Setting: header/footer/both; render in layout |

**Dependencies:** None critical.  
**Risks:** SMS cost; rate limit sends.

---

## Implementation Rules (All Phases)

1. **Migrations:** Additive first (new columns nullable, new tables). Backfill in separate migration. Never drop columns in same release as feature removal.
2. **Config:** New features behind config or module flag where possible.
3. **API:** Extend site-config API for new tenant settings (currency, placement, etc.).
4. **Tests:** Add feature tests for critical paths (checkout, enrollment, form submit).
5. **Docs:** Update PROJECT-GUIDE.md and module guides (saas-module-guide-update rule) when behavior changes.
6. **RTL/Persian:** All new UI RTL; dates in Jalali where required (persian-date-only rule).
7. **Permissions:** New admin routes under appropriate permission or role.

---

## Suggested First Implementation (Phase 1 — One Slice) ✅ انجام شد

برش اول فاز ۱ پیاده‌سازی شده است:

- **زمان‌بندی و انقضا:** از قبل در پلتفرم وجود داشت: فیلدهای `starts_at` و `expires_at` (شمسی) در فرم ایجاد/ویرایش/ارسال گروهی؛ اسکوپ `active()` در مدل فقط اعلان‌های در بازهٔ زمانی را برمی‌گرداند؛ API سایت‌کانفیگ همان اسکوپ را استفاده می‌کند.
- **اولویت:** فیلد `priority` (low, normal, high, urgent) به جدول `platform_site_notifications` اضافه شد (مایگریشن `2026_02_28_130000_add_priority_to_platform_site_notifications`). در فرم‌ها انتخاب اولویت (کم / عادی / مهم / فوری)، در لیست اعلان‌های هر سایت ستون اولویت، در API ترتیب بر اساس اولویت (فوری → مهم → عادی → کم) و در ویجت اعلان سایت مشتری نمایش برچسب «فوری» یا «مهم» و مرتب‌سازی بر اساس اولویت.

**نکته:** پس از کشیدن کد، در پوشهٔ saas-platform دستور `php artisan migrate` را اجرا کنید تا ستون `priority` ایجاد شود.

---

*See ENTERPRISE-MODULES-AUDIT.md for current-state details.*
