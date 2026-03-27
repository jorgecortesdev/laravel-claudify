<?php

declare(strict_types=1);

namespace JorgeCortesDev\Claudify\Writers;

use JorgeCortesDev\Claudify\Enums\WriteResult;
use RuntimeException;
use Symfony\Component\Finder\Finder;

class SkillWriter
{
    private const MANIFEST_FILE = '.claudify-manifest.json';

    public function __construct(
        private string $sourcePath,
        private string $targetPath,
    ) {}

    public function write(string $skillName): WriteResult
    {
        $this->validateSkillName($skillName);

        $source = $this->sourcePath.DIRECTORY_SEPARATOR.$skillName;
        $target = $this->targetPath.DIRECTORY_SEPARATOR.$skillName;

        if (! is_dir($source)) {
            return WriteResult::FAILED;
        }

        $existed = is_dir($target);

        if (! $this->copyDirectory($source, $target)) {
            return WriteResult::FAILED;
        }

        return $existed ? WriteResult::UPDATED : WriteResult::SUCCESS;
    }

    /**
     * @return array<string, WriteResult>
     */
    public function writeAll(): array
    {
        $results = [];

        foreach ($this->availableSkills() as $skillName) {
            $results[$skillName] = $this->write($skillName);
        }

        return $results;
    }

    public function remove(string $skillName): bool
    {
        if (! $this->isValidSkillName($skillName)) {
            return false;
        }

        $target = $this->targetPath.DIRECTORY_SEPARATOR.$skillName;

        if (! is_dir($target)) {
            return true;
        }

        return $this->deleteDirectory($target);
    }

    /**
     * @param  array<int, string>  $skillNames
     * @return array<string, bool>
     */
    public function removeStale(array $skillNames): array
    {
        $results = [];

        foreach ($skillNames as $name) {
            $results[$name] = $this->remove($name);
        }

        return $results;
    }

    /**
     * @return array<string, WriteResult>
     */
    public function sync(): array
    {
        $previouslyTracked = $this->readManifest();

        $written = $this->writeAll();

        $currentSkillNames = array_keys($written);
        $stale = array_values(array_diff($previouslyTracked, $currentSkillNames));

        $this->removeStale($stale);
        $this->writeManifest($currentSkillNames);

        return $written;
    }

    /**
     * @return array<int, string>
     */
    public function availableSkills(): array
    {
        if (! is_dir($this->sourcePath)) {
            return [];
        }

        $dirs = glob($this->sourcePath.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR);

        return array_map('basename', $dirs ?: []);
    }

    private function validateSkillName(string $skillName): void
    {
        if (! $this->isValidSkillName($skillName)) {
            throw new RuntimeException("Invalid skill name: {$skillName}");
        }
    }

    private function isValidSkillName(string $name): bool
    {
        return trim($name) !== ''
            && ! str_contains($name, '..')
            && ! str_contains($name, '/')
            && ! str_contains($name, '\\');
    }

    private function copyDirectory(string $source, string $target): bool
    {
        $this->deleteDirectory($target);
        $this->ensureDirectoryExists($target);

        $finder = Finder::create()
            ->files()
            ->in($source)
            ->ignoreDotFiles(false);

        foreach ($finder as $file) {
            $targetFile = $target.DIRECTORY_SEPARATOR.$file->getRelativePathname();

            $this->ensureDirectoryExists(dirname($targetFile));

            if (! @copy($file->getRealPath(), $targetFile)) {
                return false;
            }
        }

        return true;
    }

    private function deleteDirectory(string $path): bool
    {
        if (! is_dir($path)) {
            return false;
        }

        $finder = Finder::create()
            ->in($path)
            ->ignoreDotFiles(false)
            ->sortByName()
            ->reverseSorting();

        foreach ($finder as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }

        return @rmdir($path);
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            @mkdir($path, 0755, true);
        }
    }

    /**
     * @return array<int, string>
     */
    private function readManifest(): array
    {
        $manifestPath = $this->targetPath.DIRECTORY_SEPARATOR.self::MANIFEST_FILE;

        if (! file_exists($manifestPath)) {
            return [];
        }

        $data = json_decode(file_get_contents($manifestPath), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<int, string>  $skillNames
     */
    private function writeManifest(array $skillNames): void
    {
        $this->ensureDirectoryExists($this->targetPath);

        $manifestPath = $this->targetPath.DIRECTORY_SEPARATOR.self::MANIFEST_FILE;

        sort($skillNames);

        file_put_contents(
            $manifestPath,
            json_encode($skillNames, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );
    }
}
