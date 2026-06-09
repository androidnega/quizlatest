#!/usr/bin/env bash
# QUIZSNAP — post-pull deploy script for cPanel / shared hosting.
# Run from the project root after `git pull`:
#
#     bash bin/deploy.sh
#
# What it does (idempotent — safe to re-run):
#   1. Verifies PHP >= 8.3 and the required extensions
#   2. Ensures .env exists (copies from .env.production.example if not)
#   3. Generates APP_KEY when blank
#   4. Strips any leftover Redis drivers from .env
#   5. Runs composer install (no-dev, optimised autoloader)
#   6. Creates the runtime directories with the right permissions
#   7. Clears stale framework caches + view caches
#   8. Runs `php artisan migrate --force`
#   9. Rebuilds the production caches (config, route, view)
#  10. Prints the final health snapshot

set -eu

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[0;33m'
DIM='\033[2m'
BOLD='\033[1m'
NC='\033[0m'

step()   { printf "\n${BOLD}==>${NC} %s\n" "$1"; }
ok()     { printf "  ${GREEN}✓${NC} %s\n" "$1"; }
fail()   { printf "  ${RED}✗${NC} %s\n" "$1" >&2; exit 1; }
warn()   { printf "  ${YELLOW}!${NC} %s\n" "$1"; }
info()   { printf "  ${DIM}·${NC} %s\n" "$1"; }

# --- 1. PHP runtime check -----------------------------------------------------
step "1. Checking PHP runtime"
if ! command -v php >/dev/null 2>&1; then
    fail "php not found on PATH. Add the right cPanel PHP, e.g.: export PATH=/opt/cpanel/ea-php83/root/usr/bin:\$PATH"
fi
PHP_VERSION="$(php -r 'echo PHP_VERSION;')"
if php -r 'exit(version_compare(PHP_VERSION, "8.3.0", "<") ? 1 : 0);'; then
    ok "PHP $PHP_VERSION"
else
    fail "PHP $PHP_VERSION is too old. Laravel 13 needs PHP 8.3+. Switch via cPanel → MultiPHP Manager → PHP 8.3."
fi

NEEDED_EXTS=(pdo_mysql mbstring openssl tokenizer xml ctype json fileinfo bcmath gd curl zip)
MISSING=()
for ext in "${NEEDED_EXTS[@]}"; do
    if ! php -r "exit(extension_loaded('$ext') ? 0 : 1);"; then
        MISSING+=("$ext")
    fi
done
if [[ ${#MISSING[@]} -gt 0 ]]; then
    fail "Missing PHP extensions: ${MISSING[*]} — enable them in cPanel → Select PHP Version."
fi
ok "All required PHP extensions present"

# --- 2. .env --------------------------------------------------------------------
step "2. Ensuring .env exists"
if [[ ! -f .env ]]; then
    if [[ -f .env.production.example ]]; then
        cp .env.production.example .env
        warn "Created .env from .env.production.example — edit DB_*, APP_URL, mail, AI keys."
    else
        fail "No .env and no .env.production.example to bootstrap from."
    fi
else
    ok ".env present"
fi

# --- 3. Strip stale Redis drivers ----------------------------------------------
step "3. Stripping leftover Redis drivers from .env"
TMP=".env.tmp.$$"
sed -E \
    -e 's/^CACHE_STORE=redis/CACHE_STORE=file/' \
    -e 's/^SESSION_DRIVER=redis/SESSION_DRIVER=file/' \
    -e 's/^QUEUE_CONNECTION=redis/QUEUE_CONNECTION=database/' \
    -e 's/^BROADCAST_CONNECTION=redis/BROADCAST_CONNECTION=null/' \
    .env > "$TMP" && mv "$TMP" .env
# Drop any REDIS_* env lines — they are no-ops now and just confuse cPanel admins.
grep -vE '^(REDIS_|REVERB_SCALING_)' .env > "$TMP" && mv "$TMP" .env
ok "Cache/session/queue/broadcast drivers normalised"

# --- 4. Composer ---------------------------------------------------------------
step "4. Installing composer dependencies"
COMPOSER_BIN=""
if command -v composer >/dev/null 2>&1; then
    COMPOSER_BIN="composer"
elif [[ -f /opt/cpanel/composer/bin/composer ]]; then
    COMPOSER_BIN="/opt/cpanel/composer/bin/composer"
elif [[ -f composer.phar ]]; then
    COMPOSER_BIN="php composer.phar"
else
    info "Downloading composer.phar..."
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php
    rm -f composer-setup.php
    COMPOSER_BIN="php composer.phar"
fi
info "Using: $COMPOSER_BIN"
$COMPOSER_BIN install --no-dev --no-interaction --prefer-dist --optimize-autoloader
ok "vendor/ installed"

# --- 5. APP_KEY ----------------------------------------------------------------
step "5. Ensuring APP_KEY is set"
if grep -qE '^APP_KEY=base64:.{40,}' .env; then
    ok "APP_KEY already set"
else
    php artisan key:generate --force
    ok "APP_KEY generated"
fi

# --- 6. Runtime directories + permissions --------------------------------------
step "6. Creating runtime directories"
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    bootstrap/cache
chmod -R u+rwX,go+rX storage bootstrap/cache
ok "storage/ + bootstrap/cache writable"

# --- 7. Clear stale caches -----------------------------------------------------
step "7. Clearing stale caches"
php artisan config:clear || true
php artisan cache:clear  || true
php artisan view:clear   || true
php artisan route:clear  || true
ok "caches cleared"

# --- 8. Migrations -------------------------------------------------------------
step "8. Running migrations"
if php artisan migrate --force; then
    ok "migrations applied"
else
    warn "migrations failed — verify DB_* in .env, then re-run: php artisan migrate --force"
fi

# --- 9. Rebuild production caches ----------------------------------------------
step "9. Rebuilding production caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache
ok "config + route + view caches rebuilt"

# --- 10. Final health snapshot --------------------------------------------------
step "10. Health snapshot"
if [[ -x bin/check-server.sh ]]; then
    bash bin/check-server.sh || true
else
    info "(skipped — bin/check-server.sh not executable; run: chmod +x bin/check-server.sh)"
fi

printf "\n${GREEN}${BOLD}Deploy complete.${NC} Visit https://fada.neckpressing.com — if it still 500s, run:\n"
printf "  ${DIM}tail -f storage/logs/laravel-*.log${NC}\n\n"
