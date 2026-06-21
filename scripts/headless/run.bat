@echo off
REM Playwright Headless Extractor - Windows wrapper (DEBUG)

echo OPENSSL_CONF=[%OPENSSL_CONF%] > "C:\laragon\www\saas-platform\scripts\headless\debug.txt"
echo OPENSSL_CONF_DEFINED=%OPENSSL_CONF% >> "C:\laragon\www\saas-platform\scripts\headless\debug.txt"
set >> "C:\laragon\www\saas-platform\scripts\headless\debug.txt"

set PHP_FCGI_CHILDREN=
set PHP_FCGI_MAX_REQUESTS=
set PHPRC=
set PHPINI=
set PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=C:\Program Files\Google\Chrome\Application\chrome.exe

node "%~dp0extract.mjs" %*
echo NODE_EXIT=%errorlevel% >> "C:\laragon\www\saas-platform\scripts\headless\debug.txt"
