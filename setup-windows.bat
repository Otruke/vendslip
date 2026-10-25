@echo off
setlocal EnableExtensions
title NetSlip - first-time setup
cd /d "%~dp0"

echo.
echo  NetSlip local setup
echo  ===================
echo.

rem ---- 1. Check tools -------------------------------------------------------
where php >nul 2>nul || (echo [X] PHP was not found. Add your PHP 8.4+ folder to PATH, then run this again. & goto :fail)
where composer >nul 2>nul || (echo [X] Composer was not found. Install it from https://getcomposer.org, then run this again. & goto :fail)
where npm >nul 2>nul || (echo [X] Node.js / npm was not found. Install Node 20+ from https://nodejs.org, then run this again. & goto :fail)

php -r "exit(version_compare(PHP_VERSION, '8.4.1', '>=') ? 0 : 1);" || (echo [X] PHP 8.4.1 or newer is required. & php -v & goto :fail)
php -r "$m = array_filter(['pdo_pgsql','mbstring','openssl','fileinfo','gd','dom','zip','curl'], fn($e) => !extension_loaded($e)); if ($m) { echo '[X] Enable these PHP extensions in php.ini: '.implode(', ', $m).PHP_EOL; exit(1);} echo '[OK] PHP '.PHP_VERSION.' with all required extensions'.PHP_EOL;" || goto :fail

rem ---- 2. Backend ------------------------------------------------------------
cd /d "%~dp0Backend"
set "NEWENV="
if not exist ".env" (
    copy /y ".env.example" ".env" >nul
    set "NEWENV=1"
    echo.
    echo [!] Created Backend\.env. Notepad will open: set DB_PASSWORD for PostgreSQL
    echo     and NETSLIP_SUPER_ADMIN_PASSWORD, then save and close Notepad.
    start /wait notepad ".env"
)

echo.
echo [..] Installing PHP packages (composer install)...
call composer install --no-interaction || goto :fail
if defined NEWENV php artisan key:generate --force || goto :fail

echo.
echo [!] The next step RESETS the database named in Backend\.env (DB_DATABASE)
echo     and loads the demo shop. Create the database in pgAdmin first if needed.
choice /C YN /M "    Reset the database and load demo data now"
if errorlevel 2 goto :skipdb
php artisan optimize:clear
php artisan migrate:fresh --seed --force || (echo [X] Database step failed. Check DB_* in Backend\.env and that PostgreSQL is running. & goto :fail)
:skipdb

if not exist "public\storage" php artisan storage:link

rem ---- 3. Frontend -----------------------------------------------------------
cd /d "%~dp0Frontend"
echo.
echo [..] Installing frontend packages (npm install)...
call npm install --no-fund --no-audit || goto :fail

echo.
echo  [OK] Setup complete. Double-click start-netslip.bat to run NetSlip.
echo       If a super-admin password was printed above, copy it now.
echo.
pause
exit /b 0

:fail
echo.
echo  Setup stopped - fix the message above and run setup-windows.bat again.
echo.
pause
exit /b 1
