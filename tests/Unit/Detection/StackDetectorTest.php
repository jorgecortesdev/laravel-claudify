<?php

declare(strict_types=1);

use JorgeCortesDev\Claudify\Detection\StackDetector;

afterEach(function (): void {
    if (file_exists(base_path('composer.json'))) {
        unlink(base_path('composer.json'));
    }

    if (file_exists(base_path('package.json'))) {
        unlink(base_path('package.json'));
    }
});

function writeComposerJson(array $data): void
{
    file_put_contents(base_path('composer.json'), json_encode($data));
}

function writePackageJson(array $data): void
{
    file_put_contents(base_path('package.json'), json_encode($data));
}

it('detects pest in require-dev', function (): void {
    writeComposerJson(['require-dev' => ['pestphp/pest' => '^3.0']]);

    $detector = new StackDetector;

    expect($detector->hasPest())->toBeTrue();
});

it('detects filament in require', function (): void {
    writeComposerJson(['require' => ['filament/filament' => '^3.0']]);

    $detector = new StackDetector;

    expect($detector->hasFilament())->toBeTrue();
});

it('detects inertia in require', function (): void {
    writeComposerJson(['require' => ['inertiajs/inertia-laravel' => '^2.0']]);

    $detector = new StackDetector;

    expect($detector->hasInertia())->toBeTrue();
});

it('detects livewire in require', function (): void {
    writeComposerJson(['require' => ['livewire/livewire' => '^3.0']]);

    $detector = new StackDetector;

    expect($detector->hasLivewire())->toBeTrue();
});

it('detects boost in require-dev', function (): void {
    writeComposerJson(['require-dev' => ['laravel/boost' => '^1.0']]);

    $detector = new StackDetector;

    expect($detector->hasBoost())->toBeTrue();
});

it('detects pint in require-dev', function (): void {
    writeComposerJson(['require-dev' => ['laravel/pint' => '^1.0']]);

    $detector = new StackDetector;

    expect($detector->hasPint())->toBeTrue();
});

it('detects mcp in require', function (): void {
    writeComposerJson(['require' => ['laravel/mcp' => '^1.0']]);

    $detector = new StackDetector;

    expect($detector->hasMcp())->toBeTrue();
});

it('detects sanctum in require', function (): void {
    writeComposerJson(['require' => ['laravel/sanctum' => '^4.0']]);

    $detector = new StackDetector;

    expect($detector->hasSanctum())->toBeTrue();
});

it('detects horizon in require', function (): void {
    writeComposerJson(['require' => ['laravel/horizon' => '^5.0']]);

    $detector = new StackDetector;

    expect($detector->hasHorizon())->toBeTrue();
});

it('detects telescope in require-dev', function (): void {
    writeComposerJson(['require-dev' => ['laravel/telescope' => '^5.0']]);

    $detector = new StackDetector;

    expect($detector->hasTelescope())->toBeTrue();
});

it('detects node dependencies when package.json exists', function (): void {
    writePackageJson(['devDependencies' => []]);

    $detector = new StackDetector;

    expect($detector->hasNodeDependencies())->toBeTrue();
});

it('does not detect node dependencies when package.json is missing', function (): void {
    writeComposerJson(['require' => []]);

    $detector = new StackDetector;

    expect($detector->hasNodeDependencies())->toBeFalse();
});

it('detects prettier in package.json devDependencies', function (): void {
    writeComposerJson([]);
    writePackageJson(['devDependencies' => ['prettier' => '^3.0']]);

    $detector = new StackDetector;

    expect($detector->hasPrettier())->toBeTrue();
});

it('detects eslint in package.json devDependencies', function (): void {
    writeComposerJson([]);
    writePackageJson(['devDependencies' => ['eslint' => '^9.0']]);

    $detector = new StackDetector;

    expect($detector->hasEslint())->toBeTrue();
});

it('does not detect packages that are absent', function (): void {
    writeComposerJson([
        'require' => ['laravel/framework' => '^12.0'],
    ]);

    $detector = new StackDetector;

    expect($detector->hasPest())->toBeFalse()
        ->and($detector->hasFilament())->toBeFalse()
        ->and($detector->hasInertia())->toBeFalse()
        ->and($detector->hasLivewire())->toBeFalse()
        ->and($detector->hasBoost())->toBeFalse()
        ->and($detector->hasPint())->toBeFalse()
        ->and($detector->hasMcp())->toBeFalse()
        ->and($detector->hasSanctum())->toBeFalse()
        ->and($detector->hasHorizon())->toBeFalse()
        ->and($detector->hasTelescope())->toBeFalse()
        ->and($detector->hasPrettier())->toBeFalse()
        ->and($detector->hasEslint())->toBeFalse();
});

it('returns only detected packages from detected()', function (): void {
    writeComposerJson([
        'require' => ['livewire/livewire' => '^3.0'],
        'require-dev' => ['pestphp/pest' => '^3.0', 'laravel/pint' => '^1.0'],
    ]);

    $detector = new StackDetector;
    $detected = $detector->detected();

    expect($detected->keys()->toArray())
        ->toContain('pest', 'livewire', 'pint')
        ->not->toContain('filament', 'inertia', 'boost');
});

it('returns all detection keys from detect()', function (): void {
    writeComposerJson([]);

    $detector = new StackDetector;
    $all = $detector->detect();

    expect($all)->toHaveCount(13)
        ->and($all->keys()->toArray())->toBe([
            'pest', 'filament', 'inertia', 'livewire', 'boost',
            'pint', 'mcp', 'sanctum', 'horizon', 'telescope',
            'node', 'prettier', 'eslint',
        ]);
});

it('does not confuse require with require-dev', function (): void {
    writeComposerJson([
        'require' => ['pestphp/pest' => '^3.0'],
        'require-dev' => ['laravel/sanctum' => '^4.0'],
    ]);

    $detector = new StackDetector;

    // pest is require-dev only, sanctum is require only
    expect($detector->hasPest())->toBeFalse()
        ->and($detector->hasSanctum())->toBeFalse();
});

it('handles missing composer.json gracefully', function (): void {
    // No composer.json or package.json written
    $detector = new StackDetector;

    expect($detector->detected()->isEmpty())->toBeTrue();
});
