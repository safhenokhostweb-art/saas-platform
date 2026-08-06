#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# saas-platform deployment script (Linux)
# Idempotent, fail-fast, with timestamped rollback tag.
# Compatible with Laravel 10/11/12.
# Never resets, deletes data, or revokes customer entitlements.
# Usage:   ./deploy.sh
# ---------------------------------------------------------------------------
set -euo pipefail

REPO_NAME="saas-platform"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_TAG="deploy-backup-${TIMESTAMP}"

# Colors (no-op if TERM does not support them)
if [[ -t 1 ]]; then
  C_GREEN="$(printf '\033[32m')"
  C_RED="$(printf '\033[31m')"
  C_YELLOW="$(printf '\033[33m')"
  C_BLUE="$(printf '\033[34m')"
  C_RESET="$(printf '\033[0m')"
else
  C_GREEN=""; C_RED=""; C_YELLOW=""; C_BLUE=""; C_RESET=""
fi

step_no=0
ok()   { echo "${C_GREEN}[OK]${C_RESET} $*"; }
warn() { echo "${C_YELLOW}[WARN]${C_RESET} $*"; }
info() { echo "${C_BLUE}[..]${C_RESET} $*"; }
die()  { echo "${C_RED}[FAIL]${C_RESET} $*" >&2; exit 1; }

run_step() {
  step_no=$((step_no + 1))
  local title="$1"; shift
  echo
  info "Step ${step_no}: ${title}"
  echo "       command: $*"
  if "$@"; then
    ok "Step ${step_no}: ${title}"
  else
    die "Step ${step_no} failed: ${title}"
  fi
}

cd "$(dirname "$0")"

echo "==============================================================="
echo " ${REPO_NAME} deployment"
echo " timestamp: ${TIMESTAMP}"
echo "==============================================================="

# Sanity: must be a git working copy on Laravel project root
[[ -d .git ]] || die "Not a git repository: $(pwd)"
[[ -f artisan ]] || die "artisan not found in $(pwd); is this the project root?"
command -v git >/dev/null 2>&1     || die "git is not installed or not in PATH"
command -v composer >/dev/null 2>&1 || die "composer is not installed or not in PATH"
command -v php >/dev/null 2>&1      || die "php is not installed or not in PATH"

GIT_BEFORE="$(git rev-parse HEAD)"
GIT_BEFORE_SHORT="$(git rev-parse --short HEAD)"
echo "current HEAD before deploy: ${GIT_BEFORE_SHORT} (${GIT_BEFORE})"

# --- backup tag (rollback anchor) ---
run_step "Create rollback tag ${BACKUP_TAG}" \
  git tag -a "${BACKUP_TAG}" -m "Automatic deploy backup ${TIMESTAMP} on ${REPO_NAME} (HEAD=${GIT_BEFORE_SHORT})"

# --- required steps ---
run_step "git fetch origin"            git fetch origin
run_step "git checkout main"           git checkout main
run_step "git pull origin main"        git pull --ff-only origin main
run_step "composer install --no-dev --optimize-autoloader" \
  composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

run_step "php artisan migrate --force"  php artisan migrate --force
run_step "php artisan optimize:clear"   php artisan optimize:clear
run_step "php artisan optimize"         php artisan optimize

# --- reload code caches: optimize:clear/optimize only touch Laravel's own
# framework caches (config/route/view) — they do NOT invalidate OPcache or
# restart any long-lived PHP process. Without this, PHP-FPM workers and any
# queue/restore-worker daemon keep serving pre-deploy bytecode from files
# that were already overwritten on disk, so a code fix can be "deployed"
# (present on disk) yet never take effect at runtime. Best-effort: don't
# fail the whole deploy if a given mechanism isn't present on this host.
echo
info "Reloading PHP processes so OPcache picks up the new code..."
run_step "php artisan queue:restart" php artisan queue:restart
if command -v systemctl >/dev/null 2>&1; then
  FPM_UNIT="$(systemctl list-units --type=service --no-legend 2>/dev/null | awk '{print $1}' | grep -m1 '^php.*-fpm\.service$' || true)"
  if [[ -n "${FPM_UNIT}" ]]; then
    if sudo systemctl reload "${FPM_UNIT}" 2>&1; then
      ok "Reloaded ${FPM_UNIT} (OPcache invalidated)"
    else
      warn "Could not reload ${FPM_UNIT} — restart it manually or OPcache may still serve stale bytecode"
    fi
  else
    warn "No php-fpm systemd unit detected — if OPcache is enabled with validate_timestamps=0, restart it manually"
  fi
  if systemctl list-units --all --type=service --no-legend 2>/dev/null | grep -q '^restore-worker\.service'; then
    if sudo systemctl restart restore-worker 2>&1; then
      ok "Restarted restore-worker.service"
    else
      warn "Could not restart restore-worker.service — restart it manually so it picks up the new RestoreService code"
    fi
  fi
fi

# --- optional: node assets ---
echo
if [[ -f package-lock.json ]] && command -v node >/dev/null 2>&1 && command -v npm >/dev/null 2>&1; then
  info "Node detected (node $(node --version), npm $(npm --version)) and package-lock.json present"
  run_step "npm ci"        npm ci
  run_step "npm run build" npm run build
else
  if [[ -f package-lock.json ]]; then
    warn "package-lock.json present but Node/npm not available; skipping frontend build"
  else
    info "no package-lock.json; skipping frontend build"
  fi
fi

# --- optional: supervisor reminder ---
echo
if command -v supervisorctl >/dev/null 2>&1; then
  warn "Supervisor detected. If you run Laravel queue workers under Supervisor, restart them now:"
  warn "    sudo supervisorctl restart all"
  warn "    # or: php artisan queue:restart"
fi

GIT_AFTER="$(git rev-parse HEAD)"
GIT_AFTER_SHORT="$(git rev-parse --short HEAD)"

echo
echo "==============================================================="
ok " ${REPO_NAME} deploy completed successfully"
echo " before: ${GIT_BEFORE_SHORT}"
echo " after:  ${GIT_AFTER_SHORT}"
echo " backup tag (rollback): ${BACKUP_TAG}"
echo
echo " To roll back:"
echo "     git checkout ${BACKUP_TAG} -- ."
echo "     git checkout main"
echo "     git reset --hard ${BACKUP_TAG}"
echo "     # then re-run composer install + artisan optimize"
echo "==============================================================="
