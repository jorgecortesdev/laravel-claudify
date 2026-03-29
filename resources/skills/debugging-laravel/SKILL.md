---
name: debugging-laravel
description: >-
  Laravel-specific debugging tools and patterns.
  Load alongside the base `debugging` skill for the universal process.
  Use when debugging in a Laravel/PHP project.
---

# Debugging — Laravel Tools

Language-specific tools for Laravel projects. The base `debugging` skill has the universal process (4 phases, rationalizations).

## Phase 1 Tools: Investigate

| Source | What to check |
|---|---|
| `storage/logs/laravel.log` | Application errors, stack traces, context |
| Queue worker output | Failed jobs, timeouts, memory issues |
| Browser console | JS errors, failed network requests |
| `php artisan route:list` | Verify the right route is matched |
| Git history | `git log --oneline -20`, `git diff HEAD~5` |

## Phase 2: Reproduce

Write a failing Pest test that captures the bug:

### HTTP bugs

```php
it('reproduces the 500 on project creation with duplicate name', function () {
    Project::factory()->create(['name' => 'Existing']);

    $response = $this->actingAs($this->user)
        ->postJson('/api/projects', ['name' => 'Existing']);

    // Currently returns 500 — should return 422
    $response->assertUnprocessable();
});
```

### Console/Job bugs

```php
it('reproduces the job failure on missing user', function () {
    $job = new ProcessOrder(userId: 999);

    expect(fn () => $job->handle())->toThrow(ModelNotFoundException::class);
});
```

### Data bugs

Identify the exact database state that triggers the issue. Use factories to recreate it.

## Phase 3 Tools: Diagnose

Trace the bug through the Laravel stack:

```
Request → Route → Middleware → Controller → FormRequest → Service → Model → Database
```

| Tool | When |
|---|---|
| `Log::debug()` | Trace values through the call chain |
| `dd()` / `dump()` | Interactive inspection in browser/tinker |
| `php artisan tinker` | Test hypotheses in isolation |
| `DB::listen()` | See the actual SQL being executed |
| `->toSql()` / `->getBindings()` | Inspect query builder output |
| `storage/logs/laravel.log` | Historical error context |

### Multi-layer instrumentation

```php
// Temporary — remove after diagnosis
Log::debug('Controller hit', ['input' => $request->validated()]);
Log::debug('Service called', ['args' => compact('name', 'userId')]);
Log::debug('Query result', ['count' => $query->count()]);
```

### Common Laravel-specific bug patterns

- **N+1 queries**: Missing `with()` eager loading
- **Mass assignment**: Unfillable attributes silently ignored
- **Middleware order**: Auth/rate-limit/CORS applied in wrong sequence
- **Queue serialization**: Model state stale when job executes
- **Event listeners**: Side effects in unexpected order

## Phase 4: Fix

1. Confirm your diagnosis explains ALL symptoms.
2. Fix at the root cause.
3. Run the reproduction test — it should pass.
4. Run the full test suite.
5. Remove all temporary instrumentation (Log, dd, dump).

## Checklist Before Declaring Fixed

1. Root cause identified and documented (in the commit message).
2. Reproduction test exists and passes.
3. Fix is at the root cause, not a symptom.
4. Full test suite passes.
5. All temporary instrumentation removed.
6. `./vendor/bin/pint --test` passes.
