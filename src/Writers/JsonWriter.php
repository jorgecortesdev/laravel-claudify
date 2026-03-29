<?php

declare(strict_types=1);

namespace JorgeCortesDev\Claudify\Writers;

use Illuminate\Support\Facades\File;

class JsonWriter
{
    public function __construct(private readonly string $filePath) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function write(array $data): void
    {
        File::ensureDirectoryExists(dirname($this->filePath));

        if (File::exists($this->filePath)) {
            $existing = json_decode(File::get($this->filePath), true) ?? [];
            $data = $this->mergeRecursive($existing, $data);
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        File::put($this->filePath, $json."\n");
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
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function mergeRecursive(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                if (array_is_list($value) && array_is_list($base[$key])) {
                    $base[$key] = array_values(array_unique(array_merge($base[$key], $value), SORT_REGULAR));
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
