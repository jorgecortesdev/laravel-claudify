# Claudify — Laravel Workflow Rules

## Formatting

- Run `vendor/bin/pint --dirty --format agent` before declaring work done to auto-fix changed files.
- If pint fixes code you didn't write, make it a separate commit from your functional change.

## Testing

- Use Pest for new test files. When adding tests to an existing PHPUnit file, use PHPUnit syntax in that file. Don't convert existing files unless explicitly asked.
- Use `php artisan` conventions for commands.

## Debugging → TDD Handoff

When debugging produces a reproduction test, that test IS the TDD red phase. Once you have a failing reproduction test, switch to the TDD cycle for the fix: GREEN (minimum code to pass), REFACTOR (clean up under green tests). Don't restart the debugging process — the diagnosis is done, now fix it.
