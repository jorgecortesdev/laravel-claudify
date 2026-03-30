<?php

declare(strict_types=1);

namespace JorgeCortesDev\Claudify\Console;

use Illuminate\Console\Command;

class UpdateCommand extends Command
{
    protected $signature = 'claudify:update';

    protected $description = 'Update Claudify configuration to the latest version';

    public function handle(): int
    {
        $this->callSilently(InstallCommand::class, ['--update' => true]);

        $this->info('Claudify updated.');

        return self::SUCCESS;
    }
}
