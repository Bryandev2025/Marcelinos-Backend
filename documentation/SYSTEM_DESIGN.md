# System Design: APIs & Caching

This document describes the system design practices applied across the **MARCELINOS** client (React/Vite) and backend (Laravel) for APIs and caching.

---

## 1. Overview

| Layer | Purpose |
|-------|--------|
| **Client** | Single API client (Axios), TanStack Query for caching/deduplication, centralized endpoints and query keys |
| **Backend** | Response caching for read-heavy GET endpoints, cache invalidation on data change |

---

## 2. Client (React) Architecture

### 2.1 API client (`client-marcelinos/src/lib/api/apiClient.ts`)

- **Axios** instance with `baseURL` from env (`VITE_API_URL_DEV` / `VITE_API_URL_PROD`).
- Shared `Content-Type: application/json` and a **response interceptor** that normalizes errors (message + optional `response` for conflict details).
- Wrapper object **`API`** with `get`, `post`, `put`, `patch`, `delete` so all requests go through one place.

### 2.2 Centralized endpoints and query keys (`client-marcelinos/src/lib/api/endpoints.ts`)

- **`endpoints`**: All API path segments (e.g. `/rooms`, `/venues`, `/booking-receipt/:reference`, `/contact`). Use these instead of hardcoding URLs.
- **`queryKeys`**: Factory for TanStack Query keys (e.g. `queryKeys.rooms.list(checkIn, checkOut)`, `queryKeys.blockedDates.all`). Use these for:
  - Consistent cache keys across components
  - Easy invalidation: `queryClient.invalidateQueries({ queryKey: queryKeys.rooms.all })`

### 2.3 TanStack Query (`queryClient.ts`, `useApiQuery`, `useApiMutation`)

- **Default caching**
  - **staleTime**: 2 minutes — requests with the same query key are served from cache and not refetched until data is stale.
  - **gcTime**: 10 minutes — inactive cache entries stay in memory for background refetch.
- **Retry**: 1 retry for queries; 0 for mutations.
- **refetchOnWindowFocus**: off by default (override per query if needed).

Using **`useApiQuery(key, endpoint, options?)`** and **`useApiMutation(method, options?)`** keeps cache behavior and API calls consistent. Prefer **`queryKeys`** and **`endpoints`** from `endpoints.ts` when adding new features.

### 2.4 Contact form

- Contact form uses **`API.post(endpoints.contact, formData)`** so the request uses the same base URL and error handling as the rest of the app (no raw `fetch` to a hardcoded host).

---

## 3. Backend (Laravel) Caching

### 3.1 Response caching

- **Trait**: `App\Http\Controllers\API\Concerns\CachesApiResponses`.
- **Method**: `$this->rememberJson($cacheKey, fn () => response()->json(...), $ttlSeconds)`.
  - Caches the JSON response (body, status, headers) under `$cacheKey` for `$ttlSeconds`.
  - If `$cacheKey` is `null` or TTL is 0, no caching (e.g. availability by date range).

**Where it’s used**

| Endpoint | Cache key | TTL | Notes |
|----------|-----------|-----|--------|
| `GET /blocked-dates` | `api.blocked-dates` | 10 min | List changes rarely |
| `GET /rooms?is_all=1` | `api.rooms.list.all` | 5 min | Full list cached |
| `GET /rooms?check_in=...&check_out=...` | — | 0 | Not cached (availability varies) |
| `GET /rooms/{id}` | `api.rooms.show.{id}` | 5 min | Single room |
| `GET /venues?is_all=1` | `api.venues.list.all` | 5 min | Full list cached |
| `GET /venues?check_in=...&check_out=...` | — | 0 | Not cached |
| `GET /venues/{id}` | `api.venues.show.{id}` | 5 min | Single venue |

Laravel’s default cache store (e.g. `config('cache.default')`) is used; for production, **Redis** is recommended (`CACHE_STORE=redis`).

### 3.2 Cache invalidation

When admin (Filament) or code changes data, cache is cleared so the next API request gets fresh data:

- **Room** saved/deleted → **RoomObserver** forgets `api.rooms.show.{id}` and `api.rooms.list.all`.
- **Venue** saved/deleted → **VenueObserver** forgets `api.venues.show.{id}` and `api.venues.list.all`.
- **BlockedDate** saved/deleted → **BlockedDateObserver** forgets `api.blocked-dates`.

Observers are registered in `AppServiceProvider::boot()`.

---

## 4. API surface

- **Base URL**: From env on the client; Laravel API routes are under `/api` (e.g. `https://api.example.com/api`).
- **Contact**: `POST /api/contact` — validated by `ContactController::store`; extend with storage or email queue as needed.
- **Bookings, rooms, venues, blocked-dates, reviews, etc.**: See `be-marcelinos/routes/api.php` and `client-marcelinos/src/lib/api/endpoints.ts`.

---

## 5. Recommendations

1. **Client**
   - Use **`endpoints`** and **`queryKeys`** for all new API calls and queries.
   - Prefer **`useApiQuery`** / **`useApiMutation`** over direct `API.get/post/...` in UI so caching and loading/error state are consistent.
   - Adjust **staleTime** per query for rarely changing data (e.g. blocked dates) or real-time needs (e.g. booking status).

2. **Backend**
   - Use **Redis** for cache in production: set `CACHE_STORE=redis` and configure `config/database.php` Redis connection.
   - Add **HTTP cache headers** (e.g. `Cache-Control`, `ETag`) later if you need CDN or browser caching.
   - When adding new read-heavy GET endpoints, consider `CachesApiResponses` and a matching observer if the underlying model changes.

3. **Security / ops**
   - Keep **CORS**, **rate limiting**, and **auth** (e.g. Sanctum) configured for `/api` as appropriate.
   - Ensure `.env` (and Vite env) use correct `VITE_API_URL_*` and Laravel `APP_URL` / `FRONTEND_URL` for your environments.

---

## 6. File reference

| Area | Files |
|------|--------|
| Client API | `client-marcelinos/src/lib/api/apiClient.ts`, `endpoints.ts`, `queryClient.ts` |
| Client hooks | `client-marcelinos/src/lib/api/queries/useApiQuery.ts`, `mutations/useApiMutation.ts` |
| Backend cache | `be-marcelinos/app/Http/Controllers/API/Concerns/CachesApiResponses.php` |
| Backend observers | `be-marcelinos/app/Observers/RoomObserver.php`, `VenueObserver.php`, `BlockedDateObserver.php` |
| Config | `be-marcelinos/config/cache.php`, `client-marcelinos/.env` (Vite vars) |
