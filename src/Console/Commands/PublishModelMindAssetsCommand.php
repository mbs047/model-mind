<?php

namespace Mbs\ModelMind\Console\Commands;

use Illuminate\Console\Command;

class PublishModelMindAssetsCommand extends Command
{
    protected $signature = 'model-mind:publish-assets
        {--force : Overwrite previously published assets}
        {--dry-run : Show the publish step without writing files}';

    protected $description = 'Publish the ModelMind public CSS and JavaScript assets.';

    public function handle(): int
    {
        if ($this->option('dry-run')) {
            $this->line('Would publish [model-mind-assets].');
            $this->info('Dry run complete. No files were written.');

            return self::SUCCESS;
        }

        $this->callSilent('vendor:publish', [
            '--tag' => 'model-mind-assets',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->info('Published [model-mind-assets].');
        $this->line('Next: set MODEL_MIND_USE_PUBLIC_ASSETS=true if you want the package directives to render public asset tags.');

        return self::SUCCESS;
    }
}
