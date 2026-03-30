<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

afterEach(function (): void {
    if (File::isDirectory(base_path('.claude'))) {
        File::deleteDirectory(base_path('.claude'));
    }

    if (File::exists(base_path('CLAUDE.md'))) {
        File::delete(base_path('CLAUDE.md'));
    }

    if (File::exists(base_path('composer.json'))) {
        File::delete(base_path('composer.json'));
    }
});

it('updates without requiring claude cli', function (): void {
    file_put_contents(base_path('composer.json'), json_encode(['require-dev' => ['laravel/boost' => '^2.0']]));

    $this->artisan('claudify:update')
        ->assertSuccessful();
});

it('writes CLAUDE.md with guidelines', function (): void {
    file_put_contents(base_path('composer.json'), json_encode(['require-dev' => ['laravel/boost' => '^2.0']]));

    $this->artisan('claudify:update')
        ->assertSuccessful();

    expect(base_path('CLAUDE.md'))->toBeFile();

    $content = File::get(base_path('CLAUDE.md'));

    expect($content)->toContain('<laravel-claudify>')
        ->and($content)->toContain('</laravel-claudify>')
        ->and($content)->toContain('pint --dirty');
});

it('writes settings and skills', function (): void {
    file_put_contents(base_path('composer.json'), json_encode(['require-dev' => ['laravel/boost' => '^2.0']]));

    $this->artisan('claudify:update')
        ->assertSuccessful();

    expect(base_path('.claude/settings.json'))->toBeFile()
        ->and(base_path('.claude/skills/laravel-debugging/SKILL.md'))->toBeFile();
});

it('does not prompt for boost installation', function (): void {
    file_put_contents(base_path('composer.json'), json_encode([]));

    // No boost in composer.json, but update should NOT prompt — it skips boost entirely
    $this->artisan('claudify:update')
        ->assertSuccessful()
        ->doesntExpectOutput('Install laravel/boost?');
});

it('does not install plugins', function (): void {
    file_put_contents(base_path('composer.json'), json_encode(['require-dev' => ['laravel/boost' => '^2.0']]));

    $this->artisan('claudify:update')
        ->assertSuccessful()
        ->doesntExpectOutput('laravel-simplifier');
});

it('preserves existing CLAUDE.md content', function (): void {
    file_put_contents(base_path('composer.json'), json_encode(['require-dev' => ['laravel/boost' => '^2.0']]));
    file_put_contents(base_path('CLAUDE.md'), "# My Project Rules\n\nCustom rules here.\n");

    $this->artisan('claudify:update')
        ->assertSuccessful();

    $content = File::get(base_path('CLAUDE.md'));

    expect($content)->toContain('# My Project Rules')
        ->and($content)->toContain('Custom rules here.')
        ->and($content)->toContain('<laravel-claudify>');
});
