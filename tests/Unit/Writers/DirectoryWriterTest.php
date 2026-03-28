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

it('writes a skill directory to the target', function (): void {
    $source = fixturePath('skills');
    $target = createTempDir();

    $writer = new DirectoryWriter($source, $target);
    $result = $writer->write('test-skill');

    expect($result)->toBe(WriteResult::SUCCESS)
        ->and($target.'/test-skill/SKILL.md')->toBeFile();

    cleanupDirectory($target);
});

it('returns UPDATED when skill directory already exists', function (): void {
    $source = fixturePath('skills');
    $target = createTempDir();

    mkdir($target.'/test-skill', 0755, true);
    file_put_contents($target.'/test-skill/SKILL.md', 'old content');

    $writer = new DirectoryWriter($source, $target);
    $result = $writer->write('test-skill');

    $content = file_get_contents($target.'/test-skill/SKILL.md');

    expect($result)->toBe(WriteResult::UPDATED)
        ->and($content)->toContain('name: test-skill');

    cleanupDirectory($target);
});

it('returns FAILED when source directory does not exist', function (): void {
    $source = fixturePath('skills');
    $target = createTempDir();

    $writer = new DirectoryWriter($source, $target);
    $result = $writer->write('nonexistent-skill');

    expect($result)->toBe(WriteResult::FAILED);

    cleanupDirectory($target);
});

it('copies nested directory structure', function (): void {
    $source = fixturePath('skills');
    $target = createTempDir();

    $writer = new DirectoryWriter($source, $target);
    $result = $writer->write('nested-skill');

    expect($result)->toBe(WriteResult::SUCCESS)
        ->and($target.'/nested-skill/SKILL.md')->toBeFile()
        ->and($target.'/nested-skill/references/ref.md')->toBeFile()
        ->and($target.'/nested-skill/references/deep/nested/file.md')->toBeFile();

    cleanupDirectory($target);
});

it('throws exception for path traversal in skill name', function (string $maliciousName): void {
    $source = fixturePath('skills');
    $target = createTempDir();

    $writer = new DirectoryWriter($source, $target);

    expect(fn (): WriteResult => $writer->write($maliciousName))
        ->toThrow(RuntimeException::class, 'Invalid skill name');

    cleanupDirectory($target);
})->with([
    '../etc/passwd',
    '../../.bashrc',
    'skill/with/slash',
    'skill\\with\\backslash',
    '../parent',
]);

it('writes all skills from source directory', function (): void {
    $source = fixturePath('skills');
    $target = createTempDir();

    $writer = new DirectoryWriter($source, $target);
    $results = $writer->writeAll();

    expect($results)->toHaveCount(2)
        ->and($results['test-skill'])->toBe(WriteResult::SUCCESS)
        ->and($results['nested-skill'])->toBe(WriteResult::SUCCESS);

    cleanupDirectory($target);
});

it('lists available skills from source directory', function (): void {
    $source = fixturePath('skills');

    $writer = new DirectoryWriter($source, sys_get_temp_dir());
    $skills = $writer->available();

    expect($skills)->toContain('test-skill')
        ->and($skills)->toContain('nested-skill');
});

it('removes a skill directory', function (): void {
    $target = createTempDir();
    $skillDir = $target.'/test-skill';

    mkdir($skillDir, 0755, true);
    file_put_contents($skillDir.'/SKILL.md', 'test content');

    $writer = new DirectoryWriter(sys_get_temp_dir(), $target);
    $result = $writer->remove('test-skill');

    expect($result)->toBeTrue()
        ->and($skillDir)->not->toBeDirectory();

    cleanupDirectory($target);
});

it('returns true when removing non-existent skill', function (): void {
    $target = createTempDir();

    $writer = new DirectoryWriter(sys_get_temp_dir(), $target);
    $result = $writer->remove('nonexistent');

    expect($result)->toBeTrue();

    cleanupDirectory($target);
});

it('returns false when removing skill with invalid name', function (): void {
    $target = createTempDir();

    $writer = new DirectoryWriter(sys_get_temp_dir(), $target);

    expect($writer->remove('../malicious'))->toBeFalse()
        ->and($writer->remove('skill/with/slash'))->toBeFalse();

    cleanupDirectory($target);
});

it('removes multiple stale skills', function (): void {
    $target = createTempDir();

    foreach (['skill-one', 'skill-two', 'skill-three'] as $name) {
        mkdir($target.'/'.$name, 0755, true);
        file_put_contents($target.'/'.$name.'/SKILL.md', $name);
    }

    $writer = new DirectoryWriter(sys_get_temp_dir(), $target);
    $results = $writer->removeStale(['skill-one', 'skill-two']);

    expect($results)->toHaveCount(2)
        ->and($results['skill-one'])->toBeTrue()
        ->and($results['skill-two'])->toBeTrue()
        ->and($target.'/skill-one')->not->toBeDirectory()
        ->and($target.'/skill-two')->not->toBeDirectory()
        ->and($target.'/skill-three')->toBeDirectory();

    cleanupDirectory($target);
});

it('syncs skills by writing new and removing stale', function (): void {
    $source = fixturePath('skills');
    $target = createTempDir();

    $staleDir = $target.'/stale-skill';
    mkdir($staleDir, 0755, true);
    file_put_contents($staleDir.'/SKILL.md', 'stale content');

    // Write initial manifest with the stale skill tracked
    $manifestPath = $target.'/.claudify-manifest.json';
    file_put_contents($manifestPath, json_encode(['stale-skill']));

    $writer = new DirectoryWriter($source, $target);
    $results = $writer->sync();

    expect($results)->toHaveCount(2)
        ->and($results['test-skill'])->toBe(WriteResult::SUCCESS)
        ->and($results['nested-skill'])->toBe(WriteResult::SUCCESS)
        ->and($staleDir)->not->toBeDirectory();

    cleanupDirectory($target);
});

it('preserves untracked skills during sync', function (): void {
    $source = fixturePath('skills');
    $target = createTempDir();

    // A user-created skill — not in the manifest
    $userSkillDir = $target.'/my-custom-skill';
    mkdir($userSkillDir, 0755, true);
    file_put_contents($userSkillDir.'/SKILL.md', 'user content');

    $writer = new DirectoryWriter($source, $target);
    $writer->sync();

    expect($userSkillDir)->toBeDirectory()
        ->and(file_get_contents($userSkillDir.'/SKILL.md'))->toBe('user content');

    cleanupDirectory($target);
});

it('is idempotent across multiple writes', function (): void {
    $source = fixturePath('skills');
    $target = createTempDir();

    $writer = new DirectoryWriter($source, $target);

    $first = $writer->write('test-skill');
    $second = $writer->write('test-skill');

    $content = file_get_contents($target.'/test-skill/SKILL.md');

    expect($first)->toBe(WriteResult::SUCCESS)
        ->and($second)->toBe(WriteResult::UPDATED)
        ->and($content)->toContain('name: test-skill');

    cleanupDirectory($target);
});

it('removes nested skill directory with deep structure', function (): void {
    $target = createTempDir();
    $skillDir = $target.'/nested-skill';
    $deepDir = $skillDir.'/references/deep/nested';

    mkdir($deepDir, 0755, true);
    file_put_contents($skillDir.'/SKILL.md', 'test');
    file_put_contents($deepDir.'/file.md', 'nested content');

    $writer = new DirectoryWriter(sys_get_temp_dir(), $target);
    $result = $writer->remove('nested-skill');

    expect($result)->toBeTrue()
        ->and($skillDir)->not->toBeDirectory();

    cleanupDirectory($target);
});
