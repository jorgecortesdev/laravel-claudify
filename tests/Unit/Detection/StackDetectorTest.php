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
        ->and($detector->hasBoost())->toBeFalse()
        ->and($detector->hasPint())->toBeFalse()
        ->and($detector->hasPrettier())->toBeFalse()
        ->and($detector->hasEslint())->toBeFalse();
});

it('returns only detected packages from detected()', function (): void {
    writeComposerJson([
        'require-dev' => ['pestphp/pest' => '^3.0', 'laravel/pint' => '^1.0'],
    ]);

    $detector = new StackDetector;
    $detected = $detector->detected();

    expect($detected->keys()->toArray())
        ->toContain('pest', 'pint')
        ->not->toContain('boost', 'node', 'prettier', 'eslint');
});

it('returns all detection keys from all()', function (): void {
    writeComposerJson([]);

    $detector = new StackDetector;
    $all = $detector->all();

    expect($all)->toHaveCount(6)
        ->and($all->keys()->toArray())->toBe([
            'pest', 'boost', 'pint', 'node', 'prettier', 'eslint',
        ]);
});

it('does not confuse require with require-dev', function (): void {
    writeComposerJson([
        'require' => ['pestphp/pest' => '^3.0'],
    ]);

    $detector = new StackDetector;

    expect($detector->hasPest())->toBeFalse();
});

it('handles missing composer.json gracefully', function (): void {
    $detector = new StackDetector;

    expect($detector->detected()->isEmpty())->toBeTrue();
});
