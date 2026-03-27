<?php

declare(strict_types=1);

use JorgeCortesDev\Claudify\Tests\TestCase;

use function Pest\testDirectory;

uses(TestCase::class)->in('Unit', 'Feature');

function fixturePath(string $name): string
{
    return testDirectory('Fixtures/'.$name);
}
