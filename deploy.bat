@echo off
REM Legacy production deployment path — intentionally blocked.
REM Production releases are owned by deployment-system and must use a committed source revision.
REM This script never mutates Git state or contacts production.
echo Legacy direct deployment is blocked. Use deployment-system with a committed source revision. 1>&2
exit /b 1
