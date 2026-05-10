# Git hooks (همگام‌سازی قوانین Cursor)

یک‌بار در این مخزن اجرا کنید:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/setup-cursor-rules-hook.ps1
```

یا دستی:

```bash
git config core.hooksPath .githooks
```

پس از آن، با هر `commit` که فایل‌های `.cursor/rules/*.mdc` را تغییر دهد، اسکریپت `scripts/sync-cursor-rules.ps1` به‌صورت خودکار مخزن **customer-site** (کنار همین پوشه) را به‌روز می‌کند.
