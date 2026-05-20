<?php

namespace Mbs\ModelMind\Console\Commands;

use Illuminate\Console\Command;

class InstallModelMindCommand extends Command
{
    protected $signature = 'model-mind:install
        {--views : Publish the customizable Blade views}
        {--assets : Publish the public CSS and JavaScript assets}
        {--force : Overwrite previously published files}
        {--dry-run : Show the publish steps without writing files}';

    protected $description = 'Publish the ModelMind configuration, migrations, views, and assets.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $tags = [
            'model-mind-config',
            'model-mind-migrations',
        ];

        if ($this->option('views')) {
            $tags[] = 'model-mind-views';
        }

        if ($this->option('assets')) {
            $tags[] = 'model-mind-assets';
        }

        foreach ($tags as $tag) {
            if ($dryRun) {
                $this->line("Would publish [{$tag}].");

                continue;
            }

            $this->callSilent('vendor:publish', [
                '--tag' => $tag,
                '--force' => $force,
            ]);

            $this->line("Published [{$tag}].");
        }

        if ($dryRun) {
            $this->info('Dry run complete. No files were written.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('ModelMind is installed.');
        $this->line('Next: configure config/model-mind.php, add allowed models, then run php artisan migrate.');

        return self::SUCCESS;
    }
}
