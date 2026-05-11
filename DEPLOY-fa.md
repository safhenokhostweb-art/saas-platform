# راهنمای استقرار (Deploy) — saas-platform

> راهنمای فارسی استفاده از اسکریپت‌های `deploy.sh` و `deploy.bat` در مخزن **saas-platform**.
> برای مرجع فنی کامل (انگلیسی) به [`DEPLOY.md`](./DEPLOY.md) مراجعه کنید.

## فهرست

1. [این اسکریپت چه می‌کند و کجا اجرا می‌شود؟](#۱-این-اسکریپت-چه-میکند-و-کجا-اجرا-میشود)
2. [گردش‌کار کامل — از Cursor تا Production](#۲-گردشکار-کامل--از-cursor-تا-production)
3. [اجرای اسکریپت روی Linux](#۳-اجرای-اسکریپت-روی-linux)
4. [اجرای اسکریپت روی Windows Server](#۴-اجرای-اسکریپت-روی-windows-server)
5. [مثال‌های واقعی](#۵-مثالهای-واقعی)
6. [بازگشت (Rollback) در صورت خرابی](#۶-بازگشت-rollback-در-صورت-خرابی)
7. [اولین deploy روی سرور جدید](#۷-اولین-deploy-روی-سرور-جدید)
8. [تضمین‌های ایمنی](#۸-تضمینهای-ایمنی)
9. [پرسش‌های متداول](#۹-پرسشهای-متداول)
10. [خلاصه دستورات](#۱۰-خلاصه-دستورات)

---

## ۱. این اسکریپت چه می‌کند و کجا اجرا می‌شود؟

اسکریپت‌های `deploy.sh` (لینوکس) و `deploy.bat` (ویندوز) برای **سرور تولید (production)** ساخته شده‌اند، نه برای ماشین توسعهٔ شما در Laragon.

| محیط | چه کار می‌کنید؟ | اسکریپت اجرا می‌کنید؟ |
|---|---|---|
| 🖥️ **Laragon (لپ‌تاپ شما)** | کدنویسی، تست، commit، push به GitHub | ❌ **خیر** — هیچوقت |
| 🌐 **سرور Staging** | شبیه‌سازی production قبل از deploy نهایی | ✅ بله |
| 🌐 **سرور Production** | پنل ادمین زندهٔ پلتفرم | ✅ بله (بعد از تأیید staging) |

> اگر سرور staging ندارید، production تنها مقصد است. اما همیشه ابتدا یک backup دیتابیس بگیرید.

### مراحلی که اسکریپت طی می‌کند (به ترتیب)

1. ساخت **تگ پشتیبان** با نام `deploy-backup-YYYYMMDD-HHMMSS` (نقطهٔ بازگشت)
2. `git fetch origin`
3. `git checkout main`
4. `git pull --ff-only origin main` *(فقط fast-forward — هیچ overwrite نمی‌کند)*
5. `composer install --no-dev --optimize-autoloader`
6. `php artisan migrate --force` *(فقط migrationهای جدید — هرگز داده پاک نمی‌کند)*
7. `php artisan optimize:clear`
8. `php artisan optimize`
9. *(اختیاری)* اگر Node و `package-lock.json` موجود باشند: `npm ci && npm run build`
10. یادآوری restart کردن queue workers (در صورت وجود)

**اگر هر مرحله fail کند، اسکریپت بلافاصله متوقف می‌شود** و هیچ گام بعدی اجرا نخواهد شد.

---

## ۲. گردش‌کار کامل — از Cursor تا Production

```
   شما در Cursor              GitHub                  سرور Production
   ──────────────             ──────                  ───────────────
   
   ۱. کد می‌نویسید
   ۲. git add + commit
   ۳. git push origin main  ─►  main به‌روز شد
                                                       ۴. SSH/RDP به سرور
                                                       ۵. cd /path/to/saas-platform
                                                       ۶. ./deploy.sh
                                                          (یا deploy.bat در ویندوز)
                                                       
                                                       ۷. اسکریپت خودکار:
                                                          - tag پشتیبان می‌سازد
                                                          - git pull
                                                          - composer install
                                                          - migrate --force
                                                          - بازسازی cache
                                                          - build فرانت (اگر لازم)
                                                       
                                                       ۸. سایت با کد جدید up است
```

---

## ۳. اجرای اسکریپت روی Linux

```bash
# یک‌بار برای همیشه (اولین اجرا):
cd /var/www/saas-platform        # یا هر مسیری که repo را clone کرده‌اید
chmod +x deploy.sh

# هر بار که می‌خواهید deploy کنید:
./deploy.sh
```

تمام شد. اسکریپت گزارش زنده می‌دهد و در پایان نمایش می‌دهد:

```text
[OK] Step 1: rollback tag created
[OK] Step 2: git fetch origin
[OK] Step 3: git checkout main
[OK] Step 4: git pull origin main
[OK] Step 5: composer install
[OK] Step 6: php artisan migrate --force
[OK] Step 7: php artisan optimize:clear
[OK] Step 8: php artisan optimize

===============================================================
[OK] saas-platform deploy completed successfully
 before: 309ad1a
 after:  a7d1308
 backup tag (rollback): deploy-backup-20260511-201500
===============================================================
```

---

## ۴. اجرای اسکریپت روی Windows Server

### در `cmd.exe`

```bat
cd C:\inetpub\wwwroot\saas-platform
deploy.bat
```

### در PowerShell

```powershell
cd C:\inetpub\wwwroot\saas-platform
.\deploy.bat
```

> دقت کنید: `.\` در ابتدای دستور در PowerShell **الزامی** است.

---

## ۵. مثال‌های واقعی

### مثال ۱: Deploy تغییرات Wave 3 به پنل پلتفرم

فرض کنید Wave 3 (شامل endpoint جدید `module-history` و audit logging) را push کرده‌اید و می‌خواهید روی سرور پنل ادمین فعال کنید:

```bash
ssh user@your-platform-server.com
cd /var/www/saas-platform
./deploy.sh
```

پس از موفقیت، در پنل ادمین مسیرهای زیر را تست کنید:
- `/admin/dashboard` — باید بدون خطا باز شود
- `/admin/activity-log` — رویدادهای جدید `module.updated` و `module.bulk_updated` باید قابل دیدن باشند
- یک تغییر کوچک در کاتالوگ ماژول → بررسی ثبت رویداد در activity log

### مثال ۲: یک bug fix سریع

```bash
# روی Cursor (لپ‌تاپ)
git add .
git commit -m "fix: correct date display in invoice"
git push origin main

# روی سرور
ssh user@server
cd /var/www/saas-platform
./deploy.sh
```

### مثال ۳: Deploy روی Windows Server با IIS

```powershell
# RDP به سرور ویندوز
cd C:\inetpub\wwwroot\saas-platform
.\deploy.bat

# اگر site بالا نیامد:
iisreset
```

---

## ۶. بازگشت (Rollback) در صورت خرابی

اسکریپت قبل از هر deploy یک تگ مثل `deploy-backup-20260511-201500` می‌سازد که به commit قبل از deploy اشاره می‌کند.

### حالت ۱: فقط کد مشکل دارد (migration جدید نبوده)

```bash
# مشاهدهٔ تگ‌های موجود
git tag --list 'deploy-backup-*'

# بازگشت به آخرین وضعیت سالم (مثال — تاریخ خود را قرار دهید)
git reset --hard deploy-backup-20260511-201500
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
php artisan optimize:clear
php artisan optimize
```

### حالت ۲: migration جدید هم اجرا شده و باید برگردد

```bash
# ابتدا migration ها را برگردانید (هر بار یک گام)
php artisan migrate:rollback --step=1

# بعد کد را برگردانید
git reset --hard deploy-backup-20260511-201500
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
php artisan optimize:clear
php artisan optimize
```

> ⚠️ **هشدار:** قبل از `migrate:rollback` حتماً یک dump از دیتابیس بگیرید. برخی migrationها (مثل DROP COLUMN با داده) ممکن است reversible نباشند.

---

## ۷. اولین deploy روی سرور جدید

اگر هنوز repo را روی سرور clone نکرده‌اید:

```bash
# 1. clone از GitHub
git clone https://github.com/safhenokhostweb-art/saas-platform.git
cd saas-platform

# 2. .env را از فایل امن خودتان کپی کنید
nano .env
# مقادیر مهم:
#   APP_KEY (هرگز در git نباشد، هرگز در production rotate نشود — رجوع به DEPLOY.md §9)
#   DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
#   APP_URL

# 3. نصب اولیه
composer install --no-dev --optimize-autoloader
php artisan key:generate --force        # فقط اگر APP_KEY خالی است
php artisan storage:link
php artisan migrate --force
php artisan optimize

# 4. تنظیم webserver (Apache/Nginx) برای سرو پوشهٔ public/

# 5. از این پس هر deploy:
./deploy.sh
```

---

## ۸. تضمین‌های ایمنی

اسکریپت طبق قوانین SaaS این پروژه (`.cursor/rules/global-rules.mdc`) طراحی شده. این کارها را **هرگز** انجام نمی‌دهد:

- ❌ `migrate:fresh` یا `db:wipe` نمی‌زند
- ❌ `git reset` به وضعیت قدیمی نمی‌زند (به‌جز در rollback دستی)
- ❌ `git clean` نمی‌زند (فایل‌های untracked شما حفظ می‌شوند)
- ❌ داده‌های مشتری را پاک نمی‌کند
- ❌ ماژول‌های خریداری‌شده را غیرفعال نمی‌کند
- ❌ `force push` به remote نمی‌زند (در واقع هیچ push نمی‌زند)
- ❌ اگر سرور commit محلی داشته باشد که با main تضاد دارد، deploy **متوقف می‌شود** (نه overwrite)
- ❌ اگر هر مرحله fail کند، گام بعدی اجرا نمی‌شود

علاوه بر این:
- ✅ قبل از هر deploy یک تگ پشتیبان می‌سازد (نقطهٔ بازگشت تضمینی)
- ✅ commit hash قبل و بعد از deploy را چاپ می‌کند
- ✅ پیش‌نیازها را قبل از شروع چک می‌کند (git، composer، php، artisan)

---

## ۹. پرسش‌های متداول

**س: اگر سرور من ویندوز است ولی Composer/PHP نصب نیست؟**
ج: اسکریپت در مرحلهٔ بررسی پیش‌نیازها متوقف می‌شود و پیام `[FAIL] composer is not installed or not in PATH.` می‌دهد. هیچ آسیبی به سیستم نمی‌رسد.

**س: اگر `.env` تغییر کرده باشد چه؟**
ج: اسکریپت `.env` را دست نمی‌زند (در `.gitignore` است). اگر متغیر env جدیدی به پروژه اضافه شده، آن را **دستی** به `.env` تولید اضافه کنید قبل از deploy.

**س: queue workerها چطور؟**
ج: اسکریپت در پایان یادآوری چاپ می‌کند. شما باید جداگانه:
```bash
php artisan queue:restart
# اگر Supervisor دارید:
sudo supervisorctl restart all
```

**س: می‌توانم اسکریپت را روی Laragon (لپ‌تاپ خودم) اجرا کنم؟**
ج: فنی ممکن است، اما **توصیه نمی‌شود**. این اسکریپت برای محیطی است که سایت روی آن سرو می‌شود. روی dev machine، با همان دستورات معمولی Laravel کار کنید:
```bash
git pull
composer install
php artisan migrate
```

**س: اگر pull --ff-only fail کرد چه کنم؟**
ج: یعنی سرور commit محلی دارد که با main GitHub تضاد دارد. این تقریباً همیشه یک اشتباه است (فایل‌های production نباید مستقیم روی سرور تغییر کنند). ابتدا بررسی کنید:
```bash
git log --oneline origin/main..HEAD
```
اگر چیز مهمی نبود، آن را stash یا revert کنید و دوباره deploy کنید.

**س: یک migration بد به سرور رفت، چه کنم؟**
ج: اگر migration **reversible** است:
```bash
php artisan migrate:rollback --step=1
```
سپس rollback کد به تگ پشتیبان (بخش ۶). اگر **irreversible** بود، باید دیتابیس را از backup قبل از deploy restore کنید.

---

## ۱۰. خلاصه دستورات

| می‌خواهید… | دستور |
|---|---|
| اولین deploy روی سرور Linux | `chmod +x deploy.sh && ./deploy.sh` |
| Deploy عادی روی Linux | `./deploy.sh` |
| Deploy عادی روی Windows (cmd) | `deploy.bat` |
| Deploy عادی روی Windows (PowerShell) | `.\deploy.bat` |
| دیدن لیست تگ‌های پشتیبان | `git tag --list 'deploy-backup-*'` |
| Rollback سریع کد | `git reset --hard deploy-backup-YYYYMMDD-HHMMSS` + composer + optimize |
| Restart queueها | `php artisan queue:restart` |
| خواندن راهنمای فنی کامل (انگلیسی) | `cat DEPLOY.md` |

---

**وضعیت مرجع Phase 5 / Wave 3:** `main` در `a7d1308` (پلتفرم)، تگ `pre-phase5-baseline` حفظ شده.
