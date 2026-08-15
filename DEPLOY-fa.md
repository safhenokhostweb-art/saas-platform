# راهنمای استقرار (Deploy) — saas-platform

> **هشدار مسیر قدیمی:** فایل‌های `deploy.ps1`، `deploy.sh` و `deploy.bat` مسدود شده‌اند و نباید برای production استفاده شوند. مرجع انتشار در حال انتقال به `deployment-system` است؛ انتشار فقط از revision کامیت‌شده و artifact اعتبارسنجی‌شده انجام می‌شود.

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
9. [تگ‌های Wave-tip — نام دائمی هر مرحله](#۹-تگهای-wave-tip--نام-دائمی-هر-مرحله)
10. [پرسش‌های متداول](#۱۰-پرسشهای-متداول)
11. [خلاصه دستورات](#۱۱-خلاصه-دستورات)

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

## ۹. تگ‌های Wave-tip — نام دائمی هر مرحله

### این تگ‌ها چه هستند و چه کاری برای شما می‌کنند؟

در طول Phase 5، هر بار که یک «موج» (Wave) تغییرات کامل شد، آن مجموعه تغییرات به `main` ادغام (merge) شد. شاخه‌های `phase5-wave1`, `phase5-wave2`, `phase5-wave3` فقط در لپ‌تاپ شما به‌عنوان feature branch موقت ساخته شدند و به GitHub push نشدند تا مخزن remote تمیز بماند.

**مشکل:** اگر روزی این شاخه‌های محلی به‌اشتباه پاک شوند (مثلاً `git branch -D phase5-wave3`)، **commitها از دست نمی‌روند** (چون از طریق merge commit در main همچنان قابل دسترسی هستند)، اما **نامِ خوانا و قابل تایپ** آن نقطه از تاریخ گم می‌شود و باید با یک hash هفت‌رقمی مثل `95e4c61` کار کنید.

**راه‌حل:** برای هر wave یک **lightweight tag** ساخته شد که نقش «نام دائمی» آن نقطه را بازی می‌کند:

| تگ | اشاره به | شامل چه commitی است |
|---|---|---|
| `phase5-wave1-tip` | `03092ae` | آخرین commit شاخهٔ wave1 (قبل از merge به main): hardening، cleanup، P0-3، P0-7، حذف credentials |
| `phase5-wave2-tip` | `a8d32cd` | آخرین commit شاخهٔ wave2: P1-4 (password hashed cast)، P0-5 (HMAC license hashing) |
| `phase5-wave3-tip` | `95e4c61` | آخرین commit شاخهٔ wave3: P0-6 (module-history API)، P1-3 (ModuleController audit) |

### تفاوت با تگ‌های `deploy-backup-*`

| ویژگی | `phase5-wave*-tip` | `deploy-backup-YYYYMMDD-HHMMSS` |
|---|---|---|
| چه زمانی ساخته می‌شود؟ | یک بار، در پایان هر wave (دستی) | خودکار، در ابتدای هر deploy |
| هدف | مرور تاریخ توسعه، مقایسه، rollback مرحله‌ای | نقطهٔ بازگشت اضطراری برای deploy ناموفق |
| طول عمر | دائمی (نگه دارید) | موقتی (پس از deploy موفق می‌توانید حذف کنید) |
| Push به GitHub؟ | خیر (محلی می‌مانند) | خیر |

### تفاوت با شاخه (`branch`)

| ویژگی | تگ (tag) | شاخه (branch) |
|---|---|---|
| تغییر می‌کند با commit جدید؟ | ❌ نه — همیشه به همان commit ثابت می‌ماند | ✅ بله — با هر commit جلو می‌رود |
| می‌توان checkout کرد؟ | ✅ بله | ✅ بله |
| می‌توان commit جدید روی آن زد؟ | ❌ نه (detached HEAD می‌شوید) | ✅ بله |
| فضای دیسک | بسیار کم (یک خط در `.git/refs/tags/`) | بسیار کم |
| در صفحهٔ Branches روی GitHub؟ | خیر (در قسمت Tags/Releases) | بله |

به‌طور خلاصه: **تگ یک ثبت ثابت از یک نقطهٔ تاریخی است؛ شاخه یک خط رو به جلو از کار است.** برای «اسم دائمی برای نقطه‌ای از گذشته»، تگ بهترین گزینه است.

### چطور از این تگ‌ها استفاده می‌کنید؟

#### سناریو ۱: دیدن وضعیت پروژه در پایان یک wave

```bash
# پروژه را به‌صورت موقت به آن نقطه برمی‌گردانیم (read-only)
git checkout phase5-wave2-tip

# الان فایل‌های روی دیسک، دقیقاً همان وضعیت پایان Wave 2 هستند
ls app/Services/                    # ببینید چه فایل‌هایی بود
cat docs/CHANGELOG.md                # چنج‌لاگ آن زمان

# بازگشت به وضعیت فعلی (آخرین main)
git checkout main
```

#### سناریو ۲: مقایسه دو wave

```bash
# همه فایل‌هایی که بین پایان Wave 1 و پایان Wave 2 تغییر کردند
git diff phase5-wave1-tip phase5-wave2-tip --stat

# diff کامل یک فایل بین دو wave
git diff phase5-wave1-tip phase5-wave2-tip -- app/Services/LicenseServerService.php
```

#### سناریو ۳: دیدن لیست commit‌های هر wave

```bash
# لیست commit‌های wave3 (پس از merge wave2 تا پایان wave3)
git log phase5-wave2-tip..phase5-wave3-tip --oneline

# diff کامل با تک‌تک commit‌ها
git log phase5-wave2-tip..phase5-wave3-tip -p
```

#### سناریو ۴: دیدن وضعیت یک فایل خاص در نقطهٔ مشخص

```bash
# مدل User در پایان Wave 1
git show phase5-wave1-tip:app/Models/User.php

# همان فایل را در فایل موقتی برای مقایسه ذخیره کن
git show phase5-wave1-tip:app/Models/User.php > /tmp/user-wave1.php
```

#### سناریو ۵: ساخت یک شاخهٔ جدید از نقطه‌ای از گذشته (در صورت نیاز اضطراری)

```bash
# مثلاً اگر بخواهید روی پایان Wave 2 یک hotfix انجام دهید
git checkout -b hotfix-wave2 phase5-wave2-tip
# الان روی شاخهٔ جدید هستید و می‌توانید commit بزنید
```

### دستور دیدن همهٔ تگ‌های Phase 5

```bash
git tag --list 'phase5-wave*-tip'
# خروجی:
#   phase5-wave1-tip
#   phase5-wave2-tip
#   phase5-wave3-tip

# با hash هم نشان بده
git tag --list 'phase5-wave*-tip' --format='%(refname:short) -> %(objectname:short)'
```

### آیا این تگ‌ها روی GitHub هم هستند؟

**خیر — فقط روی این لپ‌تاپ.** این انتخاب آگاهانه است:
- مخزن GitHub تمیز و فقط با تگ‌های release رسمی می‌ماند
- شما به تاریخ کامل دسترسی دارید بدون شلوغی روی صفحهٔ Tags در GitHub
- اگر روزی بخواهید این تگ‌ها را روی GitHub هم داشته باشید، با یک دستور انجام می‌شود:

```bash
# push همهٔ تگ‌های phase5-wave*-tip به GitHub
git push origin phase5-wave1-tip phase5-wave2-tip phase5-wave3-tip

# یا push همهٔ تگ‌های دارای این الگو
git push origin --tags
# (هشدار: --tags هر تگ محلی را push می‌کند، شامل deploy-backup-* هم. توصیه نمی‌شود.)
```

### روال آینده — برای wave 4 و بعد از آن

پس از تکمیل و merge هر wave جدید، یک تگ مشابه بسازید:

```bash
# مثال برای Wave 4 (پس از merge به main)
# قبل از merge، tip شاخهٔ phase5-wave4 را پیدا کنید:
git rev-parse phase5-wave4
# فرض: a1b2c3d

# تگ بسازید
git tag phase5-wave4-tip a1b2c3d

# تأیید
git rev-parse phase5-wave4-tip
```

اگر فراموش کردید قبل از merge تگ بزنید، اشکالی ندارد — می‌توانید بعداً از merge commit در main استخراج کنید:

```bash
# parent دوم merge commit، tip شاخهٔ wave بود
git log --merges --oneline main | head -5    # پیدا کردن merge commit
git rev-parse <merge-commit-hash>^2          # parent دوم = tip wave
git tag phase5-wave4-tip <hash-بازیافت‌شده>
```

### اگر تگی به اشتباه پاک شد

تگ‌های lightweight بسیار آسان بازسازی می‌شوند چون شامل هیچ متادیتای خاصی نیستند:

```bash
# پاک کردن تگ
git tag -d phase5-wave2-tip

# بازسازی با همان hash (در hash بازسازی نیاز نیست رمز خاصی بدانید — همان commit ثابت است)
git tag phase5-wave2-tip a8d32cd
```

### چه چیز را تگ بزنیم، چه چیز را نزنیم

✅ **بله، تگ بزنید برای:**
- پایان هر Wave مهم (مثل Phase 5 / Wave 4)
- نسخه‌های release رسمی (`v1.0.0`, `v1.1.0`)
- نقاط منطقی بزرگ از پروژه که می‌خواهید نام داشته باشند

❌ **نه، تگ نزنید برای:**
- هر commit کوچک
- بخش‌های میانه کار wave (در حال توسعه)
- branchهای موقت debug

---

## ۱۰. پرسش‌های متداول

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

## ۱۱. خلاصه دستورات

| می‌خواهید… | دستور |
|---|---|
| اولین deploy روی سرور Linux | `chmod +x deploy.sh && ./deploy.sh` |
| Deploy عادی روی Linux | `./deploy.sh` |
| Deploy عادی روی Windows (cmd) | `deploy.bat` |
| Deploy عادی روی Windows (PowerShell) | `.\deploy.bat` |
| دیدن لیست تگ‌های پشتیبان deploy | `git tag --list 'deploy-backup-*'` |
| دیدن لیست تگ‌های wave-tip | `git tag --list 'phase5-wave*-tip'` |
| Rollback سریع کد به deploy قبلی | `git reset --hard deploy-backup-YYYYMMDD-HHMMSS` + composer + optimize |
| دیدن وضعیت پایان یک wave | `git checkout phase5-waveN-tip` (سپس `git checkout main` برای بازگشت) |
| مقایسهٔ دو wave | `git diff phase5-wave1-tip phase5-wave2-tip --stat` |
| لیست commit های یک wave | `git log phase5-wave2-tip..phase5-wave3-tip --oneline` |
| Restart queueها | `php artisan queue:restart` |
| خواندن راهنمای فنی کامل (انگلیسی) | `cat DEPLOY.md` |

---

**وضعیت مرجع Phase 5 / Wave 3:** `main` در `a7d1308` (پلتفرم)، تگ‌ها: `pre-phase5-baseline`، `phase5-wave1-tip` → `03092ae`، `phase5-wave2-tip` → `a8d32cd`، `phase5-wave3-tip` → `95e4c61`.
