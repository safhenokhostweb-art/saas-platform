#!/bin/bash
PROJECT=$1
if [ -z "$PROJECT" ]; then echo "Usage: deploy.sh saas|customer"; exit 1; fi
if [ "$PROJECT" = "saas" ]; then DIR="/var/www/saas-platform"
elif [ "$PROJECT" = "customer" ]; then DIR="/var/www/customer-site"
else echo "Unknown project: $PROJECT"; exit 1; fi
echo "=== Deploying $PROJECT to $DIR ==="
cd "$DIR"
echo "=== Backing up .env ==="
cp "$DIR/.env" /tmp/env_backup_$PROJECT
echo "=== Extracting files ==="
unzip -o /tmp/deploy_${PROJECT}.zip -d "$DIR" > /dev/null 2>&1
echo "=== Restoring .env ==="
cp /tmp/env_backup_$PROJECT "$DIR/.env"
echo "=== Syncing local packages into vendor ==="
for PKG in core-utils modules-education modules-forms modules-tickets; do
    SRC="$DIR/packages/saas-platform/$PKG"
    DEST="$DIR/vendor/saas-platform/$PKG"
    if [ -d "$SRC" ] && [ -d "$DEST" ]; then
        rsync -a --delete "$SRC/" "$DEST/" && echo "    synced $PKG"
    fi
done
echo "=== Refreshing package cache ==="
rm -f "$DIR/bootstrap/cache/packages.php"
echo "=== Setting permissions ==="
chown -R www-data:www-data "$DIR"
chmod -R 775 "$DIR/storage"
chmod -R 775 "$DIR/bootstrap/cache"
echo "=== Discovering packages ==="
php artisan package:discover --ansi 2>&1 | tail -3
echo "=== Clearing caches ==="
php artisan config:clear
php artisan route:clear
php artisan view:clear
echo "=== Running migrations ==="
php artisan migrate --force
echo "=== Rebuilding caches ==="
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo "=== Restarting queue workers ==="
php artisan queue:restart
echo "=== Reloading PHP-FPM ==="
systemctl restart php8.3-fpm
echo "=== DONE: $PROJECT deployed successfully ==="
