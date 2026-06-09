#!/usr/bin/env bash
# QUIZSNAP — server health check (cPanel / shared hosting).
# Run from the project root: bash bin/check-server.sh
#
# Exits 0 if everything looks deploy-ready, 1 otherwise. Prints a
# checklist with exact remediation commands for any failed item.

set -u

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[0;33m'
DIM='\033[2m'
NC='\033[0m'

ok()   { printf "  ${GREEN}✓${NC} %s\n" "$1"; }
fail() { printf "  ${RED}✗${NC} %s\n" "$1"; FAILED=1; }
warn() { printf "  ${YELLOW}!${NC} %s\n" "$1"; }
info() { printf "  ${DIM}·${NC} %s\n" "$1"; }
hint() { printf "      ${DIM}fix:${NC} %s\n" "$1"; }

FAILED=0

printf "\n%s\n" "==[ QUIZSNAP server health check ]=="
printf "%s\n\n" "Project root: $PROJECT_ROOT"

# 1. PHP version
printf "%s\n" "[1] PHP runtime"
PHP_BIN="$(command -v php || true)"
if [[ -z "$PHP_BIN" ]]; then
    fail "php not found on PATH"
    hint "On cPanel, prepend the right PHP to PATH, e.g.: export PATH=/opt/cpanel/ea-php83/root/usr/bin:\$PATH"
else
    PHP_VERSION="$(php -r 'echo PHP_VERSION;')"
    PHP_MAJOR_MINOR="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
    if php -r 'exit(version_compare(PHP_VERSION, "8.3.0", "<") ? 1 : 0);'; then
        ok "PHP $PHP_VERSION at $PHP_BIN"
    else
        fail "PHP $PHP_VERSION is too old (need 8.3+)"
        hint "cPanel → MultiPHP Manager → set fada.neckpressing.com to PHP 8.3 (or newer)"
    fi
fi

# 2. Required PHP extensions
printf "\n%s\n" "[2] PHP extensions"
NEEDED_EXTS=(pdo_mysql mbstring openssl tokenizer xml ctype json fileinfo bcmath gd curl zip)
MISSING_EXTS=()
for ext in "${NEEDED_EXTS[@]}"; do
    if php -r "exit(extension_loaded('$ext') ? 0 : 1);"; then
        ok "$ext"
    else
        fail "$ext is missing"
        MISSING_EXTS+=("$ext")
    fi
done
if [[ ${#MISSING_EXTS[@]} -gt 0 ]]; then
    hint "cPanel → Select PHP Version → tick: ${MISSING_EXTS[*]}"
fi

# 3. .env file + APP_KEY
printf "\n%s\n" "[3] .env"
if [[ ! -f .env ]]; then
    fail ".env is missing"
    hint "cp .env.production.example .env  &&  php artisan key:generate"
else
    ok ".env exists"
    APP_KEY_VAL="$(grep -E '^APP_KEY=' .env | head -1 | cut -d= -f2- || true)"
    if [[ -z "$APP_KEY_VAL" || "$APP_KEY_VAL" == "base64:" ]]; then
        fail "APP_KEY is empty"
        hint "php artisan key:generate"
    else
        ok "APP_KEY is set"
    fi
    APP_ENV_VAL="$(grep -E '^APP_ENV=' .env | head -1 | cut -d= -f2- || true)"
    APP_DEBUG_VAL="$(grep -E '^APP_DEBUG=' .env | head -1 | cut -d= -f2- || true)"
    info "APP_ENV=${APP_ENV_VAL:-?}  APP_DEBUG=${APP_DEBUG_VAL:-?}"
    if [[ "$APP_ENV_VAL" == "production" && "$APP_DEBUG_VAL" == "true" ]]; then
        warn "APP_DEBUG=true in production — set APP_DEBUG=false once the site is up"
    fi

    # No Redis-shaped values in .env on this host
    if grep -qE '^(CACHE_STORE|SESSION_DRIVER|QUEUE_CONNECTION|BROADCAST_CONNECTION)=redis' .env; then
        fail "A Redis driver is still set in .env"
        hint "sed -i 's/^CACHE_STORE=redis/CACHE_STORE=file/' .env"
        hint "sed -i 's/^SESSION_DRIVER=redis/SESSION_DRIVER=file/' .env"
        hint "sed -i 's/^QUEUE_CONNECTION=redis/QUEUE_CONNECTION=database/' .env"
        hint "sed -i 's/^BROADCAST_CONNECTION=redis/BROADCAST_CONNECTION=null/' .env"
    else
        ok "No Redis drivers in .env"
    fi
fi

# 4. vendor/ present (composer install ran)
printf "\n%s\n" "[4] composer / vendor"
if [[ -f vendor/autoload.php ]]; then
    ok "vendor/autoload.php is present"
else
    fail "vendor/ is missing — composer install never ran on this host"
    hint "composer install --no-dev --optimize-autoloader  (or: php composer.phar install --no-dev --optimize-autoloader)"
fi

# 5. storage/ + bootstrap/cache writable
printf "\n%s\n" "[5] writable runtime directories"
WRITABLE_DIRS=(storage storage/framework storage/framework/sessions storage/framework/views storage/framework/cache storage/framework/cache/data storage/logs bootstrap/cache)
for d in "${WRITABLE_DIRS[@]}"; do
    if [[ ! -d "$d" ]]; then
        fail "$d is missing"
        hint "mkdir -p $d  &&  chmod 755 $d"
    elif [[ ! -w "$d" ]]; then
        fail "$d exists but is NOT writable by the current user"
        hint "chmod -R u+rwX,go+rX $d"
    else
        ok "$d writable"
    fi
done

# 6. Public docroot sanity
printf "\n%s\n" "[6] public/ docroot"
if [[ -f public/index.php && -f public/.htaccess ]]; then
    ok "public/index.php and public/.htaccess present"
    info "cPanel docroot for fada.neckpressing.com MUST point to: $PROJECT_ROOT/public"
else
    fail "public/index.php or public/.htaccess missing"
fi

# 7. DB connectivity
printf "\n%s\n" "[7] database"
if [[ -f .env && -f vendor/autoload.php ]]; then
    if php artisan migrate:status >/dev/null 2>&1; then
        ok "database connection works (migrate:status succeeded)"
    else
        fail "database connection failed (php artisan migrate:status)"
        hint "Verify DB_DATABASE / DB_USERNAME / DB_PASSWORD in .env match the cPanel DB"
        hint "Try: mysql -u\"\$DB_USERNAME\" -p\"\$DB_PASSWORD\" \"\$DB_DATABASE\" -e 'SELECT 1'"
    fi
else
    info "skipped — need .env + vendor/ first"
fi

# 8. Vite production build
printf "\n%s\n" "[8] front-end build"
if [[ -f public/build/manifest.json ]]; then
    ok "public/build/manifest.json present"
else
    fail "public/build/manifest.json missing — vite build never ran (or wasn't uploaded)"
    hint "Locally: npm run build  →  upload public/build/ to the server"
fi

# 9. Last error log lines
printf "\n%s\n" "[9] last laravel.log entries"
LATEST_LOG="$(ls -1t storage/logs/laravel*.log 2>/dev/null | head -n 1 || true)"
if [[ -n "$LATEST_LOG" && -s "$LATEST_LOG" ]]; then
    info "tail -n 25 $LATEST_LOG:"
    tail -n 25 "$LATEST_LOG" | sed 's/^/      /'
else
    info "no laravel*.log entries yet"
fi

# Summary
printf "\n"
if [[ $FAILED -eq 0 ]]; then
    printf "${GREEN}All checks passed.${NC}\n"
    printf "Next: visit https://fada.neckpressing.com — if it still 500s, run: tail -f storage/logs/laravel-*.log\n"
    exit 0
else
    printf "${RED}One or more checks failed — fix the items above and re-run: bash bin/check-server.sh${NC}\n"
    exit 1
fi
