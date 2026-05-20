<?php

namespace Mbs\ModelMind\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearModelMindContextCommand extends Command
{
    protected $signature = 'model-mind:clear-context';

    protected $description = 'Clear the cached ModelMind application context.';

    public function handle(): int
    {
        Cache::forget('model-mind.context.v1');

        $this->info('ModelMind context cache cleared.');

        return self::SUCCESS;
    }
}
