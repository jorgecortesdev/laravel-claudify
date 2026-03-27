<?php

declare(strict_types=1);

namespace JorgeCortesDev\Claudify;

use Illuminate\Support\ServiceProvider;
use JorgeCortesDev\Claudify\Console\InstallCommand;

class ClaudifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }
    }
}
