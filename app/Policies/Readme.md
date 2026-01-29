# 🛡️ The Complete Filament Laravel Authorization Guide

Filament does not have its own "permissions system." Instead, it is designed to listen to **Laravel Model Policies**. This ensures that your security logic stays in one place, whether you are using the Filament UI, an API, or a custom controller.



---

## ⚙️ Step 1: Create the Policy
Generate a policy linked to your Model. For this example, we will use a `Booking` model.

```bash
php artisan make:policy BookingPolicy --model=Booking
```

---

## 🔗 Step 2: Register the Policy
Laravel usually auto-discovers policies, but for Filament, it is best practice to be explicit.

**For Laravel 11+:** Register in `bootstrap/app.php` or a Service Provider.
**For Laravel 10 and below:** Register in `app/Providers/AuthServiceProvider.php`.

```php
protected $policies = [
    \App\Models\Booking::class => \App\Policies\BookingPolicy::class,
];
```

---

## 🏗️ Step 3: Implement the Logic
Inside `app/Policies/BookingPolicy.php`, you define who can do what.

### A. The "Gatekeeper" (`viewAny`)
This is the most important method. If this returns `false`, the resource **disappears** from the sidebar and the Index page becomes inaccessible.

```php
public function viewAny(User $user): bool
{
    // Only Admin and Staff can see the "Bookings" link in the sidebar
    return $user->hasRole(['admin', 'staff']);
}
```

### B. Record-Level Security (`update` & `delete`)
Filament passes the specific **record instance** into these methods, allowing you to restrict actions based on ownership.

```php
public function update(User $user, Booking $booking): bool
{
    // Admins can edit anything
    if ($user->role === 'admin') return true;

    // Staff can only edit bookings they created
    return $user->id === $booking->user_id;
}
```

---

## 🗺️ Policy-to-UI Mapping Table
Filament looks for these specific method names to decide what to show in the UI.

| Policy Method | UI Element Affected | Route Protection |
| :--- | :--- | :--- |
| `viewAny($user)` | Sidebar Nav & Index Table | `/admin/bookings` |
| `view($user, $record)` | "View" Action/Icon | `/admin/bookings/{id}` |
| `create($user)` | "Create" Header Button | `/admin/bookings/create` |
| `update($user, $record)` | "Edit" Action/Icon | `/admin/bookings/{id}/edit` |
| `delete($user, $record)` | "Delete" Action/Icon | Blocked at Controller |
| `restore($user, $record)` | "Restore" (Soft Deletes) | Blocked at Controller |
| `forceDelete(...)` | "Force Delete" (Soft Deletes) | Blocked at Controller |

---

## ⚠️ UI Visibility vs. Hard Security
It is a common mistake to use `->visible()` for security. Here is the difference:

### ❌ The "Weak" Way (UI Only)
```php
// In BookingResource.php
Tables\Actions\DeleteAction::make()
    ->visible(fn () => auth()->user()->isAdmin())
```
* **Result:** The button is hidden, but a savvy user could still hit the delete endpoint via an API tool or a crafted request.

### ✅ The "Strong" Way (Policy)
```php
// In BookingPolicy.php
public function delete(User $user, Booking $booking): bool
{
    return $user->isAdmin();
}
```
* **Result:** Filament **automatically** hides the button AND the server will reject any manual attempt to delete the record.

---

## ✅ Final Checklist
- [ ] Does the User model have the necessary role/permission logic?
- [ ] Is the Policy registered?
- [ ] Does `viewAny` return `true` for the users who need access?
- [ ] If using Soft Deletes, did you implement `restore` and `forceDelete`?