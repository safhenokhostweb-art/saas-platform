# RECOVERY PROTOCOL — Reference Copy

> نسخه مرجع. نسخه اجرایی در `CLAUDE.md` در root پروژه است.
> Claude Code آن فایل را در هر session به‌صورت خودکار می‌خواند.

---

## چطور کار می‌کند؟

```
Session جدید باز می‌شود
        │
        ▼
Claude Code فایل CLAUDE.md را از root می‌خواند (خودکار)
        │
        ▼
پروتکل recovery اجرا می‌شود
        │
        ▼
8 فایل project-memory به‌ترتیب خوانده می‌شوند
        │
        ▼
Recovery Summary تولید می‌شود
        │
        ▼
ادامه کار از همان نقطه
```

---

## فایل‌های حافظه و ترتیب خواندن

| اولویت | فایل | هدف |
|--------|------|-----|
| 1 | `HANDOVER.md` | خلاصه کامل برای بازیابی فوری |
| 2 | `PROJECT_STATE.md` | وضعیت دقیق پروژه |
| 3 | `OPEN_TASKS.md` | تسک‌های باز و acceptance criteria |
| 4 | `ARCHITECTURE.md` | ساختار، سرویس‌ها، DB schema |
| 5 | `DECISIONS.md` | ADR log — تصمیمات معماری |
| 6 | `KNOWN_ISSUES.md` | باگ‌ها، debt، workaround‌ها |
| 7 | `AUDIT_HISTORY.md` | تمام یافته‌های audit |
| 8 | `CHANGELOG_AI.md` | تمام تغییرات AI |

---

## قانون به‌روزرسانی

بعد از هر task:

```bash
# فایل‌هایی که باید آپدیت شوند:
PROJECT_STATE.md    ← همیشه
CHANGELOG_AI.md     ← همیشه  
HANDOVER.md         ← همیشه

DECISIONS.md        ← فقط اگر تصمیم معماری جدید بود
AUDIT_HISTORY.md    ← فقط اگر audit انجام شد
KNOWN_ISSUES.md     ← فقط اگر issue جدید پیدا شد
OPEN_TASKS.md       ← فقط اگر وضعیت task تغییر کرد
```

---

## اگر CLAUDE.md خراب شد یا حذف شد

متن کامل پروتکل در `CLAUDE.md` ذخیره است.
این فایل فقط reference است.

برای بازسازی `CLAUDE.md`:
همین محتوا را در root پروژه با نام `CLAUDE.md` ذخیره کن.
