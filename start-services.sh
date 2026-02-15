#!/usr/bin/env bash
#
# Start all Laravel long-running services (Reverb, queue worker, scheduler).
# Use for local dev or on cPanel: run once or via cron @reboot.
# Stop with: ./stop-services.sh  (or php artisan services:stop)
#
# Usage: ./start-services.sh
#   Or:  php artisan services:start

set -e
cd "$(dirname "$0")"

PID_FILE="storage/app/services.pids"
LOG_DIR="storage/logs"
mkdir -p "$(dirname "$PID_FILE")" "$LOG_DIR"

# Clear previous PIDs so we don't kill wrong processes
: > "$PID_FILE"

echo "Starting Laravel services..."

# 1. Reverb (WebSocket)
nohup php artisan reverb:start >> "$LOG_DIR/reverb.log" 2>&1 &
echo $! >> "$PID_FILE"
echo "  - Reverb (WebSocket) started (PID $(tail -1 "$PID_FILE"))"

# 2. Queue worker (jobs: mail, broadcasts, etc.)
nohup php artisan queue:work --sleep=3 --tries=3 >> "$LOG_DIR/queue.log" 2>&1 &
echo $! >> "$PID_FILE"
echo "  - Queue worker started (PID $(tail -1 "$PID_FILE"))"

# 3. Scheduler (runs scheduled tasks every minute; replaces cron schedule:run)
nohup php artisan schedule:work >> "$LOG_DIR/schedule.log" 2>&1 &
echo $! >> "$PID_FILE"
echo "  - Scheduler (schedule:work) started (PID $(tail -1 "$PID_FILE"))"

echo ""
echo "All services started. PIDs saved to $PID_FILE"
echo "Logs: $LOG_DIR/reverb.log, $LOG_DIR/queue.log, $LOG_DIR/schedule.log"
echo "To stop: ./stop-services.sh  or  php artisan services:stop"
