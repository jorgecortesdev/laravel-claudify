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
});

it('does not include hooks when no formatters detected', function (): void {
    file_put_contents(base_path('composer.json'), json_encode([
        'require' => ['laravel/framework' => '^12.0'],
        'require-dev' => ['laravel/boost' => '^2.0'],
    ]));

    $this->artisan('claudify:install', ['--refresh' => true])
        ->assertSuccessful();

    $settings = json_decode(File::get(base_path('.claude/settings.json')), true);

    expect($settings)->not->toHaveKey('hooks');
});

it('includes deny permissions for .env files', function (): void {
    file_put_contents(base_path('composer.json'), json_encode(['require-dev' => ['laravel/boost' => '^2.0']]));

    $this->artisan('claudify:install', ['--refresh' => true])
        ->assertSuccessful();

    $settings = json_decode(File::get(base_path('.claude/settings.json')), true);

    expect($settings['permissions']['deny'])
        ->toContain('Edit(.env*)')
        ->toContain('Write(.env*)');
});

it('copies pint hook script when pint is detected', function (): void {
    file_put_contents(base_path('composer.json'), json_encode([
        'require-dev' => ['laravel/pint' => '^1.0', 'laravel/boost' => '^2.0'],
    ]));

    $this->artisan('claudify:install', ['--refresh' => true])
        ->assertSuccessful();

    $hookPath = base_path('.claude/hooks/pint-format.sh');

    expect($hookPath)->toBeFile()
        ->and(is_executable($hookPath))->toBeTrue();
});

it('includes PostToolUse hook for pint when detected', function (): void {
    file_put_contents(base_path('composer.json'), json_encode([
        'require-dev' => ['laravel/pint' => '^1.0', 'laravel/boost' => '^2.0'],
    ]));

    $this->artisan('claudify:install', ['--refresh' => true])
        ->assertSuccessful();

    $settings = json_decode(File::get(base_path('.claude/settings.json')), true);

    expect($settings)->toHaveKey('hooks.PostToolUse')
        ->and($settings['hooks']['PostToolUse'][0]['matcher'])->toBe('Edit|Write')
        ->and($settings['hooks']['PostToolUse'][0]['hooks'][0]['command'])
        ->toBe('.claude/hooks/pint-format.sh');
});

it('dry run shows hooks when formatters detected', function (): void {
    file_put_contents(base_path('composer.json'), json_encode([
        'require-dev' => ['laravel/pint' => '^1.0', 'laravel/boost' => '^2.0'],
    ]));

    $this->artisan('claudify:install', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('PostToolUse')
        ->expectsOutputToContain('pint-format.sh');
});
