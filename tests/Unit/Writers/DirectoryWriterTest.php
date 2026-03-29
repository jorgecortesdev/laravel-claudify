<?php

declare(strict_types=1);

use JorgeCortesDev\Claudify\Enums\WriteResult;
use JorgeCortesDev\Claudify\Writers\DirectoryWriter;

function createTempDir(): string
{
    $path = sys_get_temp_dir().'/claudify-test-'.uniqid();
    mkdir($path, 0755, true);

    return $path;
}

function cleanupDirectory(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }

    @rmdir($path);
}

it('lists available entries from source directory', function (): void {
    $source = fixturePath('skills');

    $writer = new DirectoryWriter($source, sys_get_temp_dir());

    expect($writer->available())
        ->toContain('test-skill')
        ->toContain('nested-skill');
});

it('returns empty array when source directory does not exist', function (): void {
    $writer = new DirectoryWriter('/nonexistent/'.uniqid(), sys_get_temp_dir());

    expect($writer->available())->toBe([]);
});

it('syncs all entries from source to target', function (): void {
    $source = fixturePath('skills');
    $target = createTempDir();

    $writer = new DirectoryWriter($source, $target);
    $results = $writer->sync();

    expect($results)->toHaveCount(2)
        ->and($results['test-skill'])->toBe(WriteResult::SUCCESS)
        ->and($results['nested-skill'])->toBe(WriteResult::SUCCESS)
        ->and($target.'/test-skill/SKILL.md')->toBeFile()
        ->and($target.'/nested-skill/SKILL.md')->toBeFile();

    cleanupDirectory($target);
});

it('copies nested directory structure', function (): void {
    $source = fixturePath('skills');
    $target = createTempDir();

    $writer = new DirectoryWriter($source, $target);
    $writer->sync();

    expect($target.'/nested-skill/references/ref.md')->toBeFile()
        ->and($target.'/nested-skill/references/deep/nested/file.md')->toBeFile();

    cleanupDirectory($target);
});

it('returns UPDATED on second sync', function (): void {
    $source = fixturePath('skills');
    $target = createTempDir();

    $writer = new DirectoryWriter($source, $target);

    $first = $writer->sync();
    $second = $writer->sync();

    expect($first['test-skill'])->toBe(WriteResult::SUCCESS)
        ->and($second['test-skill'])->toBe(WriteResult::UPDATED);

    cleanupDirectory($target);
});

it('removes stale entries tracked in manifest', function (): void {
    $source = fixturePath('skills');
    $target = createTempDir();

    $staleDir = $target.'/stale-skill';
    mkdir($staleDir, 0755, true);
    file_put_contents($staleDir.'/SKILL.md', 'stale content');
    file_put_contents($target.'/.claudify-manifest.json', json_encode(['stale-skill']));

    $writer = new DirectoryWriter($source, $target);
    $writer->sync();

    expect($staleDir)->not->toBeDirectory();

    cleanupDirectory($target);
});

it('preserves untracked entries during sync', function (): void {
    $source = fixturePath('skills');
    $target = createTempDir();

    $userDir = $target.'/my-custom-skill';
    mkdir($userDir, 0755, true);
    file_put_contents($userDir.'/SKILL.md', 'user content');

    $writer = new DirectoryWriter($source, $target);
    $writer->sync();

    expect($userDir)->toBeDirectory()
        ->and(file_get_contents($userDir.'/SKILL.md'))->toBe('user content');

    cleanupDirectory($target);
});

it('writes manifest after sync', function (): void {
    $source = fixturePath('skills');
    $target = createTempDir();

    $writer = new DirectoryWriter($source, $target);
    $writer->sync();

    $manifest = json_decode(file_get_contents($target.'/.claudify-manifest.json'), true);

    expect($manifest)->toContain('nested-skill')
        ->toContain('test-skill');

    cleanupDirectory($target);
});

it('removes deeply nested directories during stale cleanup', function (): void {
    $source = fixturePath('skills');
    $target = createTempDir();

    $deepDir = $target.'/old-skill/references/deep/nested';
    mkdir($deepDir, 0755, true);
    file_put_contents($target.'/old-skill/SKILL.md', 'test');
    file_put_contents($deepDir.'/file.md', 'nested');
    file_put_contents($target.'/.claudify-manifest.json', json_encode(['old-skill']));

    $writer = new DirectoryWriter($source, $target);
    $writer->sync();

    expect($target.'/old-skill')->not->toBeDirectory();

    cleanupDirectory($target);
});

it('handles empty source directory', function (): void {
    $source = createTempDir();
    $target = createTempDir();

    $writer = new DirectoryWriter($source, $target);
    $results = $writer->sync();

    expect($results)->toBe([]);

    cleanupDirectory($source);
    cleanupDirectory($target);
});
