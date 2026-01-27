# Filament Laravel Policy Authorization Guide

## What the Docs Say (Authorization)

Filament integrates with Laravel model policies to manage resource access control. For any resource:

- **`viewAny()` in the policy:**  
  Determines if the resource appears in the navigation and is accessible at all.

- **Other policy methods (`create`, `update`, `view`, `delete`, etc.):**  
  Govern specific actions on the resource (who can create, edit, view, or delete).

**Best Practice**: Always control access via the Policy. Filament prefers policies over hardcoded methods in the resource itself. Although methods like `canViewAny()` exist, policies are the documented and canonical approach.

---

## Step-by-Step: Using `viewAny()` the Filament Way

### 1. Create a Policy (Laravel Standard)
```shell
php artisan make:policy BookingPolicy --model=Booking
```
This command generates `app/Policies/BookingPolicy.php`.

---

### 2. Implement `viewAny()` for Your Roles

If both staff _and_ admin should see the resource:
```php
public function viewAny(User $user): bool
{
    return in_array($user->role, ['admin', 'staff'], true);
}
```

If only admin should see the resource:
```php
public function viewAny(User $user): bool
{
    return $user->role === 'admin';
}
```

> This method acts as the gatekeeper for the entire resource (navigation and access).

---

### 3. Register the Policy in the AuthServiceProvider
```php
protected $policies = [
    Booking::class => BookingPolicy::class,
];
```

Filament will now use `viewAny()` to control access to the resource automatically.

---

## Why Use `viewAny()` in a Policy?

- Aligns with Laravel and Filament best practices
- Centralizes authorization logic (Separation of Concerns)
- Scales as you add more roles or permissions
- Prevents accidental access via deep routing

---

## (Optional) Control Actions Too

For example, to control who can create bookings:
```php
public function create(User $user): bool
{
    return $user->role === 'admin';
}
```
Filament will use this method for the Create page/button.

---

## Quick Test Checklist

- Log in as a staff user.
- Refresh the Filament sidebar.
- `BookingResource` will be visible if `viewAny()` returns `true`.
- Other resources (like Revenue) will not be visible if `viewAny()` returns `false` for that role.

---

## Recap Table

| Filament Hook             | Where It Should Go          |
|---------------------------|----------------------------|
| `viewAny()`               | Laravel Policy             |
| `create()`, `update()`, etc. | Laravel Policy         |
| `canAccess()`             | Only for custom Pages/Widgets (not main resources) |

> **Docs say:** If you have a policy, make sure `viewAny()` returns true for a role to see the navigation link and have access to the resource.