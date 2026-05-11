@echo off
:: ---------------------------------------------------------------------------
:: saas-platform deployment script (Windows / cmd.exe)
:: Idempotent, fail-fast, with timestamped rollback tag.
:: Compatible with Laravel 10/11/12.
:: Never resets, deletes data, or revokes customer entitlements.
:: Usage:   deploy.bat
:: ---------------------------------------------------------------------------
setlocal enableextensions enabledelayedexpansion

set "REPO_NAME=saas-platform"

:: Reliable timestamp via PowerShell (locale-independent)
for /f "usebackq tokens=*" %%i in (`powershell -NoProfile -Command "Get-Date -Format yyyyMMdd-HHmmss"`) do set "TIMESTAMP=%%i"
if "%TIMESTAMP%"=="" (
  echo [FAIL] Unable to compute timestamp via PowerShell.
  exit /b 1
)
set "BACKUP_TAG=deploy-backup-%TIMESTAMP%"

cd /d "%~dp0"

echo ===============================================================
echo  %REPO_NAME% deployment
echo  timestamp: %TIMESTAMP%
echo ===============================================================

:: --- sanity checks ---
if not exist ".git\" ( echo [FAIL] Not a git repository: %CD%& exit /b 1 )
if not exist "artisan" ( echo [FAIL] artisan not found in %CD%; is this the project root?& exit /b 1 )
where git >nul 2>&1 || ( echo [FAIL] git is not installed or not in PATH.& exit /b 1 )
where composer >nul 2>&1 || ( echo [FAIL] composer is not installed or not in PATH.& exit /b 1 )
where php >nul 2>&1 || ( echo [FAIL] php is not installed or not in PATH.& exit /b 1 )

for /f "usebackq tokens=*" %%h in (`git rev-parse HEAD`) do set "GIT_BEFORE=%%h"
for /f "usebackq tokens=*" %%h in (`git rev-parse --short HEAD`) do set "GIT_BEFORE_SHORT=%%h"
echo current HEAD before deploy: %GIT_BEFORE_SHORT% (%GIT_BEFORE%)

echo.
echo [..] Step 1: Create rollback tag %BACKUP_TAG%
git tag -a "%BACKUP_TAG%" -m "Automatic deploy backup %TIMESTAMP% on %REPO_NAME% (HEAD=%GIT_BEFORE_SHORT%)"
if errorlevel 1 ( echo [FAIL] Step 1 failed: create rollback tag& exit /b 1 )
echo [OK] Step 1: rollback tag created

echo.
echo [..] Step 2: git fetch origin
git fetch origin
if errorlevel 1 ( echo [FAIL] Step 2 failed: git fetch origin& exit /b 1 )
echo [OK] Step 2: git fetch origin

echo.
echo [..] Step 3: git checkout main
git checkout main
if errorlevel 1 ( echo [FAIL] Step 3 failed: git checkout main& exit /b 1 )
echo [OK] Step 3: git checkout main

echo.
echo [..] Step 4: git pull origin main (ff-only)
git pull --ff-only origin main
if errorlevel 1 ( echo [FAIL] Step 4 failed: git pull& exit /b 1 )
echo [OK] Step 4: git pull origin main

echo.
echo [..] Step 5: composer install --no-dev --optimize-autoloader
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
if errorlevel 1 ( echo [FAIL] Step 5 failed: composer install& exit /b 1 )
echo [OK] Step 5: composer install

echo.
echo [..] Step 6: php artisan migrate --force
php artisan migrate --force
if errorlevel 1 ( echo [FAIL] Step 6 failed: php artisan migrate& exit /b 1 )
echo [OK] Step 6: php artisan migrate --force

echo.
echo [..] Step 7: php artisan optimize:clear
php artisan optimize:clear
if errorlevel 1 ( echo [FAIL] Step 7 failed: php artisan optimize:clear& exit /b 1 )
echo [OK] Step 7: php artisan optimize:clear

echo.
echo [..] Step 8: php artisan optimize
php artisan optimize
if errorlevel 1 ( echo [FAIL] Step 8 failed: php artisan optimize& exit /b 1 )
echo [OK] Step 8: php artisan optimize

:: --- optional: node assets ---
echo.
if exist "package-lock.json" (
  where node >nul 2>&1
  if !errorlevel! equ 0 (
    where npm >nul 2>&1
    if !errorlevel! equ 0 (
      echo [..] Step 9: npm ci (Node and package-lock.json detected)
      call npm ci
      if errorlevel 1 ( echo [FAIL] Step 9 failed: npm ci& exit /b 1 )
      echo [OK] Step 9: npm ci

      echo.
      echo [..] Step 10: npm run build
      call npm run build
      if errorlevel 1 ( echo [FAIL] Step 10 failed: npm run build& exit /b 1 )
      echo [OK] Step 10: npm run build
    ) else (
      echo [WARN] package-lock.json present but npm not in PATH; skipping frontend build
    )
  ) else (
    echo [WARN] package-lock.json present but node not in PATH; skipping frontend build
  )
) else (
  echo [..] no package-lock.json; skipping frontend build
)

:: --- optional: queue reminder ---
echo.
echo [WARN] If queue workers are running on this host, remember to restart them:
echo [WARN]     php artisan queue:restart
echo [WARN]     :: On Linux production with Supervisor: sudo supervisorctl restart all

for /f "usebackq tokens=*" %%h in (`git rev-parse HEAD`) do set "GIT_AFTER=%%h"
for /f "usebackq tokens=*" %%h in (`git rev-parse --short HEAD`) do set "GIT_AFTER_SHORT=%%h"

echo.
echo ===============================================================
echo [OK] %REPO_NAME% deploy completed successfully
echo  before: %GIT_BEFORE_SHORT%
echo  after:  %GIT_AFTER_SHORT%
echo  backup tag (rollback): %BACKUP_TAG%
echo.
echo  To roll back:
echo      git checkout %BACKUP_TAG% -- .
echo      git checkout main
echo      git reset --hard %BACKUP_TAG%
echo      :: then re-run composer install + artisan optimize
echo ===============================================================

endlocal
exit /b 0
