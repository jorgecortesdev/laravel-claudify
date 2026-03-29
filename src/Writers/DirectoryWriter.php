<?php

declare(strict_types=1);

namespace JorgeCortesDev\Claudify\Writers;

use Illuminate\Support\Facades\File;
use JorgeCortesDev\Claudify\Enums\WriteResult;
use RuntimeException;

class DirectoryWriter
{
    private const MANIFEST_FILE = '.claudify-manifest.json';

    public function __construct(
        private readonly string $sourcePath,
        private readonly string $targetPath,
    ) {}

    /**
     * @return array<string, WriteResult>
     */
    public function sync(): array
    {
        $previouslyTracked = $this->readManifest();
        $written = [];

        foreach ($this->available() as $name) {
            $written[$name] = $this->write($name);
        }

        foreach (array_diff($previouslyTracked, array_keys($written)) as $name) {
            $this->remove($name);
        }

        $this->writeManifest(array_keys($written));

        return $written;
    }

    /**
     * @return array<int, string>
     */
    public function available(): array
    {
        if (! is_dir($this->sourcePath)) {
            return [];
        }

        $dirs = glob($this->sourcePath.'/*', GLOB_ONLYDIR);

        return array_map('basename', $dirs ?: []);
    }

    private function write(string $name): WriteResult
    {
        $this->validateName($name);

        $source = $this->sourcePath.'/'.$name;
        $target = $this->targetPath.'/'.$name;

        if (! is_dir($source)) {
            return WriteResult::FAILED;
        }

        $existed = is_dir($target);

        File::deleteDirectory($target);
        File::copyDirectory($source, $target);

        return $existed ? WriteResult::UPDATED : WriteResult::SUCCESS;
    }

    private function remove(string $name): void
    {
        $this->validateName($name);

        File::deleteDirectory($this->targetPath.'/'.$name);
    }

    private function validateName(string $name): void
    {
        if (trim($name) === '' || str_contains($name, '..') || str_contains($name, '/') || str_contains($name, '\\')) {
            throw new RuntimeException("Invalid name: {$name}");
        }
    }

    /**
     * @return array<int, string>
     */
    private function readManifest(): array
    {
        $path = $this->targetPath.'/'.self::MANIFEST_FILE;

        if (! file_exists($path)) {
            return [];
        }

        $data = json_decode(file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<int, string>  $names
     */
    private function writeManifest(array $names): void
    {
        File::ensureDirectoryExists($this->targetPath);

        sort($names);

        file_put_contents(
            $this->targetPath.'/'.self::MANIFEST_FILE,
            json_encode($names, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );
    }
}
