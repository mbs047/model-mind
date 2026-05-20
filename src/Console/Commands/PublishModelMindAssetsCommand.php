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
        $this->line('Next: keep @modelMindStyles and @modelMindScripts in your layout so the published CSS and JS load.');

        return self::SUCCESS;
    }
}
