# DISASTER RECOVERY PLAYBOOK
> Date: 2026-06-25
> Platform: SaaS Platform — `87.107.12.81:9011`
> Status: **CERTIFIED**

---

## Prerequisites

### Required Access
- SSH key: `~/.ssh/deploy_saas` (or equivalent)
- Server: `ssh -i ~/.ssh/deploy_saas -p 9011 root@87.107.12.81`
- Arvan Object Storage credentials: `ARVAN_ACCESS_KEY`, `ARVAN_SECRET_KEY`
- Arvan bucket: `safhenokhost-backups`
- Arvan endpoint: `https://s3.ir-thr-at1.arvanstorage.ir`
- Arvan region: `ir-thr-at1`

### AWS CLI Setup (for Arvan S3-compatible API)
```bash
pip install awscli  # or: apt install awscli -y
aws configure set aws_access_key_id      YOUR_ARVAN_ACCESS_KEY
aws configure set aws_secret_access_key  YOUR_ARVAN_SECRET_KEY
aws configure set region                 ir-thr-at1
# Use --endpoint-url https://s3.ir-thr-at1.arvanstorage.ir on every command
```

### Bucket Structure
```
safhenokhost-backups/
  saas-platform/
    database/      ← daily .sql.gz (saas_platform DB)
    archives/      ← on-event .zip (archived sites)
    storage/       ← weekly .tar.gz (public media)
  customer-sites/
    tenant-{id}/
      archives/    ← on-event .zip (per-tenant)
      database/    ← daily .sql.gz (customer_site DB)
```

---

## SCENARIO A — Customer Site Accidentally Deleted

**Definition:** A specific customer site (`sites` row) is deleted from the platform admin panel.
The site's data may be partially or fully removed depending on cascades.

### Risk Level: HIGH
Tenant data (orders, CRM, enrollments, canvas pages) is in `customer_site` DB scoped by `site_id`.

### Recovery Prerequisites
- The `site_id` of the deleted site
- A backup newer than the deletion event
- Access to the platform MySQL server

### Recovery Steps

#### Step 1 — Identify deleted site_id
```bash
ssh -i ~/.ssh/deploy_saas -p 9011 root@87.107.12.81
# Check platform DB for recently deleted sites:
mysql -u root saas_platform -e "
  SELECT id, domain, name, deleted_at
  FROM sites
  WHERE deleted_at IS NOT NULL
  ORDER BY deleted_at DESC
  LIMIT 5;
"
# Note the site_id (e.g., id=7)
```

#### Step 2 — Download latest customer_site DB backup
```bash
# List available backups for that tenant:
aws s3 ls s3://safhenokhost-backups/customer-sites/tenant-7/database/ \
    --endpoint-url https://s3.ir-thr-at1.arvanstorage.ir \
    | sort -k4 -r | head -5

# Download the most recent backup before deletion:
aws s3 cp "s3://safhenokhost-backups/customer-sites/tenant-7/database/backup_2026-06-25_020003.sql.gz" \
    /tmp/tenant7_restore.sql.gz \
    --endpoint-url https://s3.ir-thr-at1.arvanstorage.ir
```

#### Step 3 — Extract and filter tenant data
```bash
# Create restore DB:
mysql -u root -e "CREATE DATABASE IF NOT EXISTS restore_tenant7;"

# Load backup into restore DB:
gunzip < /tmp/tenant7_restore.sql.gz | mysql -u root restore_tenant7

# Verify tenant data is present:
mysql -u root restore_tenant7 -e "
  SELECT COUNT(*) as orders FROM orders WHERE site_id = 7;
"
```

#### Step 4 — Re-create the site record in platform DB
```bash
cd /var/www/saas-platform
# Use platform admin UI to re-create the site, OR insert directly:
mysql -u root saas_platform -e "
  UPDATE sites SET deleted_at = NULL WHERE id = 7;
"
```

#### Step 5 — Restore tenant data into customer_site DB
```bash
# Copy tenant-specific rows from restore DB back to customer_site:
# (For each table — orders, enrollments, crm_contacts, etc.)
mysql -u root restore_tenant7 -e "
  SELECT * FROM orders WHERE site_id = 7
" | mysql -u root customer_site

# Or use table-level mysqldump with --where:
mysqldump -u root restore_tenant7 orders --where="site_id=7" \
    | mysql -u root customer_site
```

#### Step 6 — Flush caches
```bash
cd /var/www/saas-platform && php artisan cache:clear
cd /var/www/customer-site && php artisan cache:clear
supervisorctl restart all
```

#### Step 7 — Validate
- [ ] Site appears in `/admin/sites`
- [ ] Customer can log in at their domain
- [ ] Orders/CRM data is present

### Expected Duration: 45–90 minutes
### Key Risk: Data created between last backup and deletion is permanently lost (max 24h gap)

---

## SCENARIO B — Customer Database Corrupted

**Definition:** `customer_site` MySQL database has table corruption, InnoDB crash, or data inconsistency.

### Risk Level: CRITICAL
Affects all tenants simultaneously (all sites share `customer_site` DB).

### Recovery Prerequisites
- Access to VPS
- Latest `customer-sites/tenant-1/database/` backup in Arvan
- Estimated downtime: 20–60 minutes

### Recovery Steps

#### Step 1 — Stop all traffic
```bash
ssh -i ~/.ssh/deploy_saas -p 9011 root@87.107.12.81
# Put site in maintenance:
cd /var/www/customer-site && php artisan down
cd /var/www/saas-platform && php artisan down
# Stop queue workers:
supervisorctl stop all
```

#### Step 2 — Attempt MySQL repair first
```bash
mysqlcheck -u root --repair --all-databases customer_site
# If repair fails, proceed to restore
```

#### Step 3 — Download latest DB backup
```bash
aws s3 ls s3://safhenokhost-backups/customer-sites/tenant-1/database/ \
    --endpoint-url https://s3.ir-thr-at1.arvanstorage.ir \
    | sort -k4 -r | head -3

aws s3 cp "s3://safhenokhost-backups/customer-sites/tenant-1/database/LATEST_FILE.sql.gz" \
    /tmp/customer_site_restore.sql.gz \
    --endpoint-url https://s3.ir-thr-at1.arvanstorage.ir
```

#### Step 4 — Verify backup integrity
```bash
gzip -t /tmp/customer_site_restore.sql.gz && echo "BACKUP OK" || echo "BACKUP CORRUPTED"
```

#### Step 5 — Restore database
```bash
# Drop and recreate (DESTRUCTIVE — confirm backup is safe first):
mysql -u root -e "DROP DATABASE IF EXISTS customer_site; CREATE DATABASE customer_site CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Restore:
gunzip < /tmp/customer_site_restore.sql.gz | mysql -u root customer_site

# Verify row counts:
mysql -u root customer_site -e "
  SELECT table_name, table_rows
  FROM information_schema.tables
  WHERE table_schema = 'customer_site'
  ORDER BY table_rows DESC LIMIT 10;
"
```

#### Step 6 — Run migrations for any new tables since backup
```bash
cd /var/www/customer-site && php artisan migrate --force
```

#### Step 7 — Bring system back online
```bash
cd /var/www/customer-site && php artisan up && php artisan cache:clear && php artisan config:cache
cd /var/www/saas-platform && php artisan up && php artisan cache:clear
supervisorctl start all
```

#### Step 8 — Validate
- [ ] All sites respond (check `/health/ready`)
- [ ] Platform admin shows correct site count
- [ ] Test login on a customer domain
- [ ] Check `infra_backup_runs` for next scheduled backup

### Expected Duration: 20–60 minutes
### Key Risk: Up to 24 hours of data loss across ALL tenants

---

## SCENARIO C — Entire customer-site Repository Lost

**Definition:** `/var/www/customer-site/` directory is deleted or corrupted. Application files gone.

### Risk Level: HIGH
Requires full application reinstall. Data (DB) is separate and safe in backups.

### Recovery Prerequisites
- Access to GitHub repository (code is pushed)
- VPS root access
- `.env` credentials (documented below)
- Expected downtime: 30–90 minutes

### Required .env Values for customer-site
```
APP_NAME="Customer Site"
APP_ENV=production
APP_KEY=base64:...  ← critical — must use same key as encrypted data
APP_DEBUG=false
APP_URL=https://safhenokhost.ir

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=customer_site
DB_USERNAME=root
DB_PASSWORD=...

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

PLATFORM_URL=http://panel.safhenokhost.ir
PLATFORM_API_KEY=...
PLATFORM_SITE_ID=1
LICENSE_VERIFY_SSL=false

ARVAN_ACCESS_KEY=...
ARVAN_SECRET_KEY=...
ARVAN_ENDPOINT=https://s3.ir-thr-at1.arvanstorage.ir
ARVAN_BUCKET=safhenokhost-backups
ARVAN_REGION=ir-thr-at1
```

### Recovery Steps

#### Step 1 — Re-clone repository
```bash
ssh -i ~/.ssh/deploy_saas -p 9011 root@87.107.12.81
cd /var/www
rm -rf customer-site-broken  # if partially present
git clone git@github.com:YOUR_ORG/customer-site.git customer-site
cd customer-site
```

#### Step 2 — Install dependencies
```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build
```

#### Step 3 — Restore .env
```bash
# Copy .env from backup or manually create using values above
nano /var/www/customer-site/.env
```

#### Step 4 — Run application setup
```bash
php artisan key:generate --force  # ONLY if APP_KEY is lost
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### Step 5 — Restore storage uploads from Arvan
```bash
# Download latest platform storage backup:
aws s3 ls s3://safhenokhost-backups/saas-platform/storage/ \
    --endpoint-url https://s3.ir-thr-at1.arvanstorage.ir | sort -k4 -r | head -3

aws s3 cp "s3://safhenokhost-backups/saas-platform/storage/LATEST.tar.gz" \
    /tmp/storage_restore.tar.gz \
    --endpoint-url https://s3.ir-thr-at1.arvanstorage.ir

mkdir -p /var/www/customer-site/storage/app/public
tar -xzf /tmp/storage_restore.tar.gz -C /var/www/customer-site/storage/app/public/
chown -R www-data:www-data /var/www/customer-site/storage/
```

#### Step 6 — Fix Nginx config and Supervisor
```bash
# Nginx should still be configured — verify:
nginx -t && systemctl reload nginx

# Supervisor workers:
supervisorctl restart all
```

#### Step 7 — Validate
- [ ] `curl http://localhost/health/ready` returns 200
- [ ] Customer admin panel accessible
- [ ] Queue workers running (`supervisorctl status`)

### Expected Duration: 30–90 minutes
### Key Risk: APP_KEY must be the SAME as used when data was encrypted (sessions, passwords). If lost, all sessions/encrypted fields are invalid.

---

## SCENARIO D — Entire saas-platform Repository Lost

**Definition:** `/var/www/saas-platform/` directory is deleted or corrupted.

### Risk Level: CRITICAL
Platform admin, API, and package host are all down. All customer sites lose API/license connectivity.

### Recovery Prerequisites
- GitHub access (`saas-platform` repo)
- VPS root access
- `.env` for saas-platform
- Composer vendor packages (local packages in repo)

### Required .env Values for saas-platform
```
APP_NAME="SaaS Platform"
APP_ENV=production
APP_KEY=base64:...  ← must match original
APP_DEBUG=false
APP_URL=https://panel.safhenokhost.ir

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=saas_platform
DB_USERNAME=root
DB_PASSWORD=...

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

ARVAN_ACCESS_KEY=...
ARVAN_SECRET_KEY=...
ARVAN_ENDPOINT=https://s3.ir-thr-at1.arvanstorage.ir
ARVAN_BUCKET=safhenokhost-backups
ARVAN_REGION=ir-thr-at1

SLACK_ALERT_WEBHOOK=https://hooks.slack.com/...
SLACK_ALERT_CHANNEL=#alerts

ANTHROPIC_API_KEY=sk-ant-...
```

### Recovery Steps

#### Step 1 — Re-clone and install
```bash
ssh -i ~/.ssh/deploy_saas -p 9011 root@87.107.12.81
cd /var/www
git clone git@github.com:YOUR_ORG/saas-platform.git saas-platform
cd saas-platform
composer install --no-dev --optimize-autoloader
```

#### Step 2 — Restore .env
```bash
nano /var/www/saas-platform/.env
# Fill all values above
```

#### Step 3 — Restore platform database from Arvan
```bash
# Download latest platform DB backup:
aws s3 ls s3://safhenokhost-backups/saas-platform/database/ \
    --endpoint-url https://s3.ir-thr-at1.arvanstorage.ir | sort -k4 -r | head -5

aws s3 cp "s3://safhenokhost-backups/saas-platform/database/db_backup_2026-06-25_020003.sql.gz" \
    /tmp/saas_restore.sql.gz \
    --endpoint-url https://s3.ir-thr-at1.arvanstorage.ir

# Verify integrity:
gzip -t /tmp/saas_restore.sql.gz && echo "OK"

# Restore:
mysql -u root -e "DROP DATABASE IF EXISTS saas_platform; CREATE DATABASE saas_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gunzip < /tmp/saas_restore.sql.gz | mysql -u root saas_platform
```

#### Step 4 — Setup application
```bash
php artisan migrate --force
php artisan db:seed --class=SystemSeeder  # if needed for admin user
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

#### Step 5 — Restore storage uploads
```bash
aws s3 cp "s3://safhenokhost-backups/saas-platform/storage/LATEST.tar.gz" /tmp/ \
    --endpoint-url https://s3.ir-thr-at1.arvanstorage.ir

mkdir -p /var/www/saas-platform/storage/app/public
tar -xzf /tmp/LATEST.tar.gz -C /var/www/saas-platform/storage/app/public/
chown -R www-data:www-data /var/www/saas-platform/storage/
```

#### Step 6 — Restart all services
```bash
supervisorctl restart all
systemctl reload nginx
```

#### Step 7 — Validate
- [ ] `/health/ready` returns ready
- [ ] Platform admin login works (`/admin/login`)
- [ ] All customer sites reconnect (license validation)
- [ ] `php artisan monitor:platform` runs without errors

### Expected Duration: 60–120 minutes
### Key Risk: APP_KEY loss invalidates all encrypted data. All license keys in DB must match.

---

## SCENARIO E — Entire VPS Destroyed

**Definition:** The VPS at `87.107.12.81` is permanently lost (hardware failure, provider deletion, ransom). Both repos and both databases are destroyed.

### Risk Level: MAXIMUM CATASTROPHIC
Full rebuild on a new server from scratch.

### Recovery Prerequisites
- New VPS (Ubuntu 22.04 LTS, min 2 vCPU / 4 GB RAM / 40 GB disk)
- GitHub access for both repos
- Arvan backup credentials (stored OUTSIDE the VPS — e.g., password manager)
- Domain DNS control (ArvanCloud CDN)
- All `.env` values documented above (Scenarios C + D)

### New VPS Setup

#### Step 1 — Provision server
```bash
# Recommended: Ubuntu 22.04 LTS on any provider
# Initial setup:
apt update && apt upgrade -y
apt install -y nginx mysql-server redis-server php8.4-fpm php8.4-mysql \
    php8.4-redis php8.4-gd php8.4-curl php8.4-zip php8.4-mbstring \
    php8.4-xml php8.4-bcmath supervisor git unzip curl awscli

# Install Composer:
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Node.js 20:
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
```

#### Step 2 — MySQL setup
```bash
mysql_secure_installation
mysql -u root -e "
  CREATE DATABASE saas_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE DATABASE customer_site CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'app'@'localhost' IDENTIFIED BY 'YOUR_DB_PASSWORD';
  GRANT ALL PRIVILEGES ON saas_platform.* TO 'app'@'localhost';
  GRANT ALL PRIVILEGES ON customer_site.* TO 'app'@'localhost';
  FLUSH PRIVILEGES;
"
```

#### Step 3 — Configure Arvan S3 access
```bash
aws configure set aws_access_key_id      YOUR_ARVAN_ACCESS_KEY
aws configure set aws_secret_access_key  YOUR_ARVAN_SECRET_KEY
aws configure set region                 ir-thr-at1
```

#### Step 4 — Download and restore databases
```bash
# SaaS Platform DB:
aws s3 ls s3://safhenokhost-backups/saas-platform/database/ \
    --endpoint-url https://s3.ir-thr-at1.arvanstorage.ir | sort -k4 -r | head -3

aws s3 cp "s3://safhenokhost-backups/saas-platform/database/LATEST.sql.gz" /tmp/saas.sql.gz \
    --endpoint-url https://s3.ir-thr-at1.arvanstorage.ir

gunzip < /tmp/saas.sql.gz | mysql -u root saas_platform

# Customer Site DB:
aws s3 cp "s3://safhenokhost-backups/customer-sites/tenant-1/database/LATEST.sql.gz" /tmp/cs.sql.gz \
    --endpoint-url https://s3.ir-thr-at1.arvanstorage.ir

gunzip < /tmp/cs.sql.gz | mysql -u root customer_site
```

#### Step 5 — Deploy both applications
```bash
cd /var/www
git clone git@github.com:YOUR_ORG/saas-platform.git
git clone git@github.com:YOUR_ORG/customer-site.git

# SaaS Platform:
cd /var/www/saas-platform
composer install --no-dev --optimize-autoloader
cp .env.example .env  # then fill all values
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan storage:link

# Customer Site:
cd /var/www/customer-site
composer install --no-dev --optimize-autoloader
npm install && npm run build
cp .env.example .env  # then fill all values
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan storage:link
```

#### Step 6 — Restore uploaded files
```bash
# Platform storage:
aws s3 cp "s3://safhenokhost-backups/saas-platform/storage/LATEST.tar.gz" /tmp/ \
    --endpoint-url https://s3.ir-thr-at1.arvanstorage.ir
mkdir -p /var/www/saas-platform/storage/app/public
tar -xzf /tmp/LATEST.tar.gz -C /var/www/saas-platform/storage/app/public/

# Fix permissions:
chown -R www-data:www-data /var/www/saas-platform/storage/ /var/www/customer-site/storage/
find /var/www -type d -exec chmod 755 {} \;
find /var/www -type f -exec chmod 644 {} \;
chmod -R 775 /var/www/saas-platform/storage /var/www/customer-site/storage
```

#### Step 7 — Configure Nginx
```bash
# Copy nginx configs from /etc/nginx/sites-available/ (document these configs separately)
# Minimal config example:
cat > /etc/nginx/sites-available/saas-platform << 'EOF'
server {
    listen 80;
    server_name panel.safhenokhost.ir;
    root /var/www/saas-platform/public;
    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}
EOF

ln -s /etc/nginx/sites-available/saas-platform /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

#### Step 8 — Configure Supervisor
```bash
# Create worker config:
cat > /etc/supervisor/conf.d/saas-workers.conf << 'EOF'
[program:saas-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/saas-platform/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/var/www/saas-platform/storage/logs/worker.log

[program:customer-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/customer-site/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/customer-site/storage/logs/worker.log
EOF

supervisorctl reread && supervisorctl update && supervisorctl start all
```

#### Step 9 — Configure scheduler cron
```bash
echo "* * * * * www-data cd /var/www/saas-platform && php artisan schedule:run >> /dev/null 2>&1" \
    > /etc/cron.d/saas-scheduler
echo "* * * * * www-data cd /var/www/customer-site && php artisan schedule:run >> /dev/null 2>&1" \
    >> /etc/cron.d/saas-scheduler
```

#### Step 10 — Update DNS
```bash
# In ArvanCloud CDN panel:
# - Point panel.safhenokhost.ir → new VPS IP
# - Point safhenokhost.ir → new VPS IP
# DNS propagation: 5–30 minutes
```

### Final Validation Checklist
- [ ] `/health/ready` returns `ready: true` on platform
- [ ] Platform admin login (`/admin/login`) works
- [ ] Customer site loads at domain
- [ ] Queue workers running: `supervisorctl status`
- [ ] Scheduler running: `php artisan schedule:list`
- [ ] Backup runs: `php artisan db:backup` completes with Arvan upload
- [ ] Monitoring active: `php artisan monitor:platform`
- [ ] Slack alert test: `php artisan slack:test`

### Expected Duration: 3–6 hours
### Key Risks:
1. APP_KEY must be restored exactly — if lost, encrypted fields are unrecoverable
2. Domain DNS cutover adds 5–30 minute propagation delay
3. Storage media gap: up to 7 days (weekly backup) of customer-uploaded files may be lost

---

## Recovery Contact Runbook

| Action | Contact | Method |
|--------|---------|--------|
| VPS re-provision | Hosting provider | Provider console |
| DNS update | ArvanCloud panel | Web console |
| Arvan bucket access | Admin holding credentials | Password manager |
| GitHub repo access | Repository owner | GitHub web |

---

## Estimated Recovery Times Summary

| Scenario | RTO (Recovery Time) | RPO (Data Loss) |
|----------|---------------------|-----------------|
| A — Site deleted | 45–90 min | 0–24 hours |
| B — DB corrupted | 20–60 min | 0–24 hours |
| C — customer-site app lost | 30–90 min | 0–7 days (storage) |
| D — saas-platform app lost | 60–120 min | 0–7 days (storage) |
| E — Entire VPS destroyed | 3–6 hours | 0–24 hours (DB) / 0–7 days (storage) |

*DISASTER_RECOVERY_PLAYBOOK — generated 2026-06-25*
