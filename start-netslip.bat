@echo off
setlocal EnableExtensions
title NetSlip launcher
cd /d "%~dp0"

if not exist "Backend\vendor\autoload.php" (echo Run setup-windows.bat first. & pause & exit /b 1)
if not exist "Frontend\node_modules" (echo Run setup-windows.bat first. & pause & exit /b 1)

rem Three windows: API on :8000, scheduler (overdue invoices, digests), web app on :5173.
rem Close those windows to stop NetSlip.
start "NetSlip API - http://127.0.0.1:8000" /D "%~dp0Backend" cmd /k php artisan serve --host=127.0.0.1 --port=8000
start "NetSlip scheduler" /D "%~dp0Backend" cmd /k php artisan schedule:work
start "NetSlip web - http://localhost:5173" /D "%~dp0Frontend" cmd /k npm run dev

echo Starting NetSlip... the browser opens in a few seconds.
timeout /t 8 /nobreak >nul
start "" "http://localhost:5173"
start "" "http://127.0.0.1:8000/admin"
exit /b 0
