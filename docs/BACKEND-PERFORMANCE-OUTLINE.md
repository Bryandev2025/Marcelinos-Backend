# Backend Performance & Robustness — Implementation Outline

Step-by-step plan to make the Marcelinos API faster, more robust, and easier to maintain. Implement in the order below for best impact.

---

## 1. Rate limiting (throttling)

**Goal:** Protect APIs from abuse and overload.

**Steps:**

1. **Define named rate limits** (Laravel 11)
   - In `App\Providers\AppServiceProvider::boot()` (or create `App\Providers\RouteServiceProvider` if you prefer), call:
     - `RateLimiter::for('api', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));`
     - `RateLimiter::for('bookings', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));`
     - `RateLimiter::for('contact', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));`
   - Use `Illuminate\Cache\RateLimiting\Limit` and `Illuminate\Support\Facades\RateLimiter`.

2. **Apply middleware in `routes/api.php`**
   - Wrap all API routes in a group with `middleware('throttle:api')`.
   - For `POST /bookings` and `POST /bookings/reference/{reference}/review`, add `throttle:bookings` (e.g. chain: `throttle:api,throttle:bookings`).
   - For `POST /contact`, use `throttle:contact` (or `throttle:api,throttle:contact`).

**Files to touch:** `app/Providers/AppServiceProvider.php`, `routes/api.php`.

---

## 2. Fix N+1 and heavy logic in BlockedDateController

**Goal:** Avoid N+1 queries and reduce memory/CPU when computing blocked dates.

**Steps:**

1. **Eager load relations**
   - In `getBookingBlockedDates()`, change the booking query to:
     - `Booking::with(['rooms', 'venues'])->whereIn('status', [...])->get()`
   - In the loop, use `$booking->rooms->count()` and `$booking->venues->count()` (no `->count()` on the relation builder) so no extra queries per booking.

2. **Optional: precompute blocked dates (for scale)**
   - If the number of bookings grows large, consider:
     - A scheduled job (e.g. daily or every 15 min) that computes blocked dates and stores them in cache (e.g. `api.blocked-dates`) or a small table.
     - `BlockedDateController::index()` then only reads from cache/table instead of recalculating on every request.

**Files to touch:** `app/Http/Controllers/API/BlockedDateController.php`.

---

## 3. Cache invalidation on write

**Goal:** When data changes, cached API responses stay correct.

**Steps:**

1. **Create observers (or use model events)**
   - For models that affect cached responses: `Room`, `Venue`, `BlockedDate`, `Booking` (for blocked-dates and possibly booking lists).
   - In each observer’s `saved` / `deleted` (as appropriate), clear the relevant cache tags/keys:
     - Rooms: `Cache::forget('api.rooms.list.all');` and consider a pattern for `api.rooms.show.{id}` when that room is updated.
     - Venues: same for `api.venues.list.all` and `api.venues.show.{id}`.
     - BlockedDate: `Cache::forget('api.blocked-dates');`
     - Booking (when status/dates change): `Cache::forget('api.blocked-dates');` and any booking-list caches if you add them later.

2. **Register observers**
   - In `App\Providers\EventServiceProvider::boot()` (or `AppServiceProvider`), register: `Room::observe(RoomObserver::class);` (and same for Venue, BlockedDate, Booking if you add a booking observer).

**Files to create/touch:** `app/Observers/RoomObserver.php`, `VenueObserver.php`, `BlockedDateObserver.php`, optionally `BookingObserver.php`; `app/Providers/EventServiceProvider.php` or `AppServiceProvider.php`.

---

## 4. Use Redis for cache in production

**Goal:** Faster and more reliable response caching than database driver.

**Steps:**

1. **Environment**
   - In production `.env`, set `CACHE_STORE=redis` (and ensure `REDIS_*` connection config is correct).

2. **Code**
   - No code change required; `config/cache.php` already defines the `redis` store. Optional: use `Cache::store('redis')` only where you need to force Redis; otherwise the default store is enough.

**Files to touch:** `.env` (and `.env.example` with a comment).

---

## 5. Cache availability list responses (rooms/venues with dates)

**Goal:** Reduce DB load for repeated calendar/availability requests.

**Steps:**

1. **In RoomController and VenueController `index()`**
   - When `!$isAll` and `check_in` / `check_out` are present, build a cache key, e.g. using the existing trait helper: `listCacheKey('rooms', ['check_in' => $checkIn->toDateString(), 'check_out' => $checkOut->toDateString()])`.
   - Use a short TTL (e.g. 60–120 seconds): `$ttl = 120;`
   - Call `rememberJson($cacheKey, fn () => response()->json($payload, 200), $ttl)` instead of returning the response directly (and keep `$cacheKey = null` and `$ttl = 0` when you intentionally skip cache).

2. **Ensure cache key is built after validation**
   - Use the same `$checkIn` / `$checkOut` values you validated so the key is consistent.

**Files to touch:** `app/Http/Controllers/API/RoomController.php`, `app/Http/Controllers/API/VenueController.php`.

---

## 6. Paginate booking list and add DB indexes

**Goal:** Keep list endpoints fast and stable as data grows.

**Steps:**

1. **Paginate BookingController::index()**
   - Replace `Booking::with([...])->get()` with `->paginate(15)` (or `perPage` from request with a max, e.g. 50).
   - Return the paginated result (Laravel returns `data`, `links`, `meta` by default) so the frontend can handle pages.

2. **Database indexes**
   - Add migrations for:
     - `bookings`: index on `status`; composite or separate indexes on `check_in`, `check_out`; unique (or index) on `reference_number` if lookups are frequent.
     - `blocked_dates`: index on `date` if you query by date.
   - Run migrations in dev/staging first, then production.

**Files to touch:** `app/Http/Controllers/API/BookingController.php`; new migrations under `database/migrations/`.

---

## 7. Form Requests and API Resources (consistency)

**Goal:** Centralize validation and response shape for maintainability and consistent API contract.

**Steps:**

1. **Form Requests**
   - Create e.g. `App\Http\Requests\API\StoreBookingRequest`, `ContactRequest`, `RoomsIndexRequest`, `VenuesIndexRequest` with `authorize()` and `rules()`.
   - In controllers, type-hint these requests instead of `Request` and remove inline `$request->validate(...)`.

2. **API Resources**
   - Create `App\Http\Resources\API\RoomResource`, `VenueResource`, `BookingResource` (and optionally for review, gallery) with `toArray()`.
   - In RoomController/VenueController/BookingController, return `RoomResource::collection($rooms)` or `new RoomResource($room)` (and same for venues/bookings) so JSON structure is consistent and easy to change in one place.

**Files to create:** `app/Http/Requests/API/*.php`, `app/Http/Resources/API/*.php`. **Touch:** corresponding controllers.

---

## 8. Queue emails and heavy work

**Goal:** Faster API response and resilient handling of failures.

**Steps:**

1. **Configure queue**
   - Set `QUEUE_CONNECTION=database` or `redis` in `.env`; run `php artisan queue:table` and `migrate` if using database driver.

2. **Jobs**
   - Create jobs for: sending contact form email, sending booking confirmation email, and any PDF generation or external API calls.
   - In controllers, `dispatch(new SendContactEmail(...))` (and similar) instead of doing the work synchronously.

3. **Run workers**
   - In production, run `php artisan queue:work` (or Supervisor/systemd) so jobs are processed.

**Files to create:** `app/Jobs/SendContactEmail.php`, `app/Jobs/SendBookingConfirmation.php`, etc. **Touch:** `ContactController`, `BookingController`, `.env`.

---

## 9. Route cleanup

**Goal:** No duplicate or confusing route definitions.

**Steps:**

1. **In `routes/api.php`**
   - Remove duplicate route definitions: keep a single `GET /rooms`, `GET /rooms/{id}`, `GET /venues`, `GET /venues/{id}` (remove the second set under “Room”/“Venue”).
   - Remove `Route::apiResource('/bookings/store', BookingController::class)` unless you intentionally want resourceful routes under that path; you already have explicit `get/post bookings` and related routes.

**Files to touch:** `routes/api.php`.

---

## 10. Optional: health check and response headers

**Goal:** Better operability and client caching.

**Steps:**

1. **Health endpoint**
   - Laravel 11’s `health: '/up'` may already be set in `bootstrap/app.php`. If you need an API-specific health (e.g. DB + cache), add `GET /api/health` that runs a simple DB query and optional cache check and returns 200/503.

2. **Cache-Control for cacheable GETs**
   - Add middleware or in-controller headers for list/show of rooms, venues, blocked-dates: e.g. `Cache-Control: public, max-age=60` (or 300 for `is_all` responses) so clients and reverse proxies can cache when appropriate.

**Files to touch:** `routes/api.php` (health), middleware or controllers (headers).

---

## Checklist (copy and tick off)

- [x] **1** Rate limits defined and applied in `api.php`
- [x] **2** BlockedDateController N+1 fixed; optional precompute job
- [x] **3** Observers for Room, Venue, BlockedDate (and optionally Booking); cache cleared on write
- [ ] **4** `CACHE_STORE=redis` in production (set in .env when deploying)
- [x] **5** Rooms/Venues availability lists cached with short TTL
- [x] **6** Bookings index paginated; DB indexes added and migrated
- [x] **7** Form Requests and API Resources introduced
- [x] **8** Contact and booking emails (and heavy work) queued
- [x] **9** Duplicate routes removed in `api.php`
- [x] **10** Optional: API health endpoint and Cache-Control headers

---

## Suggested order of implementation

1. **Quick wins:** 1 (throttle), 2 (BlockedDateController), 9 (route cleanup).  
2. **Cache correctness:** 3 (invalidation), 4 (Redis), 5 (availability cache).  
3. **Scale and consistency:** 6 (pagination + indexes), 7 (Form Requests + Resources).  
4. **Resilience:** 8 (queues).  
5. **Ops:** 10 (health, headers).

After each step, run existing tests and manually smoke-test the affected endpoints.
