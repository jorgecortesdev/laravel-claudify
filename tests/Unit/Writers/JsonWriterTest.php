<?php

declare(strict_types=1);

use JorgeCortesDev\Claudify\Writers\JsonWriter;

function createTempJsonPath(): string
{
    return sys_get_temp_dir().'/claudify-test-'.uniqid().'/test.json';
}

function cleanupTempJson(string $path): void
{
    if (file_exists($path)) {
        unlink($path);
    }

    $dir = dirname($path);

    if (is_dir($dir)) {
        @rmdir($dir);
    }
}

it('writes json to a new file', function (): void {
    $path = createTempJsonPath();

    $writer = new JsonWriter($path);
    $result = $writer->write(['key' => 'value']);

    expect($result)->toBeTrue()
        ->and($path)->toBeFile();

    $content = json_decode(file_get_contents($path), true);
    expect($content)->toBe(['key' => 'value']);

    cleanupTempJson($path);
});

it('creates parent directories if they do not exist', function (): void {
    $path = sys_get_temp_dir().'/claudify-test-'.uniqid().'/nested/dir/test.json';

    $writer = new JsonWriter($path);
    $writer->write(['key' => 'value']);

    expect($path)->toBeFile();

    // Cleanup nested dirs
    unlink($path);
    rmdir(dirname($path));
    rmdir(dirname($path, 2));
    rmdir(dirname($path, 3));
});

it('merges with existing file on write', function (): void {
    $path = createTempJsonPath();

    $writer = new JsonWriter($path);
    $writer->write(['a' => 1, 'b' => 2]);
    $writer->write(['b' => 3, 'c' => 4]);

    $content = $writer->read();

    expect($content)->toBe(['a' => 1, 'b' => 3, 'c' => 4]);

    cleanupTempJson($path);
});

it('merges sequential arrays by deduplicating', function (): void {
    $path = createTempJsonPath();

    $writer = new JsonWriter($path);
    $writer->write(['permissions' => ['allow' => ['read', 'write']]]);
    $writer->write(['permissions' => ['allow' => ['write', 'execute']]]);

    $content = $writer->read();

    expect($content['permissions']['allow'])->toBe(['read', 'write', 'execute']);

    cleanupTempJson($path);
});

it('merges associative arrays recursively', function (): void {
    $path = createTempJsonPath();

    $writer = new JsonWriter($path);
    $writer->write([
        'permissions' => [
            'allow' => ['read'],
            'deny' => ['delete'],
        ],
    ]);
    $writer->write([
        'permissions' => [
            'allow' => ['write'],
        ],
    ]);

    $content = $writer->read();

    expect($content['permissions']['allow'])->toBe(['read', 'write'])
        ->and($content['permissions']['deny'])->toBe(['delete']);

    cleanupTempJson($path);
});

it('overwrites scalar values on merge', function (): void {
    $path = createTempJsonPath();

    $writer = new JsonWriter($path);
    $writer->write(['name' => 'old']);
    $writer->write(['name' => 'new']);

    $content = $writer->read();

    expect($content['name'])->toBe('new');

    cleanupTempJson($path);
});

it('reports exists correctly', function (): void {
    $path = createTempJsonPath();

    $writer = new JsonWriter($path);

    expect($writer->exists())->toBeFalse();

    $writer->write(['key' => 'value']);

    expect($writer->exists())->toBeTrue();

    cleanupTempJson($path);
});

it('returns empty array when reading non-existent file', function (): void {
    $path = createTempJsonPath();

    $writer = new JsonWriter($path);

    expect($writer->read())->toBe([]);
});

it('writes pretty-printed json with unescaped slashes', function (): void {
    $path = createTempJsonPath();

    $writer = new JsonWriter($path);
    $writer->write(['path' => '/usr/local/bin']);

    $raw = file_get_contents($path);

    expect($raw)->toContain('/usr/local/bin')
        ->not->toContain('\\/usr\\/local\\/bin')
        ->and($raw)->toEndWith("\n");

    cleanupTempJson($path);
});

it('handles empty existing file gracefully', function (): void {
    $path = createTempJsonPath();

    mkdir(dirname($path), 0755, true);
    file_put_contents($path, '');

    $writer = new JsonWriter($path);
    $writer->write(['key' => 'value']);

    expect($writer->read())->toBe(['key' => 'value']);

    cleanupTempJson($path);
});
