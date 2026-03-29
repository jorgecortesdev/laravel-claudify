<?php

declare(strict_types=1);

namespace JorgeCortesDev\Claudify\Detection;

use Illuminate\Support\Collection;

class StackDetector
{
    /** @var array<string, string> */
    private array $composerRequireDev;

    /** @var array<string, mixed> */
    private array $packageJson;

    public function __construct()
    {
        $composer = $this->readJson(base_path('composer.json'));
        $this->packageJson = $this->readJson(base_path('package.json'));

        $this->composerRequireDev = $composer['require-dev'] ?? [];
    }

    public function hasPest(): bool
    {
        return $this->hasDevPackage('pestphp/pest');
    }

    public function hasBoost(): bool
    {
        return $this->hasDevPackage('laravel/boost');
    }

    public function hasPint(): bool
    {
        return $this->hasDevPackage('laravel/pint');
    }

    public function hasNodeDependencies(): bool
    {
        return $this->packageJson !== [];
    }

    public function hasPrettier(): bool
    {
        return $this->hasNodeDevPackage('prettier');
    }

    public function hasEslint(): bool
    {
        return $this->hasNodeDevPackage('eslint');
    }

    /**
     * @return Collection<string, bool>
     */
    public function all(): Collection
    {
        return collect([
            'pest' => $this->hasPest(),
            'boost' => $this->hasBoost(),
            'pint' => $this->hasPint(),
            'node' => $this->hasNodeDependencies(),
            'prettier' => $this->hasPrettier(),
            'eslint' => $this->hasEslint(),
        ]);
    }

    /**
     * @return Collection<string, bool>
     */
    public function detected(): Collection
    {
        return $this->all()->filter();
    }

    private function hasDevPackage(string $package): bool
    {
        return isset($this->composerRequireDev[$package]);
    }

    private function hasNodeDevPackage(string $package): bool
    {
        return isset($this->packageJson['devDependencies'][$package]);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?? [];
    }
}
