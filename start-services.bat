@echo off
REM Start Reverb, queue worker, and scheduler in separate windows.
REM Run from project root: start-services.bat  or  php artisan services:start

cd /d "%~dp0"

start "Laravel Reverb" cmd /k "php artisan reverb:start"
start "Laravel Queue"  cmd /k "php artisan queue:work --sleep=3 --tries=3"
start "Laravel Scheduler" cmd /k "php artisan schedule:work"

echo.
echo Started 3 windows: Reverb, Queue, Scheduler.
echo Close each window to stop that service.
echo.
