# Laravel Claudify

Configure [Claude Code](https://claude.ai/download) for Laravel projects with a single command.

Claudify detects your project's stack and automatically sets up permissions, hooks, skills, plugins, and optionally installs [Laravel Boost](https://github.com/laravel/boost) - so you don't have to configure Claude Code manually for every Laravel app.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- [Claude Code CLI](https://claude.ai/download) installed

## Installation

```bash
composer require jorgecortesdev/laravel-claudify --dev
```

## Usage

```bash
php artisan claudify:install
```

That's it. Claudify reads your `composer.json` and `package.json`, detects what's installed, and configures Claude Code accordingly.

### Options

| Flag | Description |
|---|---|
| `--refresh` | Re-detect stack and update configuration without confirmation prompts |
| `--dry-run` | Preview what would be configured without writing any files |

### What it does

**1. Checks for Claude Code CLI**

Fails early with an error if `claude` is not installed.

**2. Detects your stack**

Reads `composer.json` and `package.json` to detect:

- Pest, Pint, Filament, Inertia, Livewire
- Boost, MCP, Sanctum, Horizon, Telescope
- Node, Prettier, ESLint

**3. Offers to install Laravel Boost**

If [laravel/boost](https://github.com/laravel/boost) is not installed and your Laravel version supports it (11.45.3+, 12.41.1+, or 13+), Claudify will ask if you'd like to install it. Boost provides MCP tools, guidelines, and skills for Claude Code.

**4. Installs hooks**

Copies auto-format scripts to `.claude/hooks/` based on detected formatters:

| Hook | Condition | What it does |
|---|---|---|
| `pint-format.sh` | Pint detected | Runs Pint on `.php` files after edits |
| `prettier-format.sh` | Prettier detected | Runs Prettier on non-PHP files after edits |

**5. Writes `.claude/settings.json`**

Generates permissions and deny rules based on your stack:

```json
{
    "permissions": {
        "allow": [
            "Bash(php:*)",
            "Bash(php artisan:*)",
            "Bash(composer:*)",
            "Bash(vendor/bin/pest:*)",
            "Bash(vendor/bin/pint:*)"
        ],
        "deny": [
            "Edit(.env*)",
            "Write(.env*)"
        ]
    },
    "hooks": {
        "PostToolUse": [
            {
                "matcher": "Edit|Write",
                "hooks": [
                    {"type": "command", "command": ".claude/hooks/pint-format.sh"}
                ]
            }
        ]
    }
}
```

Permissions are conditional. Pest, Pint, npm, and npx permissions only appear if those tools are detected. The `.env` deny rules are always included.

If `.claude/settings.json` already exists, Claudify merges new settings without overwriting your existing configuration.

**6. Installs skills**

Copies Laravel-specific skills to `.claude/skills/`:

| Skill | Description |
|---|---|
| `debugging-laravel` | Laravel debugging patterns: log inspection, reproduction tests, common bug patterns (N+1, mass assignment, middleware order) |
| `tdd-pest` | TDD patterns for Laravel with Pest: test organization, HTTP testing, fakes, database assertions |

Skills are tracked via a `.claudify-manifest.json` file. User-created skills in `.claude/skills/` are never touched.

**7. Installs plugins**

Installs Claude Code plugins per-project via the `claude` CLI:

| Plugin | Condition |
|---|---|
| `laravel-simplifier@laravel` | Always |
| `php-lsp@claude-plugins-official` | Always |
| `typescript-lsp@claude-plugins-official` | Node dependencies detected |

Already installed plugins (at any scope) are skipped.

## Re-running

Claudify is safe to re-run. It merges settings, updates skills, and skips already-installed plugins. Use `--refresh` to skip confirmation prompts:

```bash
php artisan claudify:install --refresh
```

This is useful after adding new packages (e.g., installing Pest or Prettier) to pick up new permissions and hooks.

## Testing

```bash
composer test
```

## Formatting

```bash
composer format
```

## License

MIT License. See [LICENSE](LICENSE) for details.
