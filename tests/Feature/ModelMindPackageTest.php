<?php

namespace Mbs\ModelMind\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mbs\ModelMind\Models\ModelMindMemory;
use Mbs\ModelMind\Models\ModelMindMessage;
use Mbs\ModelMind\Models\ModelMindSession;
use Mbs\ModelMind\Support\Context\ContextRegistry;
use Mbs\ModelMind\Support\Database\TableNames;
use Mbs\ModelMind\Tests\Fixtures\KnowledgeEntry;
use Mbs\ModelMind\Tests\TestCase;

class ModelMindPackageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        config()->set('model-mind.database.table_prefix', 'model_mind_');
        config()->set('model-mind.provider.api_key', 'sk-test');
        config()->set('model-mind.provider.model', 'gpt-test');
        config()->set('model-mind.provider.organization', 'org-test');
        config()->set('model-mind.provider.max_output_tokens', 800);
        config()->set('model-mind.provider.reasoning_effort', 'minimal');
        config()->set('model-mind.memory.context_cache_seconds', 0);
        config()->set('model-mind.models', [
            KnowledgeEntry::class => [
                'enabled' => true,
                'columns' => 'auto',
                'limit' => 10,
                'order_by' => ['id' => 'asc'],
                'route_actions' => [
                    'knowledge.view' => [
                        'label' => 'Open knowledge',
                        'description' => 'Open the knowledge record.',
                        'route' => 'knowledge.show',
                        'parameters' => ['entry' => 'id'],
                    ],
                ],
            ],
        ]);

        Schema::dropIfExists('model_mind_knowledge_entries');
        Schema::create('model_mind_knowledge_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('password')->nullable();
            $table->string('api_token')->nullable();
            $table->text('hidden_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });
    }

    public function test_blade_directives_render_a_self_contained_widget(): void
    {
        $html = Blade::render('@modelMindStyles @modelMindModal @modelMindScripts <x-model-mind::modal />');

        $this->assertStringContainsString('data-model-mind-widget', $html);
        $this->assertStringContainsString('data-model-mind-position="bottom-right"', $html);
        $this->assertStringContainsString('data-model-mind-theme="auto"', $html);
        $this->assertStringContainsString('--model-mind-width: 25rem;', $html);
        $this->assertStringContainsString('window.ModelMind', $html);
        $this->assertStringContainsString(route('model-mind.chat'), $html);
        $this->assertStringContainsString(route('model-mind.session'), $html);
        $this->assertStringContainsString('Ask ModelMind', $html);
        $this->assertStringContainsString('Helpful', $html);
        $this->assertStringContainsString('Not helpful', $html);
        $this->assertStringContainsString('aria-pressed', $html);
        $this->assertStringNotContainsString('x-cloak', $html);
        $this->assertStringNotContainsString('x-data', $html);
    }

    public function test_position_and_table_prefix_are_configurable(): void
    {
        config()->set('model-mind.ui.position', 'left');
        config()->set('model-mind.ui.width', '30rem');
        config()->set('model-mind.ui.offset', '2rem');
        config()->set('model-mind.ui.z_index', 12345);

        $html = Blade::render('@modelMindStyles @modelMindModal');

        $this->assertStringContainsString('data-model-mind-position="center-left"', $html);
        $this->assertStringContainsString('--model-mind-width: 30rem;', $html);
        $this->assertStringContainsString('--model-mind-offset: 2rem;', $html);
        $this->assertStringContainsString('--model-mind-z-index: 12345;', $html);

        config()->set('model-mind.database.table_prefix', 'assistant_');

        $this->assertSame('assistant_sessions', TableNames::sessions());
        $this->assertSame('assistant_messages', TableNames::messages());
        $this->assertSame('assistant_memories', TableNames::memories());
        $this->assertSame('assistant_sessions', (new ModelMindSession)->getTable());
        $this->assertSame('assistant_messages', (new ModelMindMessage)->getTable());
        $this->assertSame('assistant_memories', (new ModelMindMemory)->getTable());
    }

    public function test_theme_and_custom_views_are_configurable(): void
    {
        config()->set('model-mind.ui.theme', 'dark');

        $darkHtml = Blade::render('@modelMindModal');

        $this->assertStringContainsString('class="model-mind-widget dark"', $darkHtml);
        $this->assertStringContainsString('data-model-mind-theme="dark"', $darkHtml);
        $this->assertStringContainsString('"theme":"dark"', $darkHtml);

        config()->set('model-mind.views.modal', 'model-mind-test::custom-modal');
        config()->set('model-mind.views.styles', 'model-mind-test::custom-styles');
        config()->set('model-mind.views.scripts', 'model-mind-test::custom-scripts');

        $configuredHtml = Blade::render("@modelMind(['data' => ['label' => 'Configured design']])");

        $this->assertStringContainsString('data-custom-model-mind-modal', $configuredHtml);
        $this->assertStringContainsString('Configured design', $configuredHtml);
        $this->assertStringContainsString('data-custom-model-mind-styles', $configuredHtml);
        $this->assertStringContainsString('data-custom-model-mind-scripts', $configuredHtml);

        $inlineHtml = Blade::render("@modelMindModal(['view' => 'model-mind-test::custom-modal', 'data' => ['label' => 'Inline design']])");

        $this->assertStringContainsString('Inline design', $inlineHtml);
    }

    public function test_public_assets_can_be_rendered_and_published_separately(): void
    {
        config()->set('model-mind.assets.use_public', true);
        config()->set('model-mind.assets.styles_path', 'vendor/model-mind/custom.css');
        config()->set('model-mind.assets.scripts_path', 'vendor/model-mind/custom.js');

        $html = Blade::render('@modelMindStyles @modelMindScripts');

        $this->assertStringContainsString('href="http://localhost/vendor/model-mind/custom.css"', $html);
        $this->assertStringContainsString('src="http://localhost/vendor/model-mind/custom.js"', $html);
        $this->assertStringContainsString('defer', $html);
        $this->assertStringNotContainsString('<style>', $html);
        $this->assertStringNotContainsString('window.ModelMind', $html);

        Artisan::call('model-mind:install', [
            '--assets' => true,
            '--dry-run' => true,
        ]);

        $this->assertStringContainsString('Would publish [model-mind-assets].', Artisan::output());

        Artisan::call('model-mind:publish-assets', [
            '--dry-run' => true,
        ]);

        $this->assertStringContainsString('Would publish [model-mind-assets].', Artisan::output());
    }

    public function test_package_uses_a_single_model_mind_migration_file(): void
    {
        $migrations = glob(__DIR__.'/../../database/migrations/*model_mind*.php') ?: [];

        $this->assertCount(1, $migrations);
        $this->assertStringEndsWith('create_model_mind_tables.php', $migrations[0]);

        $migration = require $migrations[0];
        $migration->up();

        $this->assertTrue(Schema::hasTable(TableNames::sessions()));
        $this->assertTrue(Schema::hasTable(TableNames::messages()));
        $this->assertTrue(Schema::hasTable(TableNames::memories()));
    }

    public function test_context_filters_sensitive_columns_and_hidden_records(): void
    {
        KnowledgeEntry::query()->create([
            'title' => 'Public onboarding',
            'body' => '<strong>Use the public setup checklist.</strong>',
            'password' => 'super-secret',
            'api_token' => 'token-secret',
            'hidden_note' => 'hidden detail',
            'internal_note' => 'internal detail',
            'is_public' => true,
        ]);
        KnowledgeEntry::query()->create([
            'title' => 'Private onboarding',
            'body' => 'This should not be indexed.',
            'is_public' => false,
        ]);

        $context = app(ContextRegistry::class)->toPrompt();

        $this->assertStringContainsString('Knowledge entries', $context);
        $this->assertStringContainsString('Public onboarding', $context);
        $this->assertStringContainsString('Use the public setup checklist.', $context);
        $this->assertStringContainsString('knowledge.view', $context);
        $this->assertStringContainsString('Open knowledge', $context);
        $this->assertStringContainsString('[[model_mind_route key=\"knowledge.view\" entry=\"1\"]]', $context);
        $this->assertStringContainsString('[[model_mind_route key=\"knowledge.trait-view\" entry=\"1\"]]', $context);
        $this->assertStringNotContainsString('super-secret', $context);
        $this->assertStringNotContainsString('token-secret', $context);
        $this->assertStringNotContainsString('hidden detail', $context);
        $this->assertStringNotContainsString('internal detail', $context);
        $this->assertStringNotContainsString('Private onboarding', $context);
        $this->assertStringNotContainsString('<strong>', $context);
    }

    public function test_chat_endpoint_persists_messages_and_learns_answers(): void
    {
        KnowledgeEntry::query()->create([
            'title' => 'Support policy',
            'body' => 'Support replies happen within one business day.',
            'password' => 'never-share',
            'is_public' => true,
        ]);

        Http::fake([
            'api.openai.com/v1/responses' => function (Request $request) {
                $instructions = (string) $request['instructions'];
                $input = (string) ($request['input'][0]['content'][0]['text'] ?? '');

                $this->assertSame('gpt-test', $request['model']);
                $this->assertSame(800, $request['max_output_tokens']);
                $this->assertFalse($request['store']);
                $this->assertSame(['effort' => 'minimal'], $request['reasoning']);
                $this->assertSame('Bearer sk-test', $request->header('Authorization')[0] ?? null);
                $this->assertSame('org-test', $request->header('OpenAI-Organization')[0] ?? null);
                $this->assertStringContainsString('Support replies happen within one business day.', $instructions);
                $this->assertStringNotContainsString('never-share', $instructions);
                $this->assertStringContainsString('Current visitor question', $input);

                return Http::response([
                    'output_text' => 'Support replies happen within one business day. Read more at https://example.com/support.',
                ]);
            },
        ]);

        $response = $this->postJson(route('model-mind.chat'), [
            'question' => 'How fast is support?',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('answer', 'Support replies happen within one business day. Read more.')
            ->assertJsonPath('actions.0.kind', 'link')
            ->assertJsonPath('actions.0.url', 'https://example.com/support');

        $this->assertDatabaseHas('model_mind_messages', [
            'role' => ModelMindMessage::ROLE_ASSISTANT,
            'content' => 'Support replies happen within one business day. Read more.',
        ]);
        $this->assertDatabaseHas('model_mind_memories', [
            'source' => 'assistant_answer',
            'title' => 'Assistant answer',
            'content' => 'Support replies happen within one business day. Read more.',
        ]);
    }

    public function test_chat_endpoint_resolves_whitelisted_named_route_actions(): void
    {
        $entry = KnowledgeEntry::query()->create([
            'title' => 'Product setup',
            'body' => 'Use the product setup checklist.',
            'is_public' => true,
        ]);

        Http::fake([
            'api.openai.com/v1/responses' => function (Request $request) use ($entry) {
                $instructions = (string) $request['instructions'];

                $this->assertStringContainsString('Route actions:', $instructions);
                $this->assertStringContainsString('knowledge.view', $instructions);
                $this->assertStringContainsString('route action token', $instructions);
                $this->assertStringContainsString("[[model_mind_route key=\\\"knowledge.view\\\" entry=\\\"{$entry->id}\\\"]]", $instructions);

                return Http::response([
                    'output_text' => "Open the public product setup record.\n[[model_mind_route key=\"knowledge.view\" entry=\"{$entry->id}\"]]\n[[model_mind_route key=\"knowledge.delete\" entry=\"{$entry->id}\"]]",
                ]);
            },
        ]);

        $response = $this->postJson(route('model-mind.chat'), [
            'question' => 'Open product setup',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('answer', 'Open the public product setup record.')
            ->assertJsonCount(1, 'actions')
            ->assertJsonPath('actions.0.label', 'Open knowledge')
            ->assertJsonPath('actions.0.kind', 'route')
            ->assertJsonPath('actions.0.url', url('/knowledge/'.$entry->id));

        $this->assertDatabaseHas('model_mind_messages', [
            'role' => ModelMindMessage::ROLE_ASSISTANT,
            'content' => 'Open the public product setup record.',
        ]);
    }

    public function test_feedback_and_manual_learning_feed_future_context(): void
    {
        $session = ModelMindSession::query()->create();
        $assistantMessage = $session->messages()->create([
            'role' => ModelMindMessage::ROLE_ASSISTANT,
            'content' => 'A saved answer with reusable product policy details.',
        ]);

        $this->postJson(route('model-mind.messages.feedback', $assistantMessage), [
            'session_id' => $session->uuid,
            'feedback' => ModelMindMessage::FEEDBACK_LIKED,
        ])->assertOk();

        $exitCode = Artisan::call('model-mind:learn', [
            'text' => 'Manual fed text becomes reusable package knowledge.',
            '--title' => 'Manual knowledge',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('model_mind_memories', [
            'source' => 'liked_answer',
            'content' => 'A saved answer with reusable product policy details.',
            'weight' => 6,
        ]);
        $this->assertDatabaseHas('model_mind_memories', [
            'source' => 'manual',
            'title' => 'Manual knowledge',
            'content' => 'Manual fed text becomes reusable package knowledge.',
        ]);

        $context = app(ContextRegistry::class)->toPrompt();

        $this->assertStringContainsString('A saved answer with reusable product policy details.', $context);
        $this->assertStringContainsString('Manual fed text becomes reusable package knowledge.', $context);
    }
}
