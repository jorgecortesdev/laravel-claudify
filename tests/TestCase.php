<?php

declare(strict_types=1);

namespace JorgeCortesDev\Claudify\Tests;

use JorgeCortesDev\Claudify\ClaudifyServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [ClaudifyServiceProvider::class];
    }
}
