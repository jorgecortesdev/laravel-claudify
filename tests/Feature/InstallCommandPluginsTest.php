<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

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

it('installs laravel-simplifier and php-lsp when not already installed', function (): void {
    file_put_contents(base_path('composer.json'), json_encode(['require-dev' => ['laravel/boost' => '^2.0']]));

    Process::fake([
        'which claude' => Process::result(),
        'claude plugin list --json' => Process::result(output: '[]'),
        'claude plugin install *' => Process::result(),
    ]);

    $this->artisan('claudify:install', [])
        ->assertSuccessful();

    Process::assertRan('claude plugin install laravel-simplifier@laravel --scope project');
    Process::assertRan('claude plugin install php-lsp@claude-plugins-official --scope project');
});

it('skips already installed plugins', function (): void {
    file_put_contents(base_path('composer.json'), json_encode(['require-dev' => ['laravel/boost' => '^2.0']]));

    $installedPlugins = json_encode([
        ['id' => 'laravel-simplifier@laravel', 'scope' => 'user', 'enabled' => true],
        ['id' => 'php-lsp@claude-plugins-official', 'scope' => 'user', 'enabled' => true],
    ]);

    Process::fake([
        'which claude' => Process::result(),
        'claude plugin list --json' => Process::result(output: $installedPlugins),
        'claude plugin install *' => Process::result(),
    ]);

    $this->artisan('claudify:install', [])
        ->assertSuccessful()
        ->expectsOutputToContain('already installed');

    Process::assertNotRan('claude plugin install laravel-simplifier@laravel *');
    Process::assertNotRan('claude plugin install php-lsp@claude-plugins-official *');
});

it('installs typescript-lsp when node dependencies detected', function (): void {
    file_put_contents(base_path('composer.json'), json_encode(['require-dev' => ['laravel/boost' => '^2.0']]));
    file_put_contents(base_path('package.json'), json_encode(['devDependencies' => []]));

    Process::fake([
        'which claude' => Process::result(),
        'claude plugin list --json' => Process::result(output: '[]'),
        'claude plugin install *' => Process::result(),
    ]);

    $this->artisan('claudify:install', [])
        ->assertSuccessful();

    Process::assertRan('claude plugin install typescript-lsp@claude-plugins-official --scope project');
});

it('does not install typescript-lsp when no node dependencies', function (): void {
    file_put_contents(base_path('composer.json'), json_encode(['require-dev' => ['laravel/boost' => '^2.0']]));

    Process::fake([
        'which claude' => Process::result(),
        'claude plugin list --json' => Process::result(output: '[]'),
        'claude plugin install *' => Process::result(),
    ]);

    $this->artisan('claudify:install', [])
        ->assertSuccessful();

    Process::assertNotRan('claude plugin install typescript-lsp*');
});

it('fails when claude cli is not available', function (): void {
    file_put_contents(base_path('composer.json'), json_encode(['require-dev' => ['laravel/boost' => '^2.0']]));

    Process::fake([
        'which claude' => Process::result(exitCode: 1),
    ]);

    $this->artisan('claudify:install', [])
        ->assertFailed()
        ->expectsOutputToContain('Claude Code CLI not found');
});
