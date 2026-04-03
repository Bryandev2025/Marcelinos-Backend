@echo off
REM Start queue worker and scheduler in separate windows.
REM Run from project root: start-services.bat  or  php artisan services:start

cd /d "%~dp0"

start "Laravel Queue"  cmd /k "php artisan queue:work --sleep=3 --tries=3"
start "Laravel Scheduler" cmd /k "php artisan schedule:work"

echo.
echo Started 2 windows: Queue, Scheduler.
echo Close each window to stop that service.
echo.
