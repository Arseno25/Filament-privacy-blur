<?php

namespace Arseno25\FilamentPrivacyBlur\Commands;

use Illuminate\Console\Command;

class FilamentPrivacyBlurCommand extends Command
{
    public $signature = 'filament-privacy-blur';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
