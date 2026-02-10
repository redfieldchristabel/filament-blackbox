<?php

namespace Blackbox\FilamentBlackbox\Commands;

use Illuminate\Console\Command;

class FilamentBlackboxCommand extends Command
{
    public $signature = 'blackbox:test';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
