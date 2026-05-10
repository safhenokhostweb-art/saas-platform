@echo off
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0sync-cursor-rules.ps1" %*
