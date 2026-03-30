---
name: laravel-testing-browser
description: >-
  Browser testing for Laravel with Pest 4. Use this skill when writing browser tests,
  end-to-end tests, smoke tests, or when the user mentions "browser test", "e2e test",
  "smoke test", "test in the browser", "test the UI", "test the frontend", "visual
  regression", "accessibility test", or "test on mobile". Covers Pest 4's visit() API,
  interaction methods, assertions, multi-browser and device testing, screenshot debugging,
  and Laravel integration (factories, fakes, auth).
---

# Browser Testing with Pest 4

Pest 4 runs real browser tests using Playwright. These tests interact with your application the way a user does — clicking, typing, navigating — and verify what the browser actually renders.

Browser tests live in `tests/Browser/`. They're separate from feature tests because they're slower, require a running app, and test different things (rendered UI vs HTTP responses).

## When to use browser tests

- **Forms with JavaScript validation** — feature tests can't see client-side behavior
- **Multi-step flows** — login → navigate → submit → verify
- **JavaScript-dependent UI** — modals, dropdowns, dynamic content
- **Smoke testing** — verify pages load without errors across the site
- **Accessibility checks** — WCAG compliance on rendered pages

Don't use browser tests for logic that feature tests already cover. A controller that returns JSON doesn't need a browser.

## Setup

```bash
composer require pestphp/pest-plugin-browser --dev
npm install playwright@latest
npx playwright install
```

Add to `.gitignore`:
```
tests/Browser/Screenshots
```

## Core API

### visit() — navigate to a page

```php
$page = visit('/');
$page = visit('/dashboard');
```

Visit multiple pages at once for smoke testing:

```php
$pages = visit(['/', '/about', '/contact', '/pricing']);
```

### Interactions

Chain interactions naturally — they read like user actions:

```php
$page->click('Sign In')
    ->fill('email', 'user@example.com')
    ->fill('password', 'secret')
    ->click('Submit')
    ->assertSee('Dashboard');
```

| Method | What it does |
|---|---|
| `click('text or selector')` | Click a link, button, or element |
| `fill('field', 'value')` | Fill an input field |
| `type('field', 'value')` | Type into a field (triggers key events) |
| `typeSlowly('field', 'value')` | Human-like typing speed |
| `select('field', 'value')` | Choose a dropdown option |
| `check('field')` / `uncheck('field')` | Toggle checkboxes |
| `radio('field', 'value')` | Select a radio button |
| `press('button text')` | Click a submit button |
| `attach('field', '/path/to/file')` | Upload a file |
| `hover('selector')` | Trigger hover state |
| `drag('from', 'to')` | Drag and drop |
| `submit('form selector')` | Submit a form |

### Element targeting

```php
$page->click('Login');              // by visible text
$page->click('.btn-primary');       // by CSS class
$page->click('#submit');            // by ID
$page->click('@login-button');      // by data-test attribute
```

The `@` prefix targets `data-test` attributes — use these for stable selectors that survive CSS refactors.

### Assertions

**Content:**

```php
$page->assertSee('Welcome back')
    ->assertDontSee('Error')
    ->assertSeeIn('.alert', 'Success')
    ->assertVisible('.modal')
    ->assertMissing('.loading-spinner');
```

**URL and navigation:**

```php
$page->assertUrlIs('/dashboard')
    ->assertPathIs('/dashboard')
    ->assertPathBeginsWith('/admin')
    ->assertQueryStringHas('page', '2');
```

**Forms:**

```php
$page->assertValue('input[name=email]', 'user@example.com')
    ->assertChecked('remember_me')
    ->assertSelected('country', 'US')
    ->assertEnabled('submit')
    ->assertDisabled('delete');
```

**Quality — these catch real bugs:**

```php
$page->assertNoJavaScriptErrors()   // no uncaught exceptions
    ->assertNoConsoleLogs()          // no console.log/warn/error
    ->assertNoAccessibilityIssues(); // WCAG compliance
```

## Common patterns

### Smoke testing

Verify every page loads without JavaScript errors or console noise:

```php
it('loads all public pages without errors', function () {
    $pages = visit(['/', '/about', '/contact', '/pricing']);

    $pages->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
```

### Authentication flow

```php
it('allows users to sign in', function () {
    $user = User::factory()->create();

    $page = visit('/login');

    $page->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Sign In')
        ->assertPathIs('/dashboard')
        ->assertSee($user->name);
});
```

### Form submission with validation errors

```php
it('shows validation errors on empty submit', function () {
    $page = visit('/register');

    $page->click('Create Account')
        ->assertSee('The name field is required')
        ->assertSee('The email field is required');
});
```

### Testing on mobile

```php
it('shows the mobile menu on small screens', function () {
    $page = visit('/')->on()->mobile();

    $page->assertMissing('.desktop-nav')
        ->assertVisible('.mobile-menu-button')
        ->click('.mobile-menu-button')
        ->assertVisible('.mobile-nav');
});

it('renders correctly on iPhone', function () {
    $page = visit('/')->on()->iPhone14Pro();

    $page->assertNoJavaScriptErrors();
});
```

### Dark mode

```php
it('renders correctly in dark mode', function () {
    $page = visit('/');

    $page->inDarkMode()
        ->assertNoJavaScriptErrors();
});
```

### Visual regression

```php
it('matches the expected screenshot', function () {
    $page = visit('/');

    $page->assertScreenshotMatches();
});
```

### Accessibility

```php
it('meets accessibility standards', function () {
    $pages = visit(['/', '/about', '/contact']);

    $pages->assertNoAccessibilityIssues();
});
```

### Using Laravel fakes in browser tests

```php
it('sends a notification when the form is submitted', function () {
    Notification::fake();

    $user = User::factory()->create();

    $page = visit('/contact');

    $page->fill('name', 'Jorge')
        ->fill('email', 'me@jorgecortes.dev')
        ->fill('message', 'Hello')
        ->click('Send')
        ->assertSee('Message sent');

    Notification::assertSent(ContactFormSubmitted::class);
});
```

## Debugging

```php
$page->screenshot();                    // capture current viewport
$page->screenshot(fullPage: true);      // capture entire page
$page->screenshotElement('.sidebar');    // capture one element
$page->debug();                         // pause and open browser
$page->tinker();                        // interactive PHP REPL
```

Run with visible browser:

```bash
vendor/bin/pest --headed
vendor/bin/pest --debug       # pause on failure
vendor/bin/pest --parallel    # run concurrently
```

## Multi-browser testing

```bash
vendor/bin/pest --browser firefox
vendor/bin/pest --browser safari
```

Or configure in `tests/Pest.php`:

```php
pest()->browser()->inFirefox();
```

## When NOT to use browser tests

- Testing JSON API responses — use feature tests
- Testing Eloquent queries or business logic — use unit tests
- Testing email content — use `Mail::fake()` in feature tests
- Testing that a route returns 200 — use `$this->get('/')->assertOk()`

Browser tests are for behavior that only exists in the browser. If a feature test can verify it, prefer the feature test — it's faster and more reliable.
