# Laravel Claudify

Configure [Claude Code](https://claude.ai/download) for Laravel projects with a single command.

Claudify detects your stack from `composer.json` and `package.json`, then sets up permissions, hooks, skills, and plugins — so you don't configure Claude Code manually for every Laravel app.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- [Claude Code CLI](https://claude.ai/download)

## Installation

```bash
composer require jorgecortesdev/laravel-claudify --dev
```

Then run the install command:

```bash
php artisan claudify:install
```

## What it does

The install command runs these steps in order:

### 1. Checks for Claude Code CLI

Exits with an error if `claude` is not in your PATH.

### 2. Detects your stack

Reads `composer.json` and `package.json` to detect:

- **Pest, Pint, Boost** (from `require-dev`)
- **Node, Prettier, ESLint** (from `package.json`)

### 3. Offers to install Laravel Boost

If [laravel/boost](https://github.com/laravel/boost) is not installed and your Laravel version supports it (11.45.3+, 12.41.1+, or 13+), you'll be asked whether to install it. Boost provides MCP tools, guidelines, and skills for Claude Code.

### 4. Installs hooks

Copies auto-format scripts to `.claude/hooks/` based on detected formatters:

| Hook | Condition | What it does |
|---|---|---|
| `pint-format.sh` | Pint detected | Runs Pint on `.php` files after edits |
| `prettier-format.sh` | Prettier detected | Runs Prettier on non-PHP files after edits |

### 5. Writes `.claude/settings.json`

Generates permissions and deny rules based on your stack:

**Base permissions** (always included):
- `Bash(php:*)`, `Bash(php artisan:*)`, `Bash(composer:*)`

**Conditional permissions** (when detected):

| Condition | Permissions added |
|---|---|
| Pest | `Bash(vendor/bin/pest:*)` |
| Pint | `Bash(vendor/bin/pint:*)`, `Bash(vendor/bin/pint --dirty:*)` |
| Node | `Bash(npm:*)`, `Bash(npx:*)` |
| Boost | All 9 Boost MCP tools: `search-docs`, `application-info`, `database-query`, `database-schema`, `database-connections`, `read-log-entries`, `browser-logs`, `get-absolute-url`, `last-error` |

**Deny rules** (always included):
- `Edit(.env*)`, `Write(.env*)`

**Hooks** (when formatters detected):
- PostToolUse on Edit/Write: runs Pint on `.php` files, Prettier on non-PHP files

If `.claude/settings.json` already exists, new settings are merged without overwriting your existing configuration. Duplicate permissions are deduplicated automatically.

### 6. Installs skills

Copies Laravel-specific skills to `.claude/skills/`:

| Skill | What it provides |
|---|---|
| `laravel-debugging` | Root-cause debugging: log inspection, reproduction tests, common bug patterns (N+1, mass assignment, middleware order, queue serialization) |
| `laravel-tdd-pest` | TDD with Pest: red-green-refactor cycle, HTTP testing, fakes, factories, database assertions, Sanctum/Gate/Inertia patterns |
| `laravel-conventions` | Coding standards: strict types, type hints, readonly DTOs, enums, naming conventions, file organization |
| `laravel-security` | Security patterns: input validation, Sanctum auth, policies, SQL injection, XSS, CSRF, rate limiting, file uploads |
| `laravel-architecture` | Application structure: services vs actions, when to extract, repositories (usually not), events vs observers, jobs, scaling patterns |

Skills are tracked via a `.claudify-manifest.json` file so user-created skills in `.claude/skills/` are never touched.

### 7. Installs plugins

Installs Claude Code plugins per-project using the `claude` CLI:

| Plugin | Condition |
|---|---|
| `laravel-simplifier@laravel` | Always |
| `php-lsp@claude-plugins-official` | Always |
| `typescript-lsp@claude-plugins-official` | Node dependencies detected |

Plugins already installed at any scope (user, project, or local) are skipped.

## Dry run

Preview what would be configured without writing files:

```bash
php artisan claudify:install --dry-run
```

This shows the settings JSON, hook scripts, and skills that would be installed. It does not preview Boost installation or plugin installation since those require network access.

## Re-running

Safe to re-run at any time. Settings are merged (not overwritten), skills are updated, and plugins already installed are skipped. Run it again after adding packages like Pest or Prettier to pick up new permissions and hooks.

## Testing

```bash
composer test
```

## Formatting

```bash
composer format
```

## Acknowledgements

This package was inspired by [Laravel Boost](https://github.com/laravel/boost) and its approach to configuring AI tools for Laravel projects. Some of the bundled skills were informed by community-shared Claude Code skills found across GitHub, rewritten and adapted for consistency and correctness.

## License

MIT License. See [LICENSE](LICENSE) for details.
