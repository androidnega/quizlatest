# QUIZSNAP — cPanel deployment runbook

This is the short, copy/paste path to fix a 500 on
`fada.neckpressing.com` after pulling new code on the cPanel server.

## TL;DR

```bash
ssh neckpre1@185.150.191.249       # or use PuTTY
cd ~/fada
git pull
bash bin/deploy.sh                 # idempotent — safe to re-run
```

If `bin/deploy.sh` finishes with `Deploy complete.` but the site still
500s, run `bash bin/check-server.sh` and paste the failed lines.

---

## What `bin/deploy.sh` does

1. Verifies **PHP 8.3+** and the required extensions
2. Creates `.env` from `.env.production.example` if it's missing
3. Strips any leftover `redis` driver values from `.env`
4. Runs `composer install --no-dev --optimize-autoloader`
5. Generates `APP_KEY` if blank
6. Creates `storage/` and `bootstrap/cache/` with the right perms
7. Clears stale `config:cache`, `view:cache`, `route:cache`
8. Runs `php artisan migrate --force`
9. Rebuilds the production caches
10. Runs `bin/check-server.sh`

The script is idempotent: re-running it is always safe.

---

## First-time setup on a brand-new cPanel host

Once-only steps you do **before** running `bin/deploy.sh`:

### 1. Set the PHP version

In cPanel → **MultiPHP Manager**, set the domain
`fada.neckpressing.com` to **PHP 8.3** (or newer).

In cPanel → **Select PHP Version**, enable these extensions:

```
pdo_mysql  mbstring  openssl  tokenizer  xml  ctype  json
fileinfo   bcmath    gd       curl       zip
```

### 2. Point the document root at `public/`

In cPanel → **Domains** → edit `fada.neckpressing.com`, set the
*Document Root* to:

```
/home/neckpre1/fada/public
```

### 3. Create the production `.env`

The `bin/deploy.sh` script will copy `.env.production.example` to
`.env` if `.env` is absent. Edit it with the real values:

```bash
cd ~/fada
nano .env
```

You **must** set at minimum:

```
APP_URL=https://fada.neckpressing.com
APP_DEBUG=false           # only true while debugging the very first 500
APP_ENV=production

DB_HOST=localhost
DB_DATABASE=neckpre1_quizsnap        # whatever you named it in cPanel
DB_USERNAME=neckpre1_quizsnap
DB_PASSWORD=<the-cpanel-db-password>
```

Then:

```bash
php artisan key:generate
```

### 4. Make sure the front-end build is on the server

Vite output is in `public/build/` and is **gitignored** — it never
lands on the server via `git pull`. Build it locally then SCP/upload:

```bash
# Locally
npm install
npm run build

# Upload public/build/ to ~/fada/public/build/ via cPanel File Manager,
# SFTP, or:
rsync -avz public/build/ neckpre1@185.150.191.249:~/fada/public/build/
```

### 5. Run the deploy script

```bash
ssh neckpre1@185.150.191.249
cd ~/fada
bash bin/deploy.sh
```

---

## Diagnosing a 500 fast

```bash
cd ~/fada
tail -n 80 storage/logs/laravel-*.log     # Laravel side
tail -n 80 ~/logs/fada.neckpressing.com.error.log 2>/dev/null  # Apache side
```

Common 500 causes (in order of frequency on cPanel):

| Symptom | Cause | Fix |
| --- | --- | --- |
| White page, no log lines | Wrong PHP version | cPanel → MultiPHP Manager → PHP 8.3 |
| `Class "App\Services\RedisHealthService" not found` | Stale `bootstrap/cache/config.php` from before Redis was removed | `php artisan config:clear` |
| `Permission denied: storage/logs/laravel-*.log` | Wrong perms | `chmod -R u+rwX,go+rX storage bootstrap/cache` |
| `Vite manifest not found` | `public/build/` not uploaded | `npm run build` locally + rsync `public/build/` |
| `SQLSTATE[HY000] [1045]` | DB creds wrong | Re-check `DB_*` in `.env` |
| `No application encryption key` | `APP_KEY` empty | `php artisan key:generate` |
| 404 on every route except `/` | Doc root not pointing at `public/` | cPanel → Domains → set doc root to `~/fada/public` |

If you see anything else, paste the last 30 lines of the Laravel log
back to the assistant and we'll triage it.

---

## Rolling back

If a deploy goes sideways:

```bash
cd ~/fada
git log --oneline -10            # find the last good commit
git checkout <good-sha>
bash bin/deploy.sh
```

Backups of the database live under `storage/app/private/backups/`
(see `app/Console/Commands/QsBackupCommand.php`).
