<?php

namespace Mbs\LaravelAiChat\Console\Commands;

use Illuminate\Console\Command;

class InstallMbsAiChatCommand extends Command
{
    protected $signature = 'mbs-ai-chat:install
        {--views : Publish the customizable Blade views}
        {--force : Overwrite previously published files}
        {--dry-run : Show the publish steps without writing files}';

    protected $description = 'Publish the MBS Laravel AI Chat configuration and migrations.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $tags = [
            'mbs-ai-chat-config',
            'mbs-ai-chat-migrations',
        ];

        if ($this->option('views')) {
            $tags[] = 'mbs-ai-chat-views';
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
        $this->info('MBS Laravel AI Chat is installed.');
        $this->line('Next: configure config/mbs-ai-chat.php, add allowed models, then run php artisan migrate.');

        return self::SUCCESS;
    }
}
