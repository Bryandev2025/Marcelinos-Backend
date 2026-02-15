# Running Laravel Services (Single Command)

This document describes how to run all long-running Laravel processes with **one command**: WebSocket (Reverb), queue worker (mail, broadcasts), and scheduler (booking tasks). Use this on **cPanel** or any server where you want to avoid managing multiple commands.

---

## What gets started

| Service | Command | Purpose |
|--------|---------|--------|
| **Reverb** | `php artisan reverb:start` | WebSocket server for real-time updates (rooms, venues, etc.) |
| **Queue worker** | `php artisan queue:work` | Processes queued jobs (emails, broadcast events) |
| **Scheduler** | `php artisan schedule:work` | Runs scheduled tasks every minute (booking status updates) |

When you use the single-command setup, **you do not need** a separate cron job for `schedule:run` — `schedule:work` runs the scheduler inside the process.

---

## Single command (Linux / cPanel)

### Start all services

```bash
cd /path/to/be-marcelinos
php artisan services:start
```

Or run the script directly (make it executable once: `chmod +x start-services.sh`):

```bash
./start-services.sh
```

This starts Reverb, queue worker, and scheduler in the background. PIDs are saved to `storage/app/services.pids`. Logs go to:

- `storage/logs/reverb.log`
- `storage/logs/queue.log`
- `storage/logs/schedule.log`

### Stop all services

```bash
php artisan services:stop
# or
./stop-services.sh
```

This stops the processes started by `services:start` using the saved PIDs.

---

## cPanel setup

On cPanel you often have **Cron Jobs** and sometimes **Setup Node.js App** or similar. Use cron to start services at server boot so they keep running after deploy or reboot.

### Option 1: Start services at reboot (recommended)

Add **one** cron job:

| When | Command |
|------|---------|
| **Once at reboot** | `@reboot cd /home/youruser/be-marcelinos && ./start-services.sh >> storage/logs/start-services.log 2>&1` |

Replace `/home/youruser/be-marcelinos` with your actual project path. Ensure the script is executable:

```bash
chmod +x /home/youruser/be-marcelinos/start-services.sh
chmod +x /home/youruser/be-marcelinos/stop-services.sh
```

After a server reboot, Reverb, queue, and scheduler will start automatically. You do **not** need a separate “every minute” cron for the scheduler.

### Option 2: No @reboot (e.g. cPanel doesn’t support it)

If your host doesn’t support `@reboot`, run the start command once after each deploy or server restart (e.g. via SSH or cPanel “Run a script” / “Execute script” if available):

```bash
cd /path/to/be-marcelinos && php artisan services:start
```

You can also add a cron that runs once per hour to “re-start” if processes die (optional, not ideal):

```bash
0 * * * * cd /path/to/be-marcelinos && ./start-services.sh >> storage/logs/start-services.log 2>&1
```

Note: running `start-services.sh` again while processes are already running will start **duplicate** processes. Use `services:stop` first if you need a clean restart.

---

## Requirements

- **PHP** with `php` in PATH when cron runs (or use full path to `php`, e.g. `/usr/local/bin/php`).
- **.env** must have:
  - `BROADCAST_CONNECTION=reverb` (for WebSocket)
  - `QUEUE_CONNECTION=database` (or `redis`) and migrations run for `jobs` table if using database queue.
- **Reverb config** in `.env`: `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, etc. (see `documentation/realtime-websocket.md`).

---

## Windows (local dev)

On Windows, `services:start` does not run the shell script. Run these in **three separate terminals**:

```bash
# Terminal 1
php artisan reverb:start

# Terminal 2
php artisan queue:work

# Terminal 3
php artisan schedule:work
```

Or use WSL and run `./start-services.sh` or `php artisan services:start`.

---

## Relation to other docs

- **Scheduler / booking tasks:** `documentation/cron-job.md` — when you use `schedule:work` (via `services:start`), you do **not** need the “every minute” cron entry described there; `schedule:work` replaces it.
- **WebSocket / Reverb:** `documentation/realtime-websocket.md` — config and troubleshooting for Reverb and the React client.

---

## File reference

| File | Purpose |
|------|--------|
| `start-services.sh` | Starts Reverb, queue worker, scheduler in background; saves PIDs to `storage/app/services.pids` |
| `stop-services.sh` | Stops processes using saved PIDs |
| `app/Console/Commands/ServicesStart.php` | Artisan command: `php artisan services:start` |
| `app/Console/Commands/ServicesStop.php` | Artisan command: `php artisan services:stop` |
