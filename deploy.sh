#!/bin/sh
# Updates the live server from GitHub. Runs as the `cleartrack` user on the Droplet,
# either from GitHub Actions (.github/workflows/deploy.yml) or by hand: sh deploy.sh
set -e
cd /var/www/cleartrack

git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

# Snapshot the database right before migrating; keep the 5 newest (uses ~/.my.cnf, see docs/deploy-digitalocean.md)
mkdir -p ~/backups
mysqldump --no-tablespaces cleartrack | gzip > ~/backups/pre-deploy-$(date +%F-%H%M%S).sql.gz
ls -t ~/backups/pre-deploy-*.gz | tail -n +6 | xargs -r rm

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo UPDATE_OK
