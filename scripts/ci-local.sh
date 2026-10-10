#!/usr/bin/env bash
set -Eeuo pipefail

# Reproduce the GitHub Actions validation locally, without touching .env or the
# application's normal SQLite database. Run from any directory.
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

INSTALL=0
if [[ "${1:-}" == "--install" ]]; then
    INSTALL=1
elif [[ -n "${1:-}" ]]; then
    echo "Usage: $0 [--install]" >&2
    exit 2
fi

for tool in php composer npm; do
    command -v "$tool" >/dev/null 2>&1 || { echo "Required tool missing: $tool" >&2; exit 2; }
done
[[ -f .env.example ]] || { echo ".env.example not found" >&2; exit 2; }
[[ -f composer.lock && -f package-lock.json ]] || { echo "Lock file missing" >&2; exit 2; }

if [[ "$INSTALL" == 1 ]]; then
    echo "== Install locked PHP dependencies =="
    composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader
    echo "== Install locked Node dependencies =="
    npm ci
else
    [[ -f vendor/autoload.php ]] || { echo "vendor dependencies missing; run: $0 --install" >&2; exit 2; }
    [[ -x node_modules/.bin/vite ]] || { echo "Node dependencies missing; run: $0 --install" >&2; exit 2; }
fi

CI_ENV_FILE="$ROOT/.env.ci-local"
if [[ -e "$CI_ENV_FILE" ]]; then
    echo "Refusing to overwrite existing $CI_ENV_FILE; move it aside and retry." >&2
    exit 2
fi
CI_TMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/isp-billing-ci.XXXXXX")"
cleanup() {
    rm -f "$CI_ENV_FILE"
    rm -rf "$CI_TMP_DIR"
}
trap cleanup EXIT INT TERM

cp .env.example "$CI_ENV_FILE"
DB_FILE="$CI_TMP_DIR/database.sqlite"
: > "$DB_FILE"
export APP_ENV=testing
export DB_CONNECTION=sqlite
export DB_DATABASE="$DB_FILE"
export CACHE_STORE=array
export QUEUE_CONNECTION=sync
export SESSION_DRIVER=array
export MAIL_MAILER=array
export TELESCOPE_ENABLED=false

run() {
    printf '\n== %s ==\n' "$1"
    shift
    "$@"
}

run "Composer manifest validation" composer validate
run "Composer platform requirements" composer check-platform-reqs
run "Composer locked dependency audit" composer audit --locked
run "npm dependency audit" npm audit --audit-level=low
run "Production asset build" npm run build
run "Generate isolated test key" php artisan key:generate --env=ci-local --force --no-interaction
run "Database migrations (temporary SQLite only)" php artisan migrate --env=ci-local --force
printf '\n== Full Laravel test suite (separate in-memory database) ==\n'
DB_DATABASE=:memory: php artisan test --env=ci-local
run "Blade template cache validation" php artisan view:cache --env=ci-local
run "Clear generated Blade cache" php artisan view:clear --env=ci-local
run "Whitespace/conflict check" git diff --check

printf '\nLOCAL_CI_RESULT=PASS\n'
echo "No live environment file or application database was used; temporary test files are removed on exit."
