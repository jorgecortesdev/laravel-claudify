<?php

declare(strict_types=1);

namespace JorgeCortesDev\Claudify\Writers;

use Illuminate\Support\Facades\File;

class GuidelineWriter
{
    private const OPEN_TAG = '<laravel-claudify>';

    private const CLOSE_TAG = '</laravel-claudify>';

    public function __construct(private readonly string $filePath) {}

    public function write(string $content): bool
    {
        File::ensureDirectoryExists(dirname($this->filePath));

        if (! File::exists($this->filePath)) {
            File::put($this->filePath, self::OPEN_TAG."\n".$content."\n".self::CLOSE_TAG."\n");

            return false;
        }

        $existing = File::get($this->filePath);
        $replacement = self::OPEN_TAG."\n".$content."\n".self::CLOSE_TAG;
        $pattern = '/'.preg_quote(self::OPEN_TAG, '/').'.*?'.preg_quote(self::CLOSE_TAG, '/').'/s';

        if (preg_match($pattern, $existing)) {
            $newContent = preg_replace($pattern, $replacement, $existing, 1);
        } else {
            $separator = empty(trim($existing)) ? '' : "\n\n";
            $newContent = rtrim($existing).$separator.$replacement;
        }

        $newContent = preg_replace("/\n{3,}/", "\n\n", $newContent);

        if (! str_ends_with($newContent, "\n")) {
            $newContent .= "\n";
        }

        File::put($this->filePath, $newContent);

        return true;
    }
}
