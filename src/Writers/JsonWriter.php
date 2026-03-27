<?php

declare(strict_types=1);

namespace JorgeCortesDev\Claudify\Writers;

use Illuminate\Support\Facades\File;

class JsonWriter
{
    public function __construct(private string $filePath) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function write(array $data): bool
    {
        File::ensureDirectoryExists(dirname($this->filePath));

        if (! File::exists($this->filePath)) {
            return $this->writeJson($data);
        }

        $existing = json_decode(File::get($this->filePath), true) ?? [];
        $merged = $this->mergeRecursive($existing, $data);

        return $this->writeJson($merged);
    }

    public function exists(): bool
    {
        return File::exists($this->filePath);
    }

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        if (! $this->exists()) {
            return [];
        }

        return json_decode(File::get($this->filePath), true) ?? [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeJson(array $data): bool
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return File::put($this->filePath, $json."\n") !== false;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function mergeRecursive(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                if (array_is_list($value) && array_is_list($base[$key])) {
                    $base[$key] = array_values(array_unique(array_merge($base[$key], $value)));
                } else {
                    $base[$key] = $this->mergeRecursive($base[$key], $value);
                }
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
