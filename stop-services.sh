#!/usr/bin/env bash
#
# Stop Laravel services started by start-services.sh.
#
# Usage: ./stop-services.sh  (or php artisan services:stop)

set -e
cd "$(dirname "$0")"

PID_FILE="storage/app/services.pids"

if [[ ! -f "$PID_FILE" ]]; then
  echo "No PID file found ($PID_FILE). Services may not be running."
  exit 0
fi

echo "Stopping Laravel services..."
while read -r pid; do
  if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
    kill "$pid" 2>/dev/null || true
    echo "  Stopped PID $pid"
  fi
done < "$PID_FILE"

: > "$PID_FILE"
echo "Done."
