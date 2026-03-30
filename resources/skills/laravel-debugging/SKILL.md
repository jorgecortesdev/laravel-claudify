---
name: laravel-debugging
description: >-
  Systematic root-cause debugging for Laravel applications. Use this skill whenever
  something is broken, failing, erroring, not working, or behaving unexpectedly in a
  Laravel or PHP project. Covers the full debugging process: investigating logs and
  errors, writing reproduction tests with Pest, diagnosing through the Laravel request
  lifecycle, and fixing at the root cause. Also triggers on common Laravel issues like
  N+1 queries, failed jobs, missing routes, middleware problems, and queue failures.
---

# Debugging Laravel — Root Cause or Nothing

Never guess. Never apply a fix without understanding the cause. Every bug has a root cause, and the fix goes there — not on a symptom.

## The 4 Phases

### Phase 1: Investigate

Gather evidence before forming any hypothesis.

1. **Read the error** — the full stack trace, not just the message. Note the exact file, line, and call chain.
2. **Check logs** — `storage/logs/laravel.log`, queue worker output, browser console.
3. **Check recent changes** — `git log --oneline -20`, `git diff HEAD~5`. Bugs cluster around recent changes.
4. **Verify the request path** — `php artisan route:list` to confirm the right route is matched.

| Source | What to look for |
|---|---|
| `storage/logs/laravel.log` | Application errors, stack traces, context arrays |
| Queue worker output | Failed jobs, timeouts, memory exhaustion |
| Browser console | JS errors, failed network requests, CORS issues |
| `php artisan route:list` | Route conflicts, wrong middleware, missing bindings |
| Git history | What changed recently that could have introduced this |

### Phase 2: Reproduce

Create a minimal reproduction — this is not optional. Write a failing Pest test that captures the exact conditions. This test becomes your proof of fix.

**HTTP bugs:**

```php
it('reproduces the 500 on project creation with duplicate name', function () {
    Project::factory()->create(['name' => 'Existing']);

    $response = $this->actingAs($this->user)
        ->postJson('/api/projects', ['name' => 'Existing']);

    // Currently returns 500 — should return 422
    $response->assertUnprocessable();
});
```

**Console/Job bugs:**

```php
it('reproduces the job failure on missing user', function () {
    $job = new ProcessOrder(userId: 999);

    expect(fn () => $job->handle())->toThrow(ModelNotFoundException::class);
});
```

**Data bugs:** Identify the exact database state that triggers the issue. Use factories to recreate it precisely.

### Phase 3: Diagnose

Trace the bug through the Laravel request lifecycle:

```
Request → Route → Middleware → Controller → FormRequest → Service → Model → Database
```

Add instrumentation at every layer boundary, trigger the bug once, then read the full trace. Don't guess which layer — let the data tell you.

| Tool | When to use it |
|---|---|
| `Log::debug()` | Trace values through the call chain |
| `dd()` / `dump()` | Quick interactive inspection in browser or tinker |
| `php artisan tinker` | Test hypotheses against live data in isolation |
| `DB::listen()` | See the actual SQL queries being executed |
| `->toSql()` / `->getBindings()` | Inspect Eloquent query builder output before execution |

**Multi-layer instrumentation example** (temporary — remove after diagnosis):

```php
Log::debug('Controller hit', ['input' => $request->validated()]);
Log::debug('Service called', ['args' => compact('name', 'userId')]);
Log::debug('Query result', ['count' => $query->count()]);
```

### Common Laravel bug patterns

These are the usual suspects. Check for them early — they explain most Laravel bugs:

- **N+1 queries** — Missing `with()` eager loading. Symptoms: slow pages, hundreds of queries.
- **Mass assignment** — Unfillable attributes silently ignored. Symptoms: model saves but fields are null.
- **Middleware order** — Auth/rate-limit/CORS applied in wrong sequence. Symptoms: 403s, missing headers.
- **Queue serialization** — Model state is stale when job executes. Symptoms: job works locally, fails in production.
- **Event listeners** — Side effects fire in unexpected order. Symptoms: data inconsistency after save.
- **Route model binding** — Wrong model resolved or implicit binding fails. Symptoms: 404 on valid IDs.
- **Config/env caching** — `config:cache` makes `.env` changes invisible. Symptoms: works locally, fails in production.

### Phase 4: Fix

1. Confirm your diagnosis explains ALL symptoms, not just the one you noticed.
2. Write the fix at the root cause. If the bug is in validation, fix validation — don't add a try/catch around it.
3. Run the reproduction test — it should pass.
4. Run the full test suite — nothing else should break.
5. Remove all temporary instrumentation (Log, dd, dump).

## Rationalizations to resist

| Rationalization | Why it's wrong |
|---|---|
| "This quick fix will unblock us" | A quick fix that doesn't address the root cause will break again. Find the cause first. |
| "I'll come back to fix it properly" | No you won't. Fix it now or it stays broken forever. |
| "The root cause is in a different system" | Then trace it there. Bugs don't respect module boundaries. |
| "I can't reproduce it" | Then you don't understand it yet. Check environment differences, timing, data state. |
| "Adding a null check should handle it" | A null check hides the bug. Why is the value null? That's the real question. |
| "The fix is obvious, I don't need to investigate" | Obvious fixes for wrong diagnoses create new bugs. Investigate first. |

## Checklist before declaring fixed

1. Root cause identified and documented in the commit message.
2. Reproduction test exists and passes.
3. Fix is at the root cause, not a symptom.
4. Full test suite passes.
5. All temporary instrumentation removed.
6. `./vendor/bin/pint --test` passes.
