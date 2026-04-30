#!/bin/bash
# ============================================================================
# Hostinger zero-downtime deploy script
#
# Lives at:  ~/exam_management/deploy.sh on the Hostinger server
# Triggered by:
#   1. SSH from GitHub Actions on push to `main`, OR
#   2. Manual SSH:  ssh user@host 'cd ~/exam_management && ./deploy.sh'
#
# Assumptions:
#   - Project lives at $APP_DIR (default ~/exam_management)
#   - PHP 8.3 available as `php` (Hostinger's default since 2024)
#   - Composer available as `composer`
#   - Node 20+ available for `npm` (use Hostinger's "Node.js Selector" panel)
#   - .env exists on server (NOT committed) — manage it via Hostinger File Manager
#   - public_html is a symlink to $APP_DIR/public  (one-time setup, see README)
#
# Safety:
#   - Aborts if any step fails (`set -euo pipefail`)
#   - Puts app into maintenance mode during the deploy
#   - Always brings the app back up, even on partial failure (trap on EXIT)
# ============================================================================

set -euo pipefail

# --- Config (override by exporting before running) ---
APP_DIR="${APP_DIR:-$HOME/exam_management}"
BRANCH="${BRANCH:-main}"
PHP="${PHP:-php}"
COMPOSER="${COMPOSER:-composer}"
NODE_BIN="${NODE_BIN:-npm}"
LOG_FILE="${APP_DIR}/storage/logs/deploy.log"

# --- Helpers ---
log() {
    local msg="[$(date '+%Y-%m-%d %H:%M:%S')] $*"
    echo "$msg"
    mkdir -p "$(dirname "$LOG_FILE")"
    echo "$msg" >> "$LOG_FILE"
}
fail() {
    log "❌ DEPLOY FAILED: $*"
    exit 1
}

# Always restore from maintenance mode, even if something blows up
cleanup() {
    if [ -f "$APP_DIR/storage/framework/down" ]; then
        log "Bringing app back up (cleanup)..."
        cd "$APP_DIR" && $PHP artisan up || true
    fi
}
trap cleanup EXIT

cd "$APP_DIR" || fail "Project directory $APP_DIR not found"

log "════════════════════════════════════════════════"
log "🚀 Deploy starting from $(pwd)"
log "════════════════════════════════════════════════"

# --- 1. Maintenance mode (graceful) ---
log "→ Entering maintenance mode"
$PHP artisan down --retry=15 --refresh=15 || log "  (already down or failed silently)"

# --- 2. Pull latest code ---
log "→ git fetch & reset to origin/$BRANCH"
git fetch --all --prune || fail "git fetch failed"
git reset --hard "origin/$BRANCH" || fail "git reset failed"
git clean -fd -- 'storage/app/temp/*' 2>/dev/null || true

# --- 3. PHP dependencies (production-only, no dev) ---
log "→ composer install (production)"
$COMPOSER install --no-dev --prefer-dist --optimize-autoloader --no-interaction || fail "composer install failed"

# --- 4. Database migrations ---
log "→ artisan migrate --force"
$PHP artisan migrate --force || fail "migrations failed"

# --- 5. Frontend build ---
if [ -f "package.json" ]; then
    log "→ npm ci (production deps)"
    $NODE_BIN ci --no-audit --no-fund || fail "npm ci failed"

    log "→ npm run build (Vite)"
    $NODE_BIN run build || fail "Vite build failed"
fi

# --- 6. Storage symlink (idempotent) ---
if [ ! -L "public/storage" ]; then
    log "→ Creating storage symlink"
    $PHP artisan storage:link
fi

# Remove the stale Vite hot-reload marker if it's lying around (stops us pointing at localhost:5173 in production)
rm -f public/hot

# --- 7. Permissions ---
log "→ Fixing storage + cache permissions"
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# --- 8. Cache the world (production performance) ---
log "→ Clearing old caches"
$PHP artisan optimize:clear

log "→ Caching config / routes / views / events"
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache

# --- 9. Queue & schedule (graceful restart of any horizon/queue workers) ---
log "→ Restarting queue workers (if any)"
$PHP artisan queue:restart || true

# --- 10. Clear app-level caches that may have stale data ---
$PHP artisan cache:clear || true

# --- 11. Health check ---
log "→ Health check (artisan about | head)"
$PHP artisan about --only=environment 2>&1 | head -10 || true

# --- 12. Bring it back up ---
log "→ Exiting maintenance mode"
$PHP artisan up

log "✅ Deploy succeeded · commit $(git rev-parse --short HEAD)"
log "════════════════════════════════════════════════"
