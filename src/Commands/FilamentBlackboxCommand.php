<?php

namespace Blackbox\FilamentBlackbox\Commands;

use Illuminate\Console\Command;

class FilamentBlackboxCommand extends Command
{
    public $signature = 'filament-blackbox';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
