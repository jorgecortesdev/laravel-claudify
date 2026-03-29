---
name: tdd-pest
description: >-
  TDD patterns for Laravel with Pest.
  Load alongside the base `tdd` skill for the universal process.
  Use when writing tests in a Laravel/PHP project.
---

# TDD — Laravel/Pest Patterns

Language-specific patterns for Laravel projects. The base `tdd` skill has the universal process (Iron Law, cycle, rationalizations).

## Test Organization

- **Feature tests** (`tests/Feature/`): Test behavior through HTTP, queues, events. Use `RefreshDatabase`.
- **Unit tests** (`tests/Unit/`): Test isolated logic — value objects, calculations, pure functions. No database.

Default to Feature tests. Only use Unit for genuinely isolated logic.

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

Available fakes: `Mail::fake()`, `Event::fake()`, `Bus::fake()`, `Notification::fake()`, `Queue::fake()`, `Storage::fake()`, `Http::fake()`.

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

## Checklist Before Declaring Done

1. All new behavior has at least one test.
2. Edge cases have dedicated tests (nulls, empty collections, boundaries).
3. `php artisan test` passes with zero failures.
4. `./vendor/bin/pint --test` passes (formatting).
5. No test uses `assertTrue(true)` or other tautologies.
