<?php

declare(strict_types=1);

namespace JorgeCortesDev\Claudify\Console\Enums;

enum Theme: string
{
    case Amber = 'amber';
    case Emerald = 'emerald';
    case Indigo = 'indigo';
    case Rose = 'rose';
    case Cyan = 'cyan';

    /**
     * @return array<int, int>
     */
    public function gradient(): array
    {
        return match ($this) {
            self::Amber => [220, 214, 208, 172, 136, 130],
            self::Emerald => [48, 42, 36, 35, 29, 23],
            self::Indigo => [105, 99, 93, 57, 56, 55],
            self::Rose => [211, 205, 169, 133, 97, 91],
            self::Cyan => [123, 87, 51, 45, 39, 33],
        };
    }

    public function primary(): int
    {
        return $this->gradient()[0];
    }

    public static function random(): self
    {
        $cases = self::cases();

        return $cases[array_rand($cases)];
    }
}
