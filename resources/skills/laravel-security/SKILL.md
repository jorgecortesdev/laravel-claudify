---
name: laravel-security
description: >-
  Security patterns for Laravel applications. Use this skill when implementing
  authentication, authorization, input validation, or any security-related feature.
  Triggers on: auth, login, permissions, policies, gates, CSRF, XSS, SQL injection,
  rate limiting, Sanctum, tokens, passwords, middleware, "is this secure", "security
  review", or any code that handles user input, file uploads, or sensitive data.
---

# Laravel Security

Security is not a feature you add later — it's a constraint on every line of code that handles user input, authentication, or data access.

## Input Validation

Every external input must be validated before it touches your business logic. Laravel's Form Requests are the right place for this.

```php
final class StoreCommentRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
            'parent_id' => ['nullable', 'exists:comments,id'],
        ];
    }
}
```

Validate at the boundary (controller layer), not deep in services. If a service receives data, it should assume validation already happened.

### File uploads

Never trust file extensions or MIME types from the client. Validate the actual content.

```php
'avatar' => ['required', 'image', 'mimes:jpg,png,webp', 'max:2048'],
'document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
```

Store uploads outside the public directory when possible. Use `Storage::disk('s3')` or `Storage::disk('local')` with controlled access — not `public/uploads/`.

## Authentication

### Sanctum for API tokens

```php
// Issue a token
$token = $user->createToken('api-access', ['projects:read']);

// Protect routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/projects', [ProjectController::class, 'index']);
});
```

### Password hashing

Laravel uses bcrypt by default. Never store or compare plain text passwords.

```php
// Hashing happens automatically in User::create() if using $casts or mutators
// For manual hashing:
$hashed = Hash::make($plainPassword);

// Verification:
if (Hash::check($plainPassword, $user->password)) {
    // authenticated
}
```

### Rate limiting

Protect login endpoints and sensitive operations from brute force.

**Quickest approach** — use the built-in `throttle` middleware:

```php
Route::post('/login', LoginController::class)->middleware('throttle:5,1');
```

This blocks the IP after 5 requests per minute and returns `429 Too Many Requests`.

**Custom rate limiter** — for more control (e.g., limiting by IP + email combo):

```php
// In bootstrap/app.php (Laravel 11+) or RouteServiceProvider
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip().'|'.$request->input('email'));
});

Route::post('/login', LoginController::class)->middleware('throttle:login');
```

## Authorization

### Policies for model-level access

Policies answer: "Can this user perform this action on this model?"

```php
final class ProjectPolicy
{
    public function update(User $user, Project $project): bool
    {
        return $user->id === $project->owner_id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->id === $project->owner_id;
    }
}
```

### Enforcing authorization

```php
// In controllers — throws 403 automatically
$this->authorize('update', $project);

// In Blade
@can('update', $project)
    <button>Edit</button>
@endcan

// Inline check
Gate::forUser($user)->allows('update', $project);
```

### Middleware for role-based access

```php
Route::middleware('can:admin')->group(function () {
    Route::resource('/admin/users', AdminUserController::class);
});
```

## Preventing Common Vulnerabilities

### SQL Injection

Eloquent and the query builder use parameterized queries by default. You're safe as long as you don't interpolate user input into raw queries.

```php
// Safe — parameterized
User::where('email', $request->email)->first();
DB::table('users')->where('email', '=', $email)->first();

// DANGEROUS — raw interpolation
DB::select("SELECT * FROM users WHERE email = '$email'");

// Safe raw query — with bindings
DB::select('SELECT * FROM users WHERE email = ?', [$email]);
```

### XSS (Cross-Site Scripting)

Blade's `{{ }}` syntax escapes output by default. Use `{!! !!}` only when you explicitly trust the content (rendered markdown from admin input, for example — never from user input).

```blade
{{-- Safe — escaped --}}
<p>{{ $user->bio }}</p>

{{-- Dangerous — unescaped, only use for trusted HTML --}}
<div>{!! $trustedHtml !!}</div>
```

If you need to allow a subset of HTML (bold, links, lists), sanitize with `mews/purifier` before storing:

```php
// Install: composer require mews/purifier
$clean = clean($userHtml); // strips scripts, event handlers, dangerous attributes
```

### CSRF Protection

Laravel includes CSRF protection on all POST/PUT/PATCH/DELETE routes by default. For SPAs using Sanctum, use the `/sanctum/csrf-cookie` endpoint.

```blade
<form method="POST" action="/projects">
    @csrf
    <!-- form fields -->
</form>
```

### Mass Assignment

Only list fillable fields explicitly. Never use `$guarded = []` — it disables mass assignment protection entirely.

```php
// Good — explicit whitelist
protected $fillable = ['name', 'email'];

// Dangerous — everything is assignable
protected $guarded = [];
```

## Security Checklist

When reviewing or writing code that handles auth, input, or sensitive data:

1. All user input validated via Form Requests before use.
2. Authorization checked via policies or gates — not just authentication.
3. No raw SQL with interpolated user input.
4. File uploads validated by type and size, stored outside public directory.
5. Passwords hashed, never stored or logged in plain text.
6. Rate limiting on login and sensitive endpoints.
7. CSRF protection active on state-changing routes.
8. Blade output escaped by default (`{{ }}` not `{!! !!}`).
9. Sensitive data (tokens, keys) in `.env`, never in code or version control.
