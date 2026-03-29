<?php

declare(strict_types=1);

namespace JorgeCortesDev\Claudify\Detection;

use Illuminate\Support\Collection;

class StackDetector
{
    /** @var array<string, string> */
    private array $composerRequire;

    /** @var array<string, string> */
    private array $composerRequireDev;

    /** @var array<string, mixed> */
    private array $packageJson;

    public function __construct()
    {
        $composer = $this->readJson(base_path('composer.json'));
        $this->packageJson = $this->readJson(base_path('package.json'));

        $this->composerRequire = $composer['require'] ?? [];
        $this->composerRequireDev = $composer['require-dev'] ?? [];
    }

    public function hasPest(): bool
    {
        return $this->hasDevPackage('pestphp/pest');
    }

    public function hasFilament(): bool
    {
        return $this->hasPackage('filament/filament');
    }

    public function hasInertia(): bool
    {
        return $this->hasPackage('inertiajs/inertia-laravel');
    }

    public function hasLivewire(): bool
    {
        return $this->hasPackage('livewire/livewire');
    }

    public function hasBoost(): bool
    {
        return $this->hasDevPackage('laravel/boost');
    }

    public function hasPint(): bool
    {
        return $this->hasDevPackage('laravel/pint');
    }

    public function hasMcp(): bool
    {
        return $this->hasPackage('laravel/mcp');
    }

    public function hasSanctum(): bool
    {
        return $this->hasPackage('laravel/sanctum');
    }

    public function hasHorizon(): bool
    {
        return $this->hasPackage('laravel/horizon');
    }

    public function hasTelescope(): bool
    {
        return $this->hasDevPackage('laravel/telescope');
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
            'filament' => $this->hasFilament(),
            'inertia' => $this->hasInertia(),
            'livewire' => $this->hasLivewire(),
            'boost' => $this->hasBoost(),
            'pint' => $this->hasPint(),
            'mcp' => $this->hasMcp(),
            'sanctum' => $this->hasSanctum(),
            'horizon' => $this->hasHorizon(),
            'telescope' => $this->hasTelescope(),
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

    private function hasPackage(string $package): bool
    {
        return isset($this->composerRequire[$package]);
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
