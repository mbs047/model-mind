<?php

namespace Mbs\ModelMind\Console\Commands;

use Illuminate\Console\Command;
use Mbs\ModelMind\Support\Learning\LearningRepository;

class LearnModelMindKnowledgeCommand extends Command
{
    protected $signature = 'model-mind:learn
        {text? : Text to add to ModelMind learned knowledge}
        {--title= : Optional title for the learned knowledge}
        {--source=manual : Source label for the learned knowledge}';

    protected $description = 'Feed reusable knowledge into ModelMind memory.';

    public function handle(LearningRepository $learning): int
    {
        $text = (string) ($this->argument('text') ?? '');

        if (blank($text)) {
            $text = stream_get_contents(STDIN) ?: '';
        }

        $memory = $learning->remember(
            content: $text,
            source: (string) $this->option('source'),
            title: is_string($this->option('title')) ? $this->option('title') : null,
            metadata: ['fed_by' => 'artisan'],
            weight: 8,
        );

        if (! $memory) {
            $this->warn('No knowledge was learned. Check that learning is enabled and the text is not too short or sensitive.');

            return self::FAILURE;
        }

        $this->info("Learned knowledge [{$memory->uuid}].");

        return self::SUCCESS;
    }
}
