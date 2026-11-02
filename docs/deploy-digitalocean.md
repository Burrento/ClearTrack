# Deploying ClearTrack to DigitalOcean

One small server runs everything: the app, MySQL and the scheduler. The card reader bridge always runs on the officer's laptop, not on the server.

Values in `<angle brackets>` are yours to fill in. Example: `<domain>` = `cleartrack.example.com`.

## 1. Before you start

1. **GitHub Student Developer Pack** (education.github.com/pack): verify with your school email or student ID, then claim the DigitalOcean credit from the pack's offers page.
2. **Domain name.** The pack has included a free domain (Namecheap `.me` or `.TECH`). No domain? You can use `<server-ip>.sslip.io` instead (e.g. `159.89.1.2.sslip.io`). It points to your server automatically and still gets HTTPS.
3. **Push the code to GitHub.** The server pulls from `https://github.com/Burrento/ClearTrack`. If the repo is private, you'll need a deploy key (step 4).

## 2. Create the server (Droplet)

DigitalOcean → **Create → Droplets**:

| Setting | Choose |
|---|---|
| Region | **Singapore** (closest to the Philippines) |
| Image | **Ubuntu 24.04 LTS** |
| Size | Basic → Regular → **1 GB RAM / 1 CPU** ($6/mo) |
| Authentication | **SSH key** (safer than a password) |
| Extras | Tick **Monitoring**. Leave paid Backups off; step 9 covers backups for free. |

Note the server's IP address.

**Cost:** billing is hourly, so $5 credit runs the 1 GB server for about 3½ weeks. Create it about 2 weeks before the event. Afterwards, **destroy** it if it isn't continuing; a powered-off Droplet still bills. Set Billing → **Billing Alerts** at $5.

**Firewall** (Networking → Firewalls → create, apply to the Droplet). Allow inbound:
- 22 (SSH)
- 80 and 443 (website)
- 8443 (CloudPanel admin): set its source to **your own IP only**

**Domain:** at your domain registrar, add an `A` record pointing `<domain>` to the server IP. Skip this if you use sslip.io.

## 3. Install CloudPanel

CloudPanel is a free control panel. It sets up Nginx, PHP, MySQL and HTTPS for you.

1. Connect from your computer:
   ```bash
   ssh root@<server-ip>
   ```
2. Add 2 GB of swap, so 1 GB of RAM doesn't run out:
   ```bash
   fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile && echo '/swapfile none swap sw 0 0' >> /etc/fstab
   ```
3. Copy the install command from **cloudpanel.io/docs → Getting Started → DigitalOcean**. Choose **Ubuntu 24.04** and **MySQL 8**, run it, and wait about 5 minutes.
4. Open `https://<server-ip>:8443`. Click past the certificate warning and create the CloudPanel admin user.

## 4. Create the site and database

In CloudPanel:

1. **Add Site → Create a PHP Site**
   - Application: **Laravel**
   - Domain: `<domain>`
   - PHP version: **8.4**
   - Site user: e.g. `cleartrack`
   - Note the site user password.
2. **Site → SSL/TLS → Actions → New Let's Encrypt Certificate.** This gives the site HTTPS.
3. **Databases → Add Database:** name `cleartrack`, plus a user and password. Note them.

Connect as the site user. You'll do everything from here on as this user, not root:

```bash
ssh cleartrack@<server-ip>
```

**Private repo only:** create a deploy key and add it in GitHub → repo → Settings → Deploy keys (read-only):

```bash
ssh-keygen -t ed25519 -C cleartrack-server
```

```bash
cat ~/.ssh/id_ed25519.pub
```

Then use `git@github.com:Burrento/ClearTrack.git` in the clone step below.

## 5. Get the code onto the server

```bash
cd ~/htdocs
```

```bash
rm -rf <domain> && git clone https://github.com/Burrento/ClearTrack.git <domain>
```

```bash
cd <domain>
```

```bash
composer install --no-dev --optimize-autoloader
```

**Build the CSS/JS.** Node isn't installed for the site user, so install it once with nvm:

```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
```

```bash
source ~/.bashrc && nvm install --lts
```

```bash
npm ci && npm run build
```

## 6. Configure `.env`

```bash
cp .env.example .env && nano .env
```

Change these lines and leave the rest:

```ini
APP_NAME=ClearTrack
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<domain>

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cleartrack
DB_USERNAME=<db-user>
DB_PASSWORD=<db-password>
```

`APP_DEBUG=false` matters: with it on, error pages show your database password to anyone.

Then set up the database:

```bash
php artisan key:generate
```

```bash
php artisan migrate --force
```

```bash
php artisan db:seed --force
```

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Open `https://<domain>`. The login page should appear.

## 7. Lock down the demo accounts

The seeder creates demo logins with known passwords, so fix them right away:

1. Log in as `superadmin` / `password`, then go to **Account → change password**.
2. Deactivate the demo officer and the demo student:
   ```bash
   php artisan tinker --execute="App\Models\User::whereIn('username', ['SIT_Officer', 'LGC25-105680'])->update(['is_active' => false]);"
   ```
3. As admin, add the real department and its officers, and start the semester. Officers then register their students.

## 8. Scheduler (automatic absence processing)

CloudPanel → **Site → Cron Jobs → Add Cron Job**:

- Schedule: every minute (`* * * * *`)
- Command:
  ```bash
  /usr/bin/php8.4 /home/cleartrack/htdocs/<domain>/artisan schedule:run >> /dev/null 2>&1
  ```

The app has no queued jobs, so you don't need a queue worker.

## 9. Nightly database backup

Losing attendance and fine records is the one thing that can't be undone.

As the site user, store the database login once:

```bash
printf "[client]\nuser=<db-user>\npassword=<db-password>\n" > ~/.my.cnf && chmod 600 ~/.my.cnf && mkdir -p ~/backups
```

Then add a second CloudPanel cron job, daily at 2 AM (`0 2 * * *`). It keeps the last 14 backups:

```bash
mysqldump --no-tablespaces cleartrack | gzip > ~/backups/cleartrack-$(date +\%F).sql.gz && ls -t ~/backups/*.gz | tail -n +15 | xargs -r rm
```

Every week or so, download a copy to your own computer (any SFTP app works: WinSCP, FileZilla). A backup only on the server is lost with the server.

## 10. Card reader on the officer's laptop

On each scanning laptop (Python and `pip install pyscard` once):

```bash
python tools/acr122u_bridge.py https://<domain>
```

The address must match the browser's address bar exactly: `https`, no trailing slash, no `www` unless you use it.

The first time, Chrome may ask to let the site reach **devices on your local network**. Click **Allow**. The scanner page should then show a green **Card reader connected**.

## 11. Updating the site later

After pushing changes to GitHub:

```bash
cd ~/htdocs/<domain> && git pull && composer install --no-dev --optimize-autoloader && npm ci && npm run build && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Don't update during an event.

## 12. Dry run before Nurses Week

Do this a few days ahead, on the real server, with real phones:

- [ ] Admin: semester started, department and officers created
- [ ] Officer: register 3–5 real students with real cards
- [ ] Create a short test event that is live now
- [ ] Open the scanner on the laptop and tap each card. A green name should appear.
- [ ] Students log in on their phones over mobile data, not school Wi-Fi
- [ ] Let the event end and wait 5 minutes. Untapped students should show **Absent** (this proves the scheduler works).
- [ ] Next morning, check `~/backups` has a file (this proves backups work)
- [ ] Delete or ignore the test event, and keep a paper sign-in sheet at each real event as a fallback
