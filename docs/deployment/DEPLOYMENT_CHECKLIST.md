# Deployment checklist (staging / production)

Copy **`.env.production.example`** to **`.env`**, fill placeholders, then follow in order.

## 1. Server prerequisites

- PHP version and extensions match project `composer.json`
- Node.js LTS for asset build (CI image or build server)
- MySQL, Redis, process supervisor (systemd or Supervisor)

## 2. Application code

```bash
git fetch origin && git checkout <release-tag-or-branch>
```

## 3. PHP dependencies (production)

```bash
composer install --no-dev --optimize-autoloader
```

## 4. Frontend assets

```bash
npm ci
npm run build
```

Confirm `public/build` (or Vite manifest output) is deployed with the release.

## 5. Environment

- Set `APP_KEY` (e.g. `php artisan key:generate` once per environment)
- Align `APP_URL`, database, Redis, Reverb, mail, and session cookie domain with HTTPS

## 6. Database migrations

```bash
php artisan migrate --force
```

Run first on **staging**; capture migration output.

## 7. Laravel caches (after `.env` is final)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

To clear during debugging:

```bash
php artisan optimize:clear
```

## 8. Storage symlink (only if needed)

Run **only** if the app still serves **legacy public-disk** files via the web root symlink:

```bash
php artisan storage:link
```

Sensitive uploads use **private** storage and must **not** be world-readable via `public/storage`. Prefer authorized routes for evidence and materials.

## 9. Long-running: Laravel Reverb (WebSockets)

Reverb must run as a supervised process, for example:

**Supervisor** (illustrative program block — adjust paths and user):

```ini
[program:quizsnap-reverb]
command=php /var/www/quizsnap/artisan reverb:start
directory=/var/www/quizsnap
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/quizsnap-reverb.log
```

**systemd** (illustrative unit — adjust `WorkingDirectory` and `ExecStart`):

```ini
[Unit]
Description=QUIZSNAP Laravel Reverb
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/quizsnap
ExecStart=/usr/bin/php artisan reverb:start
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

Then: `sudo systemctl daemon-reload && sudo systemctl enable --now quizsnap-reverb.service`

Place **Nginx** (or another reverse proxy) in front for **WSS** on `443`, forwarding to the Reverb listen port per your layout.

## 10. Optional: queue worker

If `QUEUE_CONNECTION` is `database` (or `redis`) **and** the application dispatches queued jobs:

```bash
php artisan queue:work --sleep=3 --tries=3
```

Supervise similarly to Reverb. If there are no queued jobs, the worker is optional but harmless.

## 11. Cron / scheduler

There is **no** `Schedule::` definition in this repository yet. When scheduled tasks are added, install:

```cron
* * * * * cd /var/www/quizsnap && php artisan schedule:run >> /dev/null 2>&1
```

Until then, cron is optional.

## 12. Smoke tests (manual)

- `/up` health check
- Login (admin / coordinator / student)
- Exam start (with OTP if enabled)
- Live exam page loads; WebSocket connects (browser devtools → Network → WS)
- Proctoring evidence route (authorized staff only)
- Course material download (student / examiner)

## 13. Post-deploy

- Rotate build artifacts on old releases
- Confirm log rotation (see [INFRASTRUCTURE_REQUIREMENTS.md](./INFRASTRUCTURE_REQUIREMENTS.md))
- Confirm backups ran (MySQL + `storage/app/private`)
