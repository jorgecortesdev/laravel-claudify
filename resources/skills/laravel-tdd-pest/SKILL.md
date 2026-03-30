---
name: laravel-tdd-pest
description: >-
  Test-driven development for Laravel with Pest. Use this skill whenever writing tests,
  adding test coverage, doing TDD, or when the user says "test this", "add tests",
  "write tests", or "test-driven". Covers the full red-green-refactor cycle with
  Laravel-specific patterns: HTTP testing, fakes, factories, database assertions,
  and migration ordering. Also use when reviewing whether code has adequate test coverage.
---

# TDD — Red-Green-Refactor for Laravel

Every feature, every bug fix, every refactor starts with a failing test. No exceptions.

## The Iron Law

> No production code exists without a failing test that demands it.

This is not a guideline. It is a constraint. If you wrote code before a test, delete the code and start over. Do not "adapt" tests to match existing code — that proves nothing about correctness.

## The Cycle

### 1. RED — Write a failing test

Write the smallest possible test that describes the next behavior you need. Run it. Watch it fail. If it passes, either the behavior already exists or your test is wrong — investigate before continuing.

### 2. GREEN — Write the minimum code to pass

Write only enough production code to make the failing test pass. Not clean code. Not future-proof code. The dumbest thing that works.

### 3. REFACTOR — Clean up under green tests

Now improve the code. Extract constants, rename things, restructure. Run the tests after every change. If they go red, undo and try again.

Then start the cycle again with the next behavior.

## Test Organization

- **Feature tests** (`tests/Feature/`): Test behavior through HTTP, queues, events. Use `RefreshDatabase`. This is the default.
- **Unit tests** (`tests/Unit/`): Test isolated logic — value objects, calculations, pure functions. No database.

Default to Feature tests. Only use Unit for genuinely isolated logic.

### Pest vs PHPUnit

Use Pest for new test files. When adding tests to an existing PHPUnit file, use PHPUnit syntax in that file. Don't convert existing PHPUnit files to Pest unless explicitly asked.

## Database Strategy

- **`RefreshDatabase`** — the default. Runs migrations once per test run, wraps each test in a transaction. Use this unless you have a reason not to.
- **`DatabaseTransactions`** — when the schema is already migrated and you only need per-test rollback.
- **`DatabaseMigrations`** — full migrate/fresh for every test. Expensive, rarely needed.

## Migration Ordering

When a test needs schema changes:
1. Write the test (it will fail — missing column/table)
2. Write the migration to make the schema match
3. Re-run the test (it should fail for the RIGHT reason now — missing logic, not missing schema)
4. Write the production code

## Common Setup

```php
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});
```

## Testing HTTP

```php
it('creates a project', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/projects', ['name' => 'Acme']);

    $response->assertCreated();
    expect(Project::where('name', 'Acme')->exists())->toBeTrue();
});
```

## Fakes Over Mocks

Use Laravel fakes — they're purpose-built and more reliable than generic mocks.

```php
it('sends a welcome email on registration', function () {
    Mail::fake();

    // ... trigger registration ...

    Mail::assertSent(WelcomeEmail::class, function ($mail) {
        return $mail->hasTo('user@example.com');
    });
});
```

```php
it('dispatches order confirmation job', function () {
    Bus::fake();

    // ... place order ...

    Bus::assertDispatched(SendOrderConfirmation::class);
});
```

```php
it('notifies user when invoice is ready', function () {
    Notification::fake();

    $user->notify(new InvoiceReady($invoice));

    Notification::assertSentTo($user, InvoiceReady::class);
});
```

Available fakes: `Mail::fake()`, `Event::fake()`, `Bus::fake()`, `Notification::fake()`, `Queue::fake()`, `Storage::fake()`, `Http::fake()`.

## Testing External APIs

Isolate external services with `Http::fake()` so tests never make real network calls.

```php
it('fetches weather data from external api', function () {
    Http::fake([
        'api.weather.com/*' => Http::response(['temp' => 22], 200),
    ]);

    $result = $this->weatherService->getCurrentTemp('London');

    expect($result)->toBe(22);
    Http::assertSent(fn ($request) => $request->url() === 'https://api.weather.com/london');
});
```

## Auth Testing

```php
// Standard Laravel auth
$this->actingAs($user)->getJson('/api/projects');

// Sanctum API tokens
Sanctum::actingAs($user);
$this->getJson('/api/projects')->assertOk();
```

## Authorization / Policies

```php
it('allows owner to update project', function () {
    expect(Gate::forUser($this->user)->allows('update', $project))->toBeTrue();
});

it('denies non-owner from updating project', function () {
    $other = User::factory()->create();

    expect(Gate::forUser($other)->allows('update', $project))->toBeFalse();
});
```

## Inertia Testing

When using Inertia.js, assert on the component name and props rather than raw JSON.

```php
it('renders dashboard with user projects', function () {
    $response = $this->actingAs($this->user)->get('/dashboard');

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Dashboard')
        ->where('user.id', $this->user->id)
        ->has('projects')
    );
});
```

## Database Assertions

```php
$this->assertDatabaseHas('projects', ['name' => 'Acme']);
$this->assertDatabaseMissing('users', ['email' => 'deleted@example.com']);
$this->assertDatabaseCount('projects', 3);
```

## Running Tests

```bash
php artisan test                        # all tests
php artisan test --filter=OrderTest     # specific test file
php artisan test --filter="calculates"  # specific test name
php artisan test --parallel             # parallel execution
```

Always run the specific test first, then the full suite before declaring done.

## Rationalizations to resist

| Rationalization | Why it's wrong |
|---|---|
| "The logic is straightforward, no test needed" | Straightforward logic is the easiest to test. If it's too simple to test, it's too simple to get wrong — and yet you will. |
| "I'll write tests after the implementation" | Tests written after are biased by the code they observe. They test what IS, not what SHOULD BE. |
| "This is just a config/view change" | Config changes break things. View changes break things. If it can break, it needs a test. |
| "I verified by reading the code" | Reading is not proving. Code that looks correct fails in production daily. Run the tests. |
| "There are no existing tests for this area" | Then you're the one who starts. Write the first test. |
| "The tests would be trivial" | Trivial tests are fast to write and catch regressions forever. |
| "I'll come back and add tests later" | No you won't. The Iron Law exists because "later" never comes. |

## Exceptions (real ones)

- **Spikes and prototypes** explicitly labeled as throwaway. These never merge to main.
- **Generated code** — test the behavior it enables, not the generated files.
- **Pure config** — but test the behavior that reads the config.

## Checklist before declaring done

1. All new behavior has at least one test.
2. Edge cases have dedicated tests (nulls, empty collections, boundaries).
3. `php artisan test` passes with zero failures.
4. `./vendor/bin/pint --test` passes.
5. No test uses `assertTrue(true)` or other tautologies.
