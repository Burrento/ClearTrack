# Deploying ClearTrack to DigitalOcean

This is how the live pilot server was set up. One small server runs everything: Nginx, PHP 8.3, MySQL and the scheduler. The card reader bridge always runs on the officer's laptop, not on the server.

Live pilot: `https://178.128.57.54.sslip.io` (Droplet IP `178.128.57.54`). For a new server, replace the IP everywhere below. `<ip>.sslip.io` resolves to that IP automatically, so no domain is needed. To move to a real domain later, change `server_name` in Nginx, rerun certbot with the new name, update `APP_URL`, and run `php artisan config:cache`.

## 1. Droplet

DigitalOcean → Create → Droplets:

| Setting | Choose |
|---|---|
| Region | Singapore (sgp1) |
| Image | Ubuntu 24.04 LTS |
| Size | Basic → Regular → **1 GB / 1 CPU** ($6/mo). 512 MB is too small. |
| Authentication | SSH key (`ssh-keygen -t ed25519` on your laptop, paste the `.pub` line) |
| Extras | Monitoring on, paid Backups off (step 7 covers backups) |

Set Billing → Billing Alerts. Billing is hourly; a powered-off Droplet still bills, so **destroy** it if the pilot stops.

Log in from your laptop:

```bash
ssh root@178.128.57.54
```

Everything below runs on the server as root unless it says otherwise.

## 2. Base packages

Add swap, so 1 GB of RAM doesn't run out:

```bash
fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile && echo '/swapfile none swap sw 0 0' >> /etc/fstab
```

Update the system. Press Enter on any config prompt:

```bash
apt update && apt upgrade -y
```

Install Nginx, MySQL, PHP 8.3 and Composer:

```bash
apt install -y nginx mysql-server php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl composer unzip git
```

Turn on the firewall:

```bash
ufw allow OpenSSH && ufw allow 'Nginx Full' && ufw --force enable
```

Install Node 22. Vite 8 needs Node 20.19+, and Ubuntu's own Node is older:

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && apt install -y nodejs
```

## 3. Database and app user

Create the database. The password is generated once and kept in `/root/db-password.txt`:

```bash
[ -f /root/db-password.txt ] || (openssl rand -hex 16 > /root/db-password.txt && chmod 600 /root/db-password.txt); DBPASS=$(cat /root/db-password.txt) && mysql -e "CREATE DATABASE IF NOT EXISTS cleartrack; CREATE USER IF NOT EXISTS 'cleartrack'@'localhost' IDENTIFIED BY '$DBPASS'; ALTER USER 'cleartrack'@'localhost' IDENTIFIED BY '$DBPASS'; GRANT ALL PRIVILEGES ON cleartrack.* TO 'cleartrack'@'localhost';" && echo DB_OK
```

The app runs as user `cleartrack`. PHP-FPM is switched to that user, so the web app, cron and Composer all use the same file owner:

```bash
id cleartrack >/dev/null 2>&1 || adduser --disabled-password --gecos "" cleartrack; mkdir -p /var/www/cleartrack && chown cleartrack:cleartrack /var/www/cleartrack && sed -i 's/^user = www-data/user = cleartrack/; s/^group = www-data/group = cleartrack/' /etc/php/8.3/fpm/pool.d/www.conf && systemctl restart php8.3-fpm
```

## 4. Code and `.env`

Download the code and build it:

```bash
cd /var/www/cleartrack && sudo -u cleartrack -H git clone https://github.com/Burrento/ClearTrack.git . && sudo -u cleartrack -H composer install --no-dev --optimize-autoloader && sudo -u cleartrack -H npm ci && sudo -u cleartrack -H npm run build
```

Create `.env` from the template and fill in production values:

```bash
cd /var/www/cleartrack && cp .env.example .env && sed -i \
 -e 's|^APP_NAME=.*|APP_NAME=ClearTrack|' \
 -e 's|^APP_ENV=.*|APP_ENV=production|' \
 -e 's|^APP_DEBUG=.*|APP_DEBUG=false|' \
 -e 's|^APP_URL=.*|APP_URL=https://178.128.57.54.sslip.io|' \
 -e 's|^LOG_LEVEL=.*|LOG_LEVEL=warning|' \
 -e 's|^DB_CONNECTION=.*|DB_CONNECTION=mysql|' \
 -e 's|^# DB_HOST=.*|DB_HOST=127.0.0.1|' \
 -e 's|^# DB_PORT=.*|DB_PORT=3306|' \
 -e 's|^# DB_DATABASE=.*|DB_DATABASE=cleartrack|' \
 -e 's|^# DB_USERNAME=.*|DB_USERNAME=cleartrack|' \
 -e "s|^# DB_PASSWORD=.*|DB_PASSWORD=$(cat /root/db-password.txt)|" .env && chown cleartrack:cleartrack .env && chmod 640 .env
```

Set up the app key, tables and demo data, then cache the config:

```bash
cd /var/www/cleartrack && sudo -u cleartrack -H php artisan key:generate --force && sudo -u cleartrack -H php artisan migrate --force && sudo -u cleartrack -H php artisan db:seed --force && sudo -u cleartrack -H php artisan config:cache && sudo -u cleartrack -H php artisan route:cache && sudo -u cleartrack -H php artisan view:cache
```

`APP_DEBUG=false` matters: with it on, error pages expose secrets.

## 5. Nginx and HTTPS

Write the site config:

```bash
cat > /etc/nginx/sites-available/cleartrack <<'EOF'
server {
    listen 80;
    server_name 178.128.57.54.sslip.io;
    root /var/www/cleartrack/public;
    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF
ln -sf /etc/nginx/sites-available/cleartrack /etc/nginx/sites-enabled/cleartrack && rm -f /etc/nginx/sites-enabled/default && nginx -t && systemctl reload nginx
```

Get the HTTPS certificate. Use a real email address; Let's Encrypt uses it to warn before expiry:

```bash
apt install -y certbot python3-certbot-nginx && certbot --nginx -d 178.128.57.54.sslip.io -m <your-email> --agree-tos --no-eff-email --redirect
```

Certbot installs a timer that renews the certificate automatically.

Run the Nginx step before certbot. If certbot runs first, it attaches the certificate to Nginx's default page. Fix that by doing this step, then rerunning certbot and choosing "reinstall".

## 6. Lock the demo accounts

The seeded passwords are public in this repo, so lock them immediately:

```bash
cd /var/www/cleartrack && sudo -u cleartrack -H php artisan tinker --execute="App\Models\User::whereIn('username', ['SIT_Officer', 'LGC25-105680'])->update(['is_active' => false]);"
```

Then log in as `superadmin` / `password` and change the password under **Account** right away.

## 7. Scheduler and nightly backups

Absence processing needs `schedule:run` every minute. There are no queued jobs, so no queue worker is needed:

```bash
(crontab -u cleartrack -l 2>/dev/null | grep -v schedule:run; echo "* * * * * cd /var/www/cleartrack && php artisan schedule:run >> /dev/null 2>&1") | crontab -u cleartrack -
```

Nightly database dump at 2 AM, keeping the last 14:

```bash
printf "[client]\nuser=cleartrack\npassword=%s\n" "$(cat /root/db-password.txt)" > /home/cleartrack/.my.cnf && chown cleartrack:cleartrack /home/cleartrack/.my.cnf && chmod 600 /home/cleartrack/.my.cnf && sudo -u cleartrack mkdir -p /home/cleartrack/backups && (crontab -u cleartrack -l | grep -v mysqldump; echo '0 2 * * * mysqldump --no-tablespaces cleartrack | gzip > /home/cleartrack/backups/cleartrack-$(date +\%F).sql.gz && ls -t /home/cleartrack/backups/*.gz | tail -n +15 | xargs -r rm') | crontab -u cleartrack -
```

Copy backups to your laptop now and then. A backup that only lives on the server is lost with the server:

```bash
scp "root@178.128.57.54:/home/cleartrack/backups/*.gz" .
```

## 8. Card reader (officer's laptop)

```bash
python tools/acr122u_bridge.py https://178.128.57.54.sslip.io
```

The address must match the browser's address bar exactly: `https`, no trailing slash. Allow the browser's "local network" or "localhost" prompt. Brave may block localhost silently; allow it in Shields, or use Chrome.

## 9. Updating the live site

Push to `main` on GitHub, then on the server:

```bash
cd /var/www/cleartrack && sudo -u cleartrack -H git pull && sudo -u cleartrack -H composer install --no-dev --optimize-autoloader && sudo -u cleartrack -H npm ci && sudo -u cleartrack -H npm run build && sudo -u cleartrack -H php artisan migrate --force && sudo -u cleartrack -H php artisan config:cache && sudo -u cleartrack -H php artisan route:cache && sudo -u cleartrack -H php artisan view:cache
```

Don't update during an event.

Or run the same steps from `deploy.sh`:

```bash
sudo -u cleartrack -H sh /var/www/cleartrack/deploy.sh
```

## 10. Automatic updates (GitHub Actions)

`.github/workflows/deploy.yml` runs the tests on every push to `main`. Only if they pass does it SSH in and run `deploy.sh`. A failing test means the live site isn't touched. One-time setup:

**On the server (as root).** Create a key that may only run the deploy. The `command=` prefix means that even if the key leaked, it couldn't open a shell:

```bash
mkdir -p /home/cleartrack/.ssh && ssh-keygen -t ed25519 -f /root/github_deploy -N "" -C github-actions && echo "command=\"cd /var/www/cleartrack && git pull --ff-only && sh deploy.sh\",no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty $(cat /root/github_deploy.pub)" >> /home/cleartrack/.ssh/authorized_keys && chown -R cleartrack:cleartrack /home/cleartrack/.ssh && chmod 700 /home/cleartrack/.ssh && chmod 600 /home/cleartrack/.ssh/authorized_keys && echo KEY_OK
```

Print the two values GitHub needs:

```bash
echo "=== DEPLOY_SSH_KEY ==="; cat /root/github_deploy; echo "=== DEPLOY_KNOWN_HOSTS ==="; ssh-keyscan -t ed25519 178.128.57.54 2>/dev/null
```

**On GitHub:** repo → Settings → Secrets and variables → Actions → **New repository secret**, twice:

| Name | Value |
|---|---|
| `DEPLOY_SSH_KEY` | everything from `-----BEGIN OPENSSH PRIVATE KEY-----` to `-----END OPENSSH PRIVATE KEY-----`, both lines included |
| `DEPLOY_KNOWN_HOSTS` | the line starting `178.128.57.54 ssh-ed25519` |

Then delete the private key from the server, since GitHub holds the only copy now:

```bash
rm /root/github_deploy
```

Watch each run under the repo's **Actions** tab: a green tick means it's deployed, a red cross means tests failed or the deploy did. A push still goes live within ~3 minutes, so **don't push to `main` during an event.**

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| "Welcome to nginx!" | The default site is still enabled: rerun step 5's Nginx part |
| "File not found." | Code missing in `/var/www/cleartrack`, or PHP-FPM not running as `cleartrack`: rerun steps 3–4 |
| 500 error | `tail -20 /var/www/cleartrack/storage/logs/laravel.log` |
| Page has no styles | `npm run build` didn't finish; rerun it as `cleartrack` |
| Absences never processed | `crontab -u cleartrack -l` should list `schedule:run` |
