<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    Process::fake([
        'which claude' => Process::result(),
        'claude plugin *' => Process::result(output: '[]'),
    ]);
});

afterEach(function (): void {
    if (File::isDirectory(base_path('.claude'))) {
        File::deleteDirectory(base_path('.claude'));
    }

    if (File::exists(base_path('composer.json'))) {
        File::delete(base_path('composer.json'));
    }

    if (File::exists(base_path('package.json'))) {
        File::delete(base_path('package.json'));
    }
});

it('runs successfully', function (): void {
    file_put_contents(base_path('composer.json'), json_encode(['require-dev' => ['laravel/boost' => '^2.0']]));

    $this->artisan('claudify:install', [])
        ->assertSuccessful();
});

it('creates settings file', function (): void {
    file_put_contents(base_path('composer.json'), json_encode(['require-dev' => ['laravel/boost' => '^2.0']]));

    $this->artisan('claudify:install', [])
        ->assertSuccessful();

    expect(base_path('.claude/settings.json'))->toBeFile();
});

it('includes base permissions', function (): void {
    file_put_contents(base_path('composer.json'), json_encode(['require-dev' => ['laravel/boost' => '^2.0']]));

    $this->artisan('claudify:install', [])
        ->assertSuccessful();

    $settings = json_decode(File::get(base_path('.claude/settings.json')), true);

    expect($settings['permissions']['allow'])
        ->toContain('Bash(php:*)')
        ->toContain('Bash(php artisan:*)')
        ->toContain('Bash(composer:*)');
});

it('includes pest permission when pest is detected', function (): void {
    file_put_contents(base_path('composer.json'), json_encode([
        'require-dev' => ['pestphp/pest' => '^3.0', 'laravel/boost' => '^2.0'],
    ]));

    $this->artisan('claudify:install', [])
        ->assertSuccessful();

    $settings = json_decode(File::get(base_path('.claude/settings.json')), true);

    expect($settings['permissions']['allow'])
        ->toContain('Bash(vendor/bin/pest:*)');
});

it('includes node permissions when package.json exists', function (): void {
    file_put_contents(base_path('composer.json'), json_encode(['require-dev' => ['laravel/boost' => '^2.0']]));
    file_put_contents(base_path('package.json'), json_encode(['devDependencies' => []]));

    $this->artisan('claudify:install', [])
        ->assertSuccessful();

    $settings = json_decode(File::get(base_path('.claude/settings.json')), true);

    expect($settings['permissions']['allow'])
        ->toContain('Bash(npm:*)')
        ->toContain('Bash(npx:*)');
});

it('does not include node permissions when package.json is missing', function (): void {
    file_put_contents(base_path('composer.json'), json_encode([
        'require' => ['laravel/framework' => '^12.0'],
        'require-dev' => ['laravel/boost' => '^2.0'],
    ]));

    $this->artisan('claudify:install', [])
        ->assertSuccessful();

    $settings = json_decode(File::get(base_path('.claude/settings.json')), true);

    expect($settings['permissions']['allow'])
        ->not->toContain('Bash(npm:*)')
        ->not->toContain('Bash(npx:*)');
});

it('installs skills', function (): void {
    file_put_contents(base_path('composer.json'), json_encode(['require-dev' => ['laravel/boost' => '^2.0']]));

    $this->artisan('claudify:install', [])
        ->assertSuccessful();

    expect(base_path('.claude/skills/laravel-tdd-pest/SKILL.md'))->toBeFile()
        ->and(base_path('.claude/skills/laravel-debugging/SKILL.md'))->toBeFile();
});

it('merges settings on repeated install', function (): void {
    file_put_contents(base_path('composer.json'), json_encode(['require-dev' => ['laravel/boost' => '^2.0']]));

    File::ensureDirectoryExists(base_path('.claude'));
    File::put(base_path('.claude/settings.json'), json_encode([
        'custom' => 'value',
        'permissions' => ['allow' => ['Bash(custom:*)']],
    ]));

    $this->artisan('claudify:install', [])
        ->assertSuccessful();

    $settings = json_decode(File::get(base_path('.claude/settings.json')), true);

    expect($settings['custom'])->toBe('value')
        ->and($settings['permissions']['allow'])->toContain('Bash(custom:*)')
        ->and($settings['permissions']['allow'])->toContain('Bash(php:*)');
});

it('dry run does not write files', function (): void {
    file_put_contents(base_path('composer.json'), json_encode(['require-dev' => ['laravel/boost' => '^2.0']]));

    $this->artisan('claudify:install', ['--dry-run' => true])
        ->assertSuccessful();

    expect(base_path('.claude/settings.json'))->not->toBeFile();
});

it('dry run shows settings preview', function (): void {
    file_put_contents(base_path('composer.json'), json_encode(['require-dev' => ['laravel/boost' => '^2.0']]));

    $this->artisan('claudify:install', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('.claude/settings.json')
        ->expectsOutputToContain('permissions');
});

it('dry run shows skills preview', function (): void {
    file_put_contents(base_path('composer.json'), json_encode(['require-dev' => ['laravel/boost' => '^2.0']]));

    $this->artisan('claudify:install', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('.claude/skills/')
        ->expectsOutputToContain('laravel-tdd-pest');
});
