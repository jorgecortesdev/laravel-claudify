---
name: laravel-conventions
description: >-
  Laravel and PHP coding conventions and standards. Use this skill when writing new
  Laravel code, reviewing code quality, or when the user asks about best practices,
  coding standards, or "how should I write this". Covers strict typing, type hints,
  readonly properties, enums, DTOs, value objects, naming conventions, and code
  organization. Also triggers when creating new classes, controllers, models, services,
  or any PHP file in a Laravel project.
---

# Laravel Conventions

Standards for writing clean, modern Laravel code. These are conventions, not rules — adapt them to the project's existing patterns when they conflict.

## PHP Fundamentals

### Strict types in every file

```php
<?php

declare(strict_types=1);
```

This catches type coercion bugs at the source. A function expecting `int` will reject `"42"` instead of silently converting it. Add it to every PHP file you create.

### Type everything

Parameters, return types, properties — type them all. When the type system knows what flows through your code, it catches bugs before tests do.

```php
final class InvoiceService
{
    public function calculate(Order $order): Money
    {
        // ...
    }
}
```

Avoid `mixed` unless you genuinely accept any type. If you're tempted to use `mixed`, the design probably needs rethinking.

### Use readonly for immutable data

Properties that shouldn't change after construction should say so. This prevents accidental mutation and communicates intent.

```php
final readonly class CreateUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}
```

### Use enums for fixed sets of values

Don't use string constants or magic strings for statuses, types, or roles. Backed enums give you type safety, IDE autocomplete, exhaustive `match` checking, and native Laravel integration.

```php
enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
        };
    }
}
```

Enums integrate deeply with Laravel:

```php
// Eloquent casting — get enum instances on read, strings stored on write
protected function casts(): array
{
    return ['status' => OrderStatus::class];
}

// Form Request validation — no manual 'in:' list to keep in sync
'status' => ['required', Rule::enum(OrderStatus::class)],

// Route binding, Blade, and collections all work natively
@if($order->status === OrderStatus::Shipped)
```

### Mark classes as final by default

Unless a class is explicitly designed for inheritance, make it `final`. This prevents accidental coupling and makes refactoring safer.

```php
final class OrderService
{
    // ...
}
```

## Laravel Patterns

### Methods: return data OR mutate state, not both

A method should either compute and return a value, or perform a side effect and return void. Mixing both makes code harder to test and reason about.

Eloquent persistence methods are exempt — `create()`, `save()`, `update()`, `delete()` all return data AND mutate. That's the Active Record pattern. Fluent/builder APIs returning `$this` are also exempt. Custom methods are not.

### Controllers: thin, single-purpose

Controllers receive a request and return a response. Business logic belongs in services or actions — not in the controller.

```php
final class StoreProjectController extends Controller
{
    public function __invoke(StoreProjectRequest $request, ProjectService $service): JsonResponse
    {
        $project = $service->create($request->validated());

        return response()->json($project, 201);
    }
}
```

Single-action controllers with `__invoke` are preferred when a controller handles one endpoint.

Controller actions with route-model-bound parameters are exempt from argument count limits — Laravel injects these, you don't control the signature.

### Form Requests for validation

Validation rules belong in Form Requests, not in controllers or services.

```php
final class StoreProjectRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
```

### Data Transfer Objects for structured input

When passing data between layers (controller → service, service → repository), use typed DTOs instead of raw arrays.

```php
final readonly class CreateProjectData
{
    public function __construct(
        public string $name,
        public ?string $description,
    ) {}

    public static function fromRequest(StoreProjectRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            description: $request->validated('description'),
        );
    }
}
```

### Models: lean, focused

Models handle relationships, scopes, casts, and accessors. They are NOT the place for business logic, email sending, or complex calculations.

```php
final class Project extends Model
{
    protected $fillable = ['name', 'description', 'status'];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProjectStatus::Active);
    }
}
```

### API Resources for output shaping

Don't return models directly from APIs. Resources give you control over the response shape without coupling your API to your database schema.

```php
final class ProjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status->label(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

## Naming Conventions

| Thing | Convention | Example |
|---|---|---|
| Controllers | PascalCase, suffixed | `StoreProjectController` |
| Models | Singular PascalCase | `Project`, `OrderItem` |
| Migrations | Snake case, descriptive | `create_projects_table` |
| Form Requests | Action + Model + Request | `StoreProjectRequest` |
| Enums | PascalCase | `OrderStatus` |
| DTOs | Action + Model + Data | `CreateProjectData` |
| Services | Model + Service | `ProjectService` |
| Scopes | `scope` + PascalCase | `scopeActive` |
| Factories | Model + Factory | `ProjectFactory` |

## File Organization

Follow Laravel's default structure. Don't over-organize with deep nesting unless the project is large enough to warrant it.

```
app/
├── Http/Controllers/
├── Http/Requests/
├── Models/
├── Services/
├── Enums/
├── Data/              # DTOs
└── Policies/
```

Only add directories when you have files to put in them. An empty `app/Repositories/` is premature abstraction.
