<?php

declare(strict_types=1);

use JorgeCortesDev\Claudify\Writers\GuidelineWriter;

it('writes guidelines to a new file', function (): void {
    $path = sys_get_temp_dir().'/claudify-test-'.uniqid().'/CLAUDE.md';

    $writer = new GuidelineWriter($path);
    $existed = $writer->write('test content');

    expect($existed)->toBeFalse()
        ->and($path)->toBeFile()
        ->and(file_get_contents($path))->toContain('<laravel-claudify>')
        ->and(file_get_contents($path))->toContain('test content')
        ->and(file_get_contents($path))->toContain('</laravel-claudify>');

    unlink($path);
    rmdir(dirname($path));
});

it('replaces existing guidelines on re-run', function (): void {
    $path = sys_get_temp_dir().'/claudify-test-'.uniqid().'/CLAUDE.md';
    mkdir(dirname($path), 0755, true);

    file_put_contents($path, "<laravel-claudify>\nold content\n</laravel-claudify>\n");

    $writer = new GuidelineWriter($path);
    $writer->write('new content');

    $result = file_get_contents($path);

    expect($result)->toContain('new content')
        ->and($result)->not->toContain('old content');

    unlink($path);
    rmdir(dirname($path));
});

it('preserves existing content outside tags', function (): void {
    $path = sys_get_temp_dir().'/claudify-test-'.uniqid().'/CLAUDE.md';
    mkdir(dirname($path), 0755, true);

    file_put_contents($path, "# My Project Rules\n\nDo things right.\n");

    $writer = new GuidelineWriter($path);
    $writer->write('claudify rules');

    $result = file_get_contents($path);

    expect($result)->toContain('# My Project Rules')
        ->and($result)->toContain('Do things right.')
        ->and($result)->toContain('<laravel-claudify>')
        ->and($result)->toContain('claudify rules');

    unlink($path);
    rmdir(dirname($path));
});

it('preserves content after closing tag', function (): void {
    $path = sys_get_temp_dir().'/claudify-test-'.uniqid().'/CLAUDE.md';
    mkdir(dirname($path), 0755, true);

    file_put_contents($path, "# Before\n\n<laravel-claudify>\nold\n</laravel-claudify>\n\n# After\n");

    $writer = new GuidelineWriter($path);
    $writer->write('updated');

    $result = file_get_contents($path);

    expect($result)->toContain('# Before')
        ->and($result)->toContain('# After')
        ->and($result)->toContain('updated')
        ->and($result)->not->toContain('old');

    unlink($path);
    rmdir(dirname($path));
});

it('returns true when file already existed', function (): void {
    $path = sys_get_temp_dir().'/claudify-test-'.uniqid().'/CLAUDE.md';
    mkdir(dirname($path), 0755, true);

    file_put_contents($path, "existing content\n");

    $writer = new GuidelineWriter($path);
    $existed = $writer->write('new content');

    expect($existed)->toBeTrue();

    unlink($path);
    rmdir(dirname($path));
});

it('does not create duplicate tags on repeated writes', function (): void {
    $path = sys_get_temp_dir().'/claudify-test-'.uniqid().'/CLAUDE.md';

    $writer = new GuidelineWriter($path);
    $writer->write('first');
    $writer->write('second');

    $result = file_get_contents($path);
    $count = substr_count($result, '<laravel-claudify>');

    expect($count)->toBe(1)
        ->and($result)->toContain('second')
        ->and($result)->not->toContain('first');

    unlink($path);
    rmdir(dirname($path));
});
