<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## Single Login for Multiple Filament Panels (Admin + Staff)

This project uses ONE login page at `/login` for multiple Filament panels (`/admin` and `/staff`). After authenticating, users are redirected to the correct panel based on their role. Logout always returns to `/login`.

### Key Changes
- Post-login and logout behavior overridden via Filament response contracts:
	- Login redirect by role: [app/Http/Responses/LoginResponse.php](app/Http/Responses/LoginResponse.php)
	- Logout always to `/login`: [app/Http/Responses/LogoutResponse.php](app/Http/Responses/LogoutResponse.php)
	- Service container bindings for Filament v3 contracts: [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php)
- Single login endpoint using Filament’s native login component:
	- `/login` maps to the admin panel login action: [routes/web.php](routes/web.php)
- Panels configuration:
	- Admin panel is default and provides login, plus admin-only guard: [app/Providers/Filament/AdminPanelProvider.php](app/Providers/Filament/AdminPanelProvider.php)
	- Staff panel has no login, only the app: [app/Providers/Filament/StaffPanelProvider.php](app/Providers/Filament/StaffPanelProvider.php)
- Role + access rules:
	- Panel access rules with normalized roles: [app/Models/User.php](app/Models/User.php)
	- Middleware to keep non-admins out of admin pages (redirect to staff): [app/Http/Middleware/EnsureAdminUser.php](app/Http/Middleware/EnsureAdminUser.php)
- Unauthenticated redirects (guests) go to `/login`:
	- Global exception handler: [bootstrap/app.php](bootstrap/app.php)

### Flow
- Everyone signs in at `/login` (admin panel’s Filament login under the hood).
- After login:
	- `admin` → `/admin`
	- others (e.g., `staff`) → `/staff`
- Staff can authenticate via the admin login page, but `EnsureAdminUser` protects admin pages and redirects staff to `/staff`.
- Logout from any panel → `/login`.

### Quick Test
```bash
php artisan optimize:clear
```
- Visit https://marcelinos-backend.test/login
	- Admin logs in → https://marcelinos-backend.test/admin
	- Staff logs in → https://marcelinos-backend.test/staff
- Logout from either panel → https://marcelinos-backend.test/login

### Tweaks
- Change role names or logic: [app/Models/User.php](app/Models/User.php)
- Adjust post-login redirect: [app/Http/Responses/LoginResponse.php](app/Http/Responses/LoginResponse.php)
- Adjust logout destination: [app/Http/Responses/LogoutResponse.php](app/Http/Responses/LogoutResponse.php)
- Harden/relax admin protection: [app/Http/Middleware/EnsureAdminUser.php](app/Http/Middleware/EnsureAdminUser.php)
