<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Infrastructure\InfraBackupRun;
use App\Models\Infrastructure\RestoreLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class RecoveryManagerController extends Controller
{
    // ─── Main Dashboard ────────────────────────────────────────────────────────

    public function index(): \Illuminate\View\View
    {
        // Latest platform backup runs
        $lastDbRun      = InfraBackupRun::whereNull('site_id')
            ->where('backup_type', 'database')
            ->orderByDesc('started_at')
            ->first();

        $lastStorageRun = InfraBackupRun::whereNull('site_id')
            ->where('backup_type', 'storage')
            ->orderByDesc('started_at')
            ->first();

        // Last successful runs
        $lastDbSuccess = InfraBackupRun::whereNull('site_id')
            ->where('backup_type', 'database')
            ->where('status', 'success')
            ->orderByDesc('completed_at')
            ->first();

        $lastStorageSuccess = InfraBackupRun::whereNull('site_id')
            ->where('backup_type', 'storage')
            ->where('status', 'success')
            ->orderByDesc('completed_at')
            ->first();

        // Recent backup runs (all types, last 15)
        $recentRuns = InfraBackupRun::whereNull('site_id')
            ->orderByDesc('started_at')
            ->limit(15)
            ->get();

        // Recent restore attempts (last 5)
        $recentRestores = RestoreLog::orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Active DR alerts (written by dr:scan-health via persistAlerts)
        $drAlerts = Cache::get('dr_active_alerts', []);

        // Backup health statuses
        $dbHealth      = $this->computeDbHealth($lastDbSuccess);
        $storageHealth = $this->computeStorageHealth($lastStorageSuccess);

        // Backup policy summary (from Kernel schedule)
        $backupPolicy = [
            'db_schedule'      => 'هر روز ساعت ۰۲:۰۰ | نگه‌داری ۷ نسخه',
            'storage_schedule' => 'هر یکشنبه ساعت ۰۳:۳۰ | نگه‌داری ۴ نسخه',
            'trigger_types'    => InfraBackupRun::TRIGGER_DAILY . ', ' . InfraBackupRun::TRIGGER_WEEKLY . ', ' . InfraBackupRun::TRIGGER_MANUAL,
        ];

        return view('admin.recovery-manager.index', compact(
            'lastDbRun',
            'lastStorageRun',
            'lastDbSuccess',
            'lastStorageSuccess',
            'recentRuns',
            'recentRestores',
            'drAlerts',
            'dbHealth',
            'storageHealth',
            'backupPolicy',
        ));
    }

    // ─── PC Sync Agent Script Download ────────────────────────────────────────

    public function downloadSyncScript(Request $request, string $platform): Response
    {
        $baseUrl   = rtrim(config('app.url'), '/');
        $adminPath = '/admin/infrastructure/recovery-repository';

        $script = match ($platform) {
            'windows' => $this->buildWindowsScript($baseUrl, $adminPath),
            'linux'   => $this->buildLinuxScript($baseUrl, $adminPath),
            default   => abort(404),
        };

        $filename = $platform === 'windows'
            ? 'saas-sync-agent.ps1'
            : 'saas-sync-agent.sh';

        $mime = $platform === 'windows'
            ? 'application/octet-stream'
            : 'text/x-sh';

        return response($script, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function computeDbHealth(?InfraBackupRun $last): array
    {
        if (!$last) {
            return ['status' => 'unknown', 'label' => 'هرگز', 'color' => 'slate'];
        }
        $hoursAgo = now()->diffInHours($last->completed_at);
        if ($hoursAgo <= 26) {
            return ['status' => 'healthy', 'label' => "{$hoursAgo} ساعت پیش", 'color' => 'green'];
        }
        if ($hoursAgo <= 48) {
            return ['status' => 'warning', 'label' => "{$hoursAgo} ساعت پیش", 'color' => 'yellow'];
        }
        return ['status' => 'critical', 'label' => "{$hoursAgo} ساعت پیش", 'color' => 'red'];
    }

    private function computeStorageHealth(?InfraBackupRun $last): array
    {
        if (!$last) {
            return ['status' => 'unknown', 'label' => 'هرگز', 'color' => 'slate'];
        }
        $daysAgo = now()->diffInDays($last->completed_at);
        if ($daysAgo <= 8) {
            return ['status' => 'healthy', 'label' => "{$daysAgo} روز پیش", 'color' => 'green'];
        }
        if ($daysAgo <= 14) {
            return ['status' => 'warning', 'label' => "{$daysAgo} روز پیش", 'color' => 'yellow'];
        }
        return ['status' => 'critical', 'label' => "{$daysAgo} روز پیش", 'color' => 'red'];
    }

    private function buildWindowsScript(string $baseUrl, string $adminPath): string
    {
        return <<<POWERSHELL
# ╔══════════════════════════════════════════════════════════════════════════════╗
# ║  SaaS Platform — Local PC Sync Agent (Windows PowerShell)                  ║
# ║  نسخه: 1.0 | تولید شده خودکار توسط Recovery Manager                        ║
# ╚══════════════════════════════════════════════════════════════════════════════╝
#
# راهنمای استفاده:
#   1. این فایل را در D:\SaaS-Recovery\ ذخیره کنید
#   2. مقادیر زیر را پر کنید:
#      - PLATFORM_URL: آدرس پنل ادمین شما
#      - ADMIN_EMAIL: ایمیل ادمین
#      - ADMIN_PASSWORD: رمز عبور ادمین
#   3. برای اجرای خودکار، یک Windows Scheduled Task بسازید:
#      schtasks /create /tn "SaaS-DR-Sync" /tr "powershell -File D:\SaaS-Recovery\saas-sync-agent.ps1" /sc daily /st 04:00
#
# ═════════════════════════════════════════════════════════════════════════════

param(
    [string]\$PlatformUrl  = "{$baseUrl}",
    [string]\$AdminEmail   = "",        # پر کنید
    [string]\$AdminPassword = "",       # پر کنید
    [string]\$LocalDir     = "D:\SaaS-Recovery",
    [int]   \$KeepVersions = 30         # تعداد نسخه‌هایی که نگه داشته می‌شود
)

Set-StrictMode -Version Latest
\$ErrorActionPreference = "Stop"

# ─── Ensure local directory exists ──────────────────────────────────────────
if (-not (Test-Path \$LocalDir)) {
    New-Item -ItemType Directory -Path \$LocalDir -Force | Out-Null
    Write-Host "[+] Directory created: \$LocalDir"
}

\$logFile = Join-Path \$LocalDir "sync.log"
function Log([string]\$msg) {
    \$line = "[{0}] {1}" -f (Get-Date -Format "yyyy-MM-dd HH:mm:ss"), \$msg
    Write-Host \$line
    Add-Content -Path \$logFile -Value \$line -Encoding UTF8
}

Log "=== SaaS DR Sync Agent started ==="

# ─── Validate config ─────────────────────────────────────────────────────────
if (-not \$AdminEmail -or -not \$AdminPassword) {
    Log "ERROR: ADMIN_EMAIL and ADMIN_PASSWORD must be set in the script."
    exit 1
}

# ─── Login and get CSRF + session cookie ─────────────────────────────────────
Log "Authenticating with \$PlatformUrl ..."
\$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

try {
    # Step 1: GET login page (grab CSRF token)
    \$loginPage = Invoke-WebRequest -Uri "\$PlatformUrl/login" -SessionVariable 'session' -UseBasicParsing
    \$csrf      = ([regex]'<meta name="csrf-token" content="([^"]+)"').Match(\$loginPage.Content).Groups[1].Value
    if (-not \$csrf) { throw "Could not extract CSRF token from login page" }

    # Step 2: POST credentials
    \$postData = @{ _token = \$csrf; email = \$AdminEmail; password = \$AdminPassword }
    \$response = Invoke-WebRequest -Uri "\$PlatformUrl/login" -Method POST -Body \$postData `
        -WebSession \$session -UseBasicParsing -MaximumRedirection 5
    Log "Authenticated successfully."
} catch {
    Log "ERROR: Authentication failed: \$_"
    exit 1
}

# ─── Download platform backup package ────────────────────────────────────────
\$downloadUrl = "\$PlatformUrl{$adminPath}/download/platform"
\$timestamp   = Get-Date -Format "yyyy-MM-dd_HHmmss"
\$filename    = "platform_package_\$timestamp.zip"
\$destPath    = Join-Path \$LocalDir \$filename

Log "Downloading platform backup package..."
try {
    Invoke-WebRequest -Uri \$downloadUrl -WebSession \$session -OutFile \$destPath -UseBasicParsing
    \$sizeMb = [math]::Round((Get-Item \$destPath).Length / 1MB, 2)
    Log "Downloaded: \$filename (\$sizeMb MB)"
} catch {
    Log "ERROR: Download failed: \$_"
    if (Test-Path \$destPath) { Remove-Item \$destPath -Force }
    exit 1
}

# ─── Verify ZIP integrity ─────────────────────────────────────────────────────
Log "Verifying ZIP integrity..."
try {
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    \$zip = [System.IO.Compression.ZipFile]::OpenRead(\$destPath)
    \$entryCount = \$zip.Entries.Count
    \$zip.Dispose()
    Log "ZIP OK: \$entryCount entries found."
} catch {
    Log "ERROR: ZIP verification failed: \$_"
    Remove-Item \$destPath -Force
    exit 1
}

# ─── Write manifest entry ─────────────────────────────────────────────────────
\$sha256    = (Get-FileHash -Path \$destPath -Algorithm SHA256).Hash.ToLower()
\$manifest  = Join-Path \$LocalDir "sync-manifest.json"
\$entry     = @{
    filename   = \$filename
    downloaded = (Get-Date -Format "o")
    size_mb    = \$sizeMb
    sha256     = \$sha256
    platform   = \$PlatformUrl
}

\$history = @()
if (Test-Path \$manifest) {
    \$history = Get-Content \$manifest -Raw | ConvertFrom-Json
    if (\$history -isnot [array]) { \$history = @(\$history) }
}
\$history += \$entry
\$history | ConvertTo-Json -Depth 3 | Set-Content \$manifest -Encoding UTF8
Log "Manifest updated. SHA256: \$sha256"

# ─── Prune old versions ───────────────────────────────────────────────────────
\$packages = Get-ChildItem -Path \$LocalDir -Filter "platform_package_*.zip" |
    Sort-Object LastWriteTime -Descending
if (\$packages.Count -gt \$KeepVersions) {
    \$toDelete = \$packages | Select-Object -Skip \$KeepVersions
    foreach (\$f in \$toDelete) {
        Remove-Item \$f.FullName -Force
        Log "Pruned old package: \$(\$f.Name)"
    }
}

Log "=== Sync complete. Packages kept: \$(([math]::Min(\$packages.Count, \$KeepVersions))) ==="
POWERSHELL;
    }

    private function buildLinuxScript(string $baseUrl, string $adminPath): string
    {
        return <<<'BASH'
#!/usr/bin/env bash
# ╔══════════════════════════════════════════════════════════════════════════════╗
# ║  SaaS Platform — Local PC Sync Agent (Linux / macOS bash)                  ║
# ║  نسخه: 1.0 | تولید شده خودکار توسط Recovery Manager                        ║
# ╚══════════════════════════════════════════════════════════════════════════════╝
#
# راهنمای استفاده:
#   1. این فایل را در ~/saas-recovery/ ذخیره کنید
#   2. مقادیر زیر را پر کنید
#   3. برای اجرای خودکار: crontab -e و اضافه کنید:
#      0 4 * * * /bin/bash ~/saas-recovery/saas-sync-agent.sh >> ~/saas-recovery/sync.log 2>&1
#
# ═════════════════════════════════════════════════════════════════════════════

set -euo pipefail

PLATFORM_URL="PLATFORM_URL_HERE"   # پر کنید — بدون slash انتهایی
ADMIN_EMAIL="ADMIN_EMAIL_HERE"     # پر کنید
ADMIN_PASSWORD="ADMIN_PASSWORD_HERE" # پر کنید
LOCAL_DIR="${HOME}/saas-recovery"
KEEP_VERSIONS=30
ADMIN_PATH="/admin/infrastructure/recovery-repository"

# ─── Init ────────────────────────────────────────────────────────────────────
mkdir -p "${LOCAL_DIR}"
LOG_FILE="${LOCAL_DIR}/sync.log"
COOKIE_JAR="${LOCAL_DIR}/.cookies"
TIMESTAMP=$(date +"%Y-%m-%d_%H%M%S")

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "${LOG_FILE}"; }

log "=== SaaS DR Sync Agent started ==="

# Validate config
if [[ -z "${ADMIN_EMAIL}" || "${ADMIN_EMAIL}" == "ADMIN_EMAIL_HERE" ]]; then
    log "ERROR: Please configure ADMIN_EMAIL and ADMIN_PASSWORD in this script."
    exit 1
fi

# ─── Authenticate ─────────────────────────────────────────────────────────────
log "Authenticating with ${PLATFORM_URL} ..."
rm -f "${COOKIE_JAR}"

# Step 1: Get CSRF token
LOGIN_HTML=$(curl -s -c "${COOKIE_JAR}" "${PLATFORM_URL}/login")
CSRF=$(echo "${LOGIN_HTML}" | grep -oP '(?<=<meta name="csrf-token" content=")[^"]+')
if [[ -z "${CSRF}" ]]; then
    log "ERROR: Could not extract CSRF token from login page."
    exit 1
fi

# Step 2: POST credentials
curl -s -b "${COOKIE_JAR}" -c "${COOKIE_JAR}" \
    -X POST "${PLATFORM_URL}/login" \
    -F "_token=${CSRF}" \
    -F "email=${ADMIN_EMAIL}" \
    -F "password=${ADMIN_PASSWORD}" \
    -L -o /dev/null
log "Authenticated."

# ─── Download platform backup package ────────────────────────────────────────
DOWNLOAD_URL="${PLATFORM_URL}${ADMIN_PATH}/download/platform"
FILENAME="platform_package_${TIMESTAMP}.zip"
DEST="${LOCAL_DIR}/${FILENAME}"

log "Downloading platform backup package..."
HTTP_CODE=$(curl -s -b "${COOKIE_JAR}" -L "${DOWNLOAD_URL}" \
    -o "${DEST}" -w "%{http_code}")

if [[ "${HTTP_CODE}" != "200" ]] || [[ ! -f "${DEST}" ]] || [[ $(stat -c%s "${DEST}" 2>/dev/null || stat -f%z "${DEST}") -lt 1024 ]]; then
    log "ERROR: Download failed (HTTP ${HTTP_CODE})."
    rm -f "${DEST}"
    exit 1
fi

SIZE_MB=$(echo "scale=2; $(stat -c%s "${DEST}" 2>/dev/null || stat -f%z "${DEST}") / 1048576" | bc)
log "Downloaded: ${FILENAME} (${SIZE_MB} MB)"

# ─── Verify ZIP integrity ─────────────────────────────────────────────────────
log "Verifying ZIP integrity..."
if ! unzip -t "${DEST}" > /dev/null 2>&1; then
    log "ERROR: ZIP verification failed."
    rm -f "${DEST}"
    exit 1
fi
ENTRY_COUNT=$(unzip -l "${DEST}" | tail -1 | awk '{print $2}')
log "ZIP OK: ${ENTRY_COUNT} entries found."

# ─── Write manifest entry ─────────────────────────────────────────────────────
SHA256=$(sha256sum "${DEST}" 2>/dev/null | awk '{print $1}' || shasum -a 256 "${DEST}" | awk '{print $1}')
MANIFEST="${LOCAL_DIR}/sync-manifest.json"

ENTRY=$(cat <<JSON
{"filename":"${FILENAME}","downloaded":"$(date -u +%FT%TZ)","size_mb":${SIZE_MB},"sha256":"${SHA256}","platform":"${PLATFORM_URL}"}
JSON
)

if [[ -f "${MANIFEST}" ]]; then
    # Append new entry to JSON array
    TMP=$(mktemp)
    python3 -c "
import json, sys
with open('${MANIFEST}') as f:
    data = json.load(f)
if not isinstance(data, list): data = [data]
data.append(json.loads(sys.argv[1]))
print(json.dumps(data, indent=2, ensure_ascii=False))
" "${ENTRY}" > "${TMP}" && mv "${TMP}" "${MANIFEST}"
else
    echo "[${ENTRY}]" > "${MANIFEST}"
fi
log "Manifest updated. SHA256: ${SHA256}"

# ─── Prune old versions ───────────────────────────────────────────────────────
mapfile -t PACKAGES < <(ls -t "${LOCAL_DIR}"/platform_package_*.zip 2>/dev/null)
if [[ ${#PACKAGES[@]} -gt ${KEEP_VERSIONS} ]]; then
    for f in "${PACKAGES[@]:${KEEP_VERSIONS}}"; do
        rm -f "${f}"
        log "Pruned old package: $(basename "${f}")"
    done
fi

log "=== Sync complete. Packages kept: $((${#PACKAGES[@]} < KEEP_VERSIONS ? ${#PACKAGES[@]} : KEEP_VERSIONS)) ==="
BASH;
    }
}
