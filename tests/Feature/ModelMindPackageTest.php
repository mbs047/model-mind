<?php

namespace Mbs\ModelMind\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mbs\ModelMind\Contracts\ModelMindProvider;
use Mbs\ModelMind\Events\ActionResolved;
use Mbs\ModelMind\Events\AnswerGenerated;
use Mbs\ModelMind\Events\FeedbackSubmitted;
use Mbs\ModelMind\Events\MemoryLearned;
use Mbs\ModelMind\Events\MessageSent;
use Mbs\ModelMind\Models\ModelMindEvent;
use Mbs\ModelMind\Models\ModelMindMemory;
use Mbs\ModelMind\Models\ModelMindMessage;
use Mbs\ModelMind\Models\ModelMindSession;
use Mbs\ModelMind\Support\Context\ContextRegistry;
use Mbs\ModelMind\Support\Database\TableNames;
use Mbs\ModelMind\Support\Presets\ModelMindPresetRepository;
use Mbs\ModelMind\Tests\Fixtures\CustomProvider;
use Mbs\ModelMind\Tests\Fixtures\FakeVectorSearcher;
use Mbs\ModelMind\Tests\Fixtures\KnowledgeEntry;
use Mbs\ModelMind\Tests\Fixtures\StreamingProvider;
use Mbs\ModelMind\Tests\Fixtures\User;
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

        Schema::dropIfExists('model_mind_users');
        Schema::create('model_mind_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password')->nullable();
            $table->string('role_names')->nullable();
            $table->string('tenant_id')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('model_mind_knowledge_entries');
        Schema::create('model_mind_knowledge_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('tenant_id')->nullable();
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

    public function test_blade_directives_render_widget_markup_and_public_assets(): void
    {
        $html = Blade::render('@modelMindStyles @modelMindModal @modelMindScripts <x-model-mind::modal />');

        $this->assertStringContainsString('data-model-mind-widget', $html);
        $this->assertStringContainsString('data-model-mind-position="bottom-right"', $html);
        $this->assertStringContainsString('data-model-mind-theme="auto"', $html);
        $this->assertStringContainsString('--model-mind-width: 25rem;', $html);
        $this->assertStringContainsString('href="http://localhost/vendor/model-mind/model-mind.css"', $html);
        $this->assertStringContainsString('src="http://localhost/vendor/model-mind/model-mind.js"', $html);
        $this->assertStringContainsString(route('model-mind.chat'), $html);
        $this->assertStringContainsString(route('model-mind.stream'), $html);
        $this->assertStringContainsString(route('model-mind.session'), $html);
        $this->assertStringContainsString(route('model-mind.actions.click'), $html);
        $this->assertStringContainsString('"streamingEnabled":false', $html);
        $this->assertStringContainsString('"pageContext":{"enabled":true', $html);
        $this->assertStringContainsString('Ask ModelMind', $html);
        $this->assertStringNotContainsString('x-cloak', $html);
        $this->assertStringNotContainsString('x-data', $html);
        $this->assertStringNotContainsString('<style>', $html);
        $this->assertStringNotContainsString('window.ModelMind', $html);

        $browserScript = file_get_contents(__DIR__.'/../../resources/dist/model-mind.js');

        $this->assertIsString($browserScript);
        $this->assertStringContainsString('Helpful', $browserScript);
        $this->assertStringContainsString('Not helpful', $browserScript);
        $this->assertStringContainsString('aria-pressed', $browserScript);
        $this->assertStringContainsString('Sources', $browserScript);
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
        $this->assertSame('assistant_events', TableNames::events());
        $this->assertSame('assistant_sessions', (new ModelMindSession)->getTable());
        $this->assertSame('assistant_messages', (new ModelMindMessage)->getTable());
        $this->assertSame('assistant_memories', (new ModelMindMemory)->getTable());
        $this->assertSame('assistant_events', (new ModelMindEvent)->getTable());
    }

    public function test_default_questions_and_session_lifetime_are_configurable(): void
    {
        config()->set('model-mind.assistant.default_questions', [
            'Which products are low in stock?',
            'Show recent orders',
        ]);
        config()->set('model-mind.assistant.quick_questions', [
            'Legacy question',
        ]);
        config()->set('model-mind.memory.session_lifetime_minutes', 15);

        $html = Blade::render('@modelMindModal');

        $this->assertStringContainsString('Which products are low in stock?', $html);
        $this->assertStringContainsString('Show recent orders', $html);
        $this->assertStringContainsString('"sessionLifetimeMinutes":15', $html);
        $this->assertStringNotContainsString('Legacy question', $html);
    }

    public function test_theme_modal_view_and_asset_paths_are_configurable(): void
    {
        config()->set('model-mind.ui.theme', 'dark');

        $darkHtml = Blade::render('@modelMindModal');

        $this->assertStringContainsString('class="model-mind-widget dark"', $darkHtml);
        $this->assertStringContainsString('data-model-mind-theme="dark"', $darkHtml);
        $this->assertStringContainsString('"theme":"dark"', $darkHtml);

        config()->set('model-mind.views.modal', 'model-mind-test::custom-modal');
        config()->set('model-mind.assets.styles_path', 'vendor/model-mind/theme.css');
        config()->set('model-mind.assets.scripts_path', 'vendor/model-mind/theme.js');

        $configuredHtml = Blade::render("@modelMind(['data' => ['label' => 'Configured design']])");

        $this->assertStringContainsString('data-custom-model-mind-modal', $configuredHtml);
        $this->assertStringContainsString('Configured design', $configuredHtml);
        $this->assertStringContainsString('href="http://localhost/vendor/model-mind/theme.css"', $configuredHtml);
        $this->assertStringContainsString('src="http://localhost/vendor/model-mind/theme.js"', $configuredHtml);

        $inlineHtml = Blade::render("@modelMindModal(['view' => 'model-mind-test::custom-modal', 'data' => ['label' => 'Inline design']])");

        $this->assertStringContainsString('Inline design', $inlineHtml);

        $assetOverrideHtml = Blade::render("@modelMind(['styles' => 'vendor/model-mind/alt.css', 'scripts' => ['path' => 'vendor/model-mind/alt.js']])");

        $this->assertStringContainsString('href="http://localhost/vendor/model-mind/alt.css"', $assetOverrideHtml);
        $this->assertStringContainsString('src="http://localhost/vendor/model-mind/alt.js"', $assetOverrideHtml);
    }

    public function test_public_assets_are_rendered_and_published_separately(): void
    {
        config()->set('model-mind.assets.styles_path', 'vendor/model-mind/custom.css');
        config()->set('model-mind.assets.scripts_path', 'vendor/model-mind/custom.js');

        $html = Blade::render('@modelMindStyles @modelMindScripts');

        $this->assertStringContainsString('href="http://localhost/vendor/model-mind/custom.css"', $html);
        $this->assertStringContainsString('src="http://localhost/vendor/model-mind/custom.js"', $html);
        $this->assertStringContainsString('defer', $html);
        $this->assertStringNotContainsString('<style>', $html);
        $this->assertStringNotContainsString('window.ModelMind', $html);

        Artisan::call('model-mind:install', [
            '--dry-run' => true,
        ]);

        $this->assertStringContainsString('Would publish [model-mind-assets].', Artisan::output());

        Artisan::call('model-mind:publish-assets', [
            '--dry-run' => true,
        ]);

        $this->assertStringContainsString('Would publish [model-mind-assets].', Artisan::output());
    }

    public function test_headless_api_manifest_exposes_embeddable_json_contract(): void
    {
        config()->set('model-mind.assistant.default_questions', [
            'Which products are low in stock?',
            'Show recent orders',
        ]);

        $response = $this->getJson(route('model-mind.api.manifest'));

        $response
            ->assertOk()
            ->assertJsonPath('name', 'ModelMind')
            ->assertJsonPath('brand_mark', 'MBS')
            ->assertJsonPath('default_questions.0', 'Which products are low in stock?')
            ->assertJsonPath('features.feedback', true)
            ->assertJsonPath('features.actions', true)
            ->assertJsonPath('features.citations', true)
            ->assertJsonPath('features.streaming', false)
            ->assertJsonPath('features.analytics', true)
            ->assertJsonPath('features.page_context', true)
            ->assertJsonPath('endpoints.chat', route('model-mind.api.chat'))
            ->assertJsonPath('endpoints.stream', route('model-mind.api.stream'))
            ->assertJsonPath('endpoints.session', route('model-mind.api.session'))
            ->assertJsonPath('endpoints.action_click', route('model-mind.api.actions.click'))
            ->assertJsonPath('limits.question_characters', 2000)
            ->assertJsonPath('limits.page_context_characters', 6000)
            ->assertJsonPath('session_lifetime_minutes', 120);

        $this->assertStringContainsString(
            '/api/model-mind/messages/{message}/feedback',
            $response->json('endpoints.feedback'),
        );
    }

    public function test_headless_api_chat_supports_stateless_custom_clients(): void
    {
        $entry = KnowledgeEntry::query()->create([
            'title' => 'Headless API policy',
            'body' => 'React and mobile clients should carry the session_id between requests.',
            'is_public' => true,
        ]);

        Http::fake([
            'api.openai.com/v1/responses' => fn () => Http::response([
                'output_text' => "React and mobile clients should carry the session_id between requests.\n[[model_mind_route key=\"knowledge.view\" entry=\"{$entry->id}\"]]",
            ]),
        ]);

        $response = $this->postJson(route('model-mind.api.chat'), [
            'question' => 'How should custom clients keep a session?',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'answer',
                'actions',
                'citations',
                'session_id',
                'expires_at',
                'user_message_id',
                'message_id',
            ])
            ->assertJsonPath('answer', 'React and mobile clients should carry the session_id between requests.')
            ->assertJsonPath('actions.0.url', url("/knowledge/{$entry->id}"));

        $this->getJson(route('model-mind.api.session', [
            'session_id' => $response->json('session_id'),
        ]))
            ->assertOk()
            ->assertJsonPath('messages.0.role', ModelMindMessage::ROLE_USER)
            ->assertJsonPath('messages.1.role', ModelMindMessage::ROLE_ASSISTANT)
            ->assertJsonPath('messages.1.actions.0.url', url("/knowledge/{$entry->id}"));
    }

    public function test_stream_endpoint_emits_server_sent_events_and_persists_message(): void
    {
        config()->set('model-mind.features.streaming', true);

        $entry = KnowledgeEntry::query()->create([
            'title' => 'Streaming policy',
            'body' => 'Streamed answers should show up before the full response is complete.',
            'is_public' => true,
        ]);
        StreamingProvider::$chunks = [
            "Streaming answer with a route.\n",
            "[[model_mind_route key=\"knowledge.view\" entry=\"{$entry->id}\"]]",
        ];

        $this->app->bind(ModelMindProvider::class, StreamingProvider::class);

        $response = $this->post(route('model-mind.stream'), [
            'question' => 'Can you stream this answer?',
        ], [
            'Accept' => 'text/event-stream',
        ]);

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('event: ready', $content);
        $this->assertStringContainsString('event: delta', $content);
        $this->assertStringContainsString('Streaming answer with a route.', $content);
        $this->assertStringContainsString('event: done', $content);
        $this->assertStringContainsString('"answer":"Streaming answer with a route."', $content);
        $this->assertStringContainsString(url("/knowledge/{$entry->id}"), $content);
        $this->assertStringNotContainsString('model_mind_route', $content);
        $this->assertDatabaseHas('model_mind_messages', [
            'role' => ModelMindMessage::ROLE_ASSISTANT,
            'content' => 'Streaming answer with a route.',
        ]);
    }

    public function test_anthropic_provider_driver_can_be_selected_from_config(): void
    {
        config()->set('model-mind.provider.default', 'anthropic');
        config()->set('model-mind.provider.drivers.anthropic.api_key', 'anthropic-test-key');
        config()->set('model-mind.provider.drivers.anthropic.model', 'claude-test');
        config()->set('model-mind.provider.drivers.anthropic.base_url', 'https://anthropic.test/v1');
        config()->set('model-mind.provider.drivers.anthropic.max_output_tokens', 500);

        Http::fake([
            'anthropic.test/v1/messages' => function (Request $request) {
                $this->assertSame('anthropic-test-key', $request->header('x-api-key')[0] ?? null);
                $this->assertSame('2023-06-01', $request->header('anthropic-version')[0] ?? null);
                $this->assertSame('claude-test', $request['model']);
                $this->assertSame(500, $request['max_tokens']);
                $this->assertFalse($request['stream']);
                $this->assertStringContainsString('Current visitor question', $request['messages'][0]['content']);

                return Http::response([
                    'content' => [
                        ['type' => 'text', 'text' => 'Anthropic provider answer.'],
                    ],
                ]);
            },
        ]);

        $this->postJson(route('model-mind.chat'), [
            'question' => 'Use Anthropic',
        ])
            ->assertOk()
            ->assertJsonPath('answer', 'Anthropic provider answer.');
    }

    public function test_gemini_provider_driver_can_be_selected_from_config(): void
    {
        config()->set('model-mind.provider.default', 'gemini');
        config()->set('model-mind.provider.drivers.gemini.api_key', 'gemini-test-key');
        config()->set('model-mind.provider.drivers.gemini.model', 'gemini-test');
        config()->set('model-mind.provider.drivers.gemini.base_url', 'https://gemini.test/v1beta');
        config()->set('model-mind.provider.drivers.gemini.max_output_tokens', 600);

        Http::fake([
            'gemini.test/*' => function (Request $request) {
                $this->assertStringContainsString('/models/gemini-test:generateContent?key=gemini-test-key', $request->url());
                $this->assertSame(600, $request['generationConfig']['maxOutputTokens']);
                $this->assertStringContainsString('Current visitor question', $request['contents'][0]['parts'][0]['text']);

                return Http::response([
                    'candidates' => [[
                        'content' => [
                            'parts' => [
                                ['text' => 'Gemini provider answer.'],
                            ],
                        ],
                    ]],
                ]);
            },
        ]);

        $this->postJson(route('model-mind.chat'), [
            'question' => 'Use Gemini',
        ])
            ->assertOk()
            ->assertJsonPath('answer', 'Gemini provider answer.');
    }

    public function test_ollama_provider_driver_can_be_selected_from_config(): void
    {
        config()->set('model-mind.provider.default', 'ollama');
        config()->set('model-mind.provider.drivers.ollama.model', 'llama-test');
        config()->set('model-mind.provider.drivers.ollama.base_url', 'http://ollama.test');
        config()->set('model-mind.provider.drivers.ollama.options', ['temperature' => 0]);

        Http::fake([
            'ollama.test/api/chat' => function (Request $request) {
                $this->assertSame('llama-test', $request['model']);
                $this->assertFalse($request['stream']);
                $this->assertSame(['temperature' => 0], $request['options']);
                $this->assertSame('system', $request['messages'][0]['role']);
                $this->assertSame('user', $request['messages'][1]['role']);

                return Http::response([
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Ollama provider answer.',
                    ],
                ]);
            },
        ]);

        $this->postJson(route('model-mind.chat'), [
            'question' => 'Use Ollama',
        ])
            ->assertOk()
            ->assertJsonPath('answer', 'Ollama provider answer.');
    }

    public function test_custom_provider_driver_can_be_selected_from_config(): void
    {
        config()->set('model-mind.provider.default', 'custom');
        config()->set('model-mind.provider.drivers.custom.class', CustomProvider::class);

        $this->postJson(route('model-mind.chat'), [
            'question' => 'Use custom provider',
        ])
            ->assertOk()
            ->assertJsonPath('answer', 'Custom provider answer.');
    }

    public function test_presets_expose_complete_configuration_recommendations(): void
    {
        $presets = app(ModelMindPresetRepository::class);

        $this->assertSame(['store', 'admin', 'support', 'docs', 'crm'], $presets->names());

        foreach ($presets->names() as $name) {
            $preset = $presets->find($name);

            $this->assertIsArray($preset);
            $this->assertNotEmpty($preset['questions']);
            $this->assertNotEmpty($preset['models']);
            $this->assertNotEmpty($preset['retrieval']);
            $this->assertNotEmpty($preset['security']);
            $this->assertNotEmpty($preset['route_actions']);
            $this->assertNotEmpty($preset['config']['assistant']['default_questions']);
            $this->assertNotEmpty($preset['config']['models']);
            $this->assertNotEmpty($preset['config']['retrieval']);
            $this->assertNotEmpty($preset['config']['security']);
        }

        $store = $presets->find('store');

        $this->assertArrayHasKey('App\\Models\\Product', $store['config']['models']);
        $this->assertSame('Products', $store['config']['models']['App\\Models\\Product']['label']);
        $this->assertArrayHasKey('products.view', $store['config']['models']['App\\Models\\Product']['route_actions']);
    }

    public function test_preset_command_lists_and_exports_json_recommendations(): void
    {
        config()->set('model-mind.preset', 'support');

        Artisan::call('model-mind:preset', [
            '--list' => true,
        ]);

        $listOutput = Artisan::output();

        $this->assertStringContainsString('store', $listOutput);
        $this->assertStringContainsString('support (active)', $listOutput);

        Artisan::call('model-mind:preset', [
            'preset' => 'crm',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true);

        $this->assertIsArray($payload);
        $this->assertSame('crm', $payload['name']);
        $this->assertSame('CRM', $payload['label']);
        $this->assertArrayHasKey('App\\Models\\Contact', $payload['config']['models']);
        $this->assertSame('contacts.view', $payload['config']['route_actions'][0]);
    }

    public function test_context_cache_can_be_cleared(): void
    {
        Cache::put('model-mind.context.v1', ['stale' => true], 600);

        Artisan::call('model-mind:clear-context');

        $this->assertFalse(Cache::has('model-mind.context.v1'));
        $this->assertStringContainsString('ModelMind context cache cleared.', Artisan::output());
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
        $this->assertTrue(Schema::hasTable(TableNames::events()));
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
        $this->assertStringContainsString('model_mind_source', $context);
        $this->assertStringContainsString('[[model_mind_route key=\"knowledge.view\" entry=\"1\"]]', $context);
        $this->assertStringContainsString('[[model_mind_route key=\"knowledge.trait-view\" entry=\"1\"]]', $context);
        $this->assertStringNotContainsString('super-secret', $context);
        $this->assertStringNotContainsString('token-secret', $context);
        $this->assertStringNotContainsString('hidden detail', $context);
        $this->assertStringNotContainsString('internal detail', $context);
        $this->assertStringNotContainsString('Private onboarding', $context);
        $this->assertStringNotContainsString('<strong>', $context);
    }

    public function test_context_can_include_authenticated_user_guard_roles_and_tenant(): void
    {
        config()->set('model-mind.authorization.user_columns', [
            'id',
            'name',
            'email',
            'password',
            'tenant_id',
        ]);

        $user = User::query()->create([
            'name' => 'MBS Admin',
            'email' => 'admin@example.test',
            'password' => 'should-not-be-shared',
            'role_names' => 'owner, support',
            'tenant_id' => 'tenant-alpha',
        ]);

        $this->actingAs($user);

        $context = app(ContextRegistry::class)->toPrompt();

        $this->assertStringContainsString('"authorization"', $context);
        $this->assertStringContainsString('"guard": "web"', $context);
        $this->assertStringContainsString('"authenticated": true', $context);
        $this->assertStringContainsString('MBS Admin', $context);
        $this->assertStringContainsString('admin@example.test', $context);
        $this->assertStringContainsString('tenant-alpha', $context);
        $this->assertStringContainsString('owner', $context);
        $this->assertStringContainsString('support', $context);
        $this->assertStringNotContainsString('should-not-be-shared', $context);
    }

    public function test_model_context_is_scoped_by_user_tenant_and_gate_checks(): void
    {
        config()->set('model-mind.models', [
            KnowledgeEntry::class => [
                'enabled' => true,
                'columns' => 'auto',
                'limit' => 10,
                'order_by' => ['id' => 'asc'],
                'authorization' => [
                    'scope_to_user' => true,
                    'user_column' => 'user_id',
                    'scope_to_tenant' => true,
                    'tenant_column' => 'tenant_id',
                    'gate' => true,
                    'ability' => 'view',
                ],
                'route_actions' => [
                    'knowledge.view' => [
                        'label' => 'Open knowledge',
                        'route' => 'knowledge.show',
                        'parameters' => ['entry' => 'id'],
                    ],
                ],
            ],
        ]);

        $user = User::query()->create([
            'name' => 'Tenant Owner',
            'email' => 'owner@example.test',
            'role_names' => 'owner',
            'tenant_id' => 'tenant-alpha',
        ]);
        $otherUser = User::query()->create([
            'name' => 'Other User',
            'email' => 'other@example.test',
            'tenant_id' => 'tenant-alpha',
        ]);

        $allowed = KnowledgeEntry::query()->create([
            'user_id' => $user->id,
            'tenant_id' => 'tenant-alpha',
            'title' => 'Allowed tenant launch plan',
            'body' => 'Visible only to the owning tenant user.',
            'is_public' => true,
        ]);
        $deniedByGate = KnowledgeEntry::query()->create([
            'user_id' => $user->id,
            'tenant_id' => 'tenant-alpha',
            'title' => 'Denied by Gate',
            'body' => 'This record fails the policy check.',
            'is_public' => true,
        ]);
        KnowledgeEntry::query()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => 'tenant-alpha',
            'title' => 'Other user launch plan',
            'body' => 'This belongs to another user.',
            'is_public' => true,
        ]);
        KnowledgeEntry::query()->create([
            'user_id' => $user->id,
            'tenant_id' => 'tenant-beta',
            'title' => 'Other tenant launch plan',
            'body' => 'This belongs to another tenant.',
            'is_public' => true,
        ]);

        Gate::define('view', fn (User $user, KnowledgeEntry $entry): bool => (int) $entry->user_id === (int) $user->id
            && $entry->title !== 'Denied by Gate');

        $this->actingAs($user);

        $context = app(ContextRegistry::class)->toPrompt('launch plan');

        $this->assertStringContainsString('Allowed tenant launch plan', $context);
        $this->assertStringNotContainsString('Denied by Gate', $context);
        $this->assertStringNotContainsString('Other user launch plan', $context);
        $this->assertStringNotContainsString('Other tenant launch plan', $context);

        Http::fake([
            'api.openai.com/v1/responses' => fn () => Http::response([
                'output_text' => "Open the allowed launch plan.\n[[model_mind_route key=\"knowledge.view\" entry=\"{$allowed->id}\"]]\n[[model_mind_route key=\"knowledge.view\" entry=\"{$deniedByGate->id}\"]]",
            ]),
        ]);

        $response = $this->postJson(route('model-mind.chat'), [
            'question' => 'Open launch plan',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('answer', 'Open the allowed launch plan.')
            ->assertJsonCount(1, 'actions')
            ->assertJsonPath('actions.0.url', url("/knowledge/{$allowed->id}"));
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
                    'usage' => [
                        'input_tokens' => 42,
                        'output_tokens' => 18,
                        'total_tokens' => 60,
                    ],
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
        $this->assertDatabaseHas('model_mind_events', [
            'type' => ModelMindEvent::TYPE_CHAT_COMPLETED,
            'provider' => 'openai',
            'provider_model' => 'gpt-test',
            'input_tokens' => 42,
            'output_tokens' => 18,
            'total_tokens' => 60,
        ]);
    }

    public function test_chat_endpoint_includes_sanitized_current_page_context(): void
    {
        Http::fake([
            'api.openai.com/v1/responses' => function (Request $request) {
                $input = (string) ($request['input'][0]['content'][0]['text'] ?? '');

                $this->assertStringContainsString('CURRENT PAGE CONTEXT', $input);
                $this->assertStringContainsString('URL: https://store.test/products/s24', $input);
                $this->assertStringContainsString('Title: Samsung Galaxy S24 Ultra', $input);
                $this->assertStringContainsString('Description: Flagship product page.', $input);
                $this->assertStringContainsString('Headings: Product details | Specifications', $input);
                $this->assertStringContainsString('Selected text:', $input);
                $this->assertStringContainsString('Visible page text:', $input);
                $this->assertStringContainsString('Large-screen Android flagship with AI tools.', $input);
                $this->assertStringNotContainsString('<strong>', $input);

                return Http::response([
                    'output_text' => 'This page is about the Samsung Galaxy S24 Ultra.',
                ]);
            },
        ]);

        $this->postJson(route('model-mind.chat'), [
            'question' => 'What do you think about this product?',
            'page_context' => [
                'url' => 'https://store.test/products/s24',
                'title' => '<strong>Samsung Galaxy S24 Ultra</strong>',
                'description' => 'Flagship product page.',
                'headings' => ['Product details', 'Specifications'],
                'selection' => '256GB storage and 6.8-inch display.',
                'content' => '<strong>Large-screen Android flagship with AI tools.</strong>',
                'locale' => 'en',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('answer', 'This page is about the Samsung Galaxy S24 Ultra.');
    }

    public function test_current_page_context_can_be_disabled_from_config(): void
    {
        config()->set('model-mind.page_context.enabled', false);

        Http::fake([
            'api.openai.com/v1/responses' => function (Request $request) {
                $input = (string) ($request['input'][0]['content'][0]['text'] ?? '');

                $this->assertStringNotContainsString('CURRENT PAGE CONTEXT', $input);
                $this->assertStringNotContainsString('Private draft page title', $input);

                return Http::response([
                    'output_text' => 'The page context feature is disabled.',
                ]);
            },
        ]);

        $this->postJson(route('model-mind.chat'), [
            'question' => 'Summarize this page',
            'page_context' => [
                'title' => 'Private draft page title',
                'content' => 'This should not reach the provider.',
            ],
        ])->assertOk();
    }

    public function test_chat_failures_are_tracked_without_breaking_error_response(): void
    {
        Http::fake([
            'api.openai.com/v1/responses' => fn () => Http::response(['error' => 'nope'], 500),
        ]);

        $this->postJson(route('model-mind.chat'), [
            'question' => 'Will this fail?',
        ])
            ->assertStatus(503)
            ->assertJsonPath('message', 'ModelMind is unavailable right now. Please try again soon.');

        $this->assertDatabaseHas('model_mind_events', [
            'type' => ModelMindEvent::TYPE_CHAT_FAILED,
            'provider' => 'openai',
            'provider_model' => 'gpt-test',
        ]);
    }

    public function test_feedback_and_action_clicks_are_tracked(): void
    {
        $session = ModelMindSession::query()->create();
        $assistantMessage = $session->messages()->create([
            'role' => ModelMindMessage::ROLE_ASSISTANT,
            'content' => 'Open the product page.',
            'metadata' => [
                'actions' => [[
                    'label' => 'View product',
                    'url' => url('/knowledge/1'),
                    'kind' => 'route',
                ]],
            ],
        ]);

        $this->postJson(route('model-mind.messages.feedback', $assistantMessage), [
            'session_id' => $session->uuid,
            'feedback' => ModelMindMessage::FEEDBACK_LIKED,
        ])->assertOk();

        $this->postJson(route('model-mind.actions.click'), [
            'session_id' => $session->uuid,
            'message_id' => $assistantMessage->uuid,
            'label' => 'View product',
            'url' => url('/knowledge/1'),
            'kind' => 'route',
            'source' => 'action',
            'index' => 0,
        ])->assertOk()->assertJsonPath('tracked', true);

        $this->assertDatabaseHas('model_mind_events', [
            'type' => ModelMindEvent::TYPE_FEEDBACK_SUBMITTED,
            'model_mind_message_id' => $assistantMessage->id,
        ]);
        $this->assertDatabaseHas('model_mind_events', [
            'type' => ModelMindEvent::TYPE_ACTION_CLICKED,
            'model_mind_message_id' => $assistantMessage->id,
        ]);
    }

    public function test_analytics_command_summarizes_usage(): void
    {
        $session = ModelMindSession::query()->create();
        $message = $session->messages()->create([
            'role' => ModelMindMessage::ROLE_ASSISTANT,
            'content' => 'Analytics answer.',
        ]);
        ModelMindEvent::query()->create([
            'model_mind_session_id' => $session->id,
            'model_mind_message_id' => $message->id,
            'type' => ModelMindEvent::TYPE_CHAT_COMPLETED,
            'provider' => 'openai',
            'provider_model' => 'gpt-test',
            'latency_ms' => 120,
            'input_tokens' => 10,
            'output_tokens' => 5,
            'total_tokens' => 15,
        ]);
        ModelMindEvent::query()->create([
            'model_mind_session_id' => $session->id,
            'model_mind_message_id' => $message->id,
            'type' => ModelMindEvent::TYPE_ACTION_CLICKED,
            'metadata' => ['label' => 'View product', 'kind' => 'route'],
        ]);

        Artisan::call('model-mind:analytics', [
            '--json' => true,
            '--days' => 1,
        ]);

        $payload = json_decode(Artisan::output(), true);

        $this->assertIsArray($payload);
        $this->assertSame(1, $payload['totals']['completed']);
        $this->assertSame(1, $payload['totals']['action_clicks']);
        $this->assertSame(15, $payload['totals']['total_tokens']);
        $this->assertSame('openai', $payload['providers'][0]['provider']);
    }

    public function test_extension_events_are_dispatched_for_chat_feedback_actions_and_learning(): void
    {
        Event::fake([
            ActionResolved::class,
            AnswerGenerated::class,
            FeedbackSubmitted::class,
            MemoryLearned::class,
            MessageSent::class,
        ]);

        $entry = KnowledgeEntry::query()->create([
            'title' => 'Extension Hooks',
            'body' => 'Packages can listen to ModelMind events and mirror activity into their own systems.',
            'is_public' => true,
        ]);

        Http::fake([
            'api.openai.com/v1/responses' => fn () => Http::response([
                'output_text' => "Open the public extension policy and review the supported listener hooks.\n[[model_mind_route key=\"knowledge.view\" entry=\"{$entry->id}\"]]",
                'usage' => [
                    'input_tokens' => 12,
                    'output_tokens' => 9,
                    'total_tokens' => 21,
                ],
            ]),
        ]);

        $response = $this->postJson(route('model-mind.chat'), [
            'question' => 'Which extension hooks are available?',
        ])->assertOk();

        Event::assertDispatched(MessageSent::class, fn (MessageSent $event): bool => $event->message->role === ModelMindMessage::ROLE_USER
            && $event->message->content === 'Which extension hooks are available?'
            && $event->question === 'Which extension hooks are available?'
            && $event->context['transport'] === 'json');

        Event::assertDispatched(ActionResolved::class, fn (ActionResolved $event): bool => $event->key === 'knowledge.view'
            && $event->parameters['entry'] === (string) $entry->id
            && $event->action['url'] === url("/knowledge/{$entry->id}"));

        Event::assertDispatched(AnswerGenerated::class, fn (AnswerGenerated $event): bool => $event->message->uuid === $response->json('message_id')
            && $event->answer === 'Open the public extension policy and review the supported listener hooks.'
            && $event->actions[0]['url'] === url("/knowledge/{$entry->id}")
            && $event->providerMetadata['provider'] === 'openai'
            && $event->providerMetadata['total_tokens'] === 21
            && $event->latencyMs >= 0);

        Event::assertDispatched(MemoryLearned::class, fn (MemoryLearned $event): bool => $event->memory->source === 'assistant_answer'
            && $event->created === true
            && $event->metadata['question'] === 'Which extension hooks are available?');

        $assistantMessage = ModelMindMessage::query()
            ->where('uuid', $response->json('message_id'))
            ->firstOrFail();

        $this->postJson(route('model-mind.messages.feedback', $assistantMessage), [
            'session_id' => $response->json('session_id'),
            'feedback' => ModelMindMessage::FEEDBACK_LIKED,
            'note' => 'Useful hook surface.',
        ])->assertOk();

        Event::assertDispatched(FeedbackSubmitted::class, fn (FeedbackSubmitted $event): bool => $event->message->is($assistantMessage)
            && $event->feedback === ModelMindMessage::FEEDBACK_LIKED
            && $event->note === 'Useful hook surface.');

        Event::assertDispatched(MemoryLearned::class, fn (MemoryLearned $event): bool => $event->memory->source === 'liked_answer'
            && $event->created === true
            && $event->metadata['message_id'] === $assistantMessage->uuid);
    }

    public function test_chat_endpoint_returns_source_citations_for_used_model_records(): void
    {
        $entry = KnowledgeEntry::query()->create([
            'title' => 'Support policy',
            'body' => 'Support replies happen within one business day.',
            'password' => 'never-share',
            'is_public' => true,
        ]);

        $context = app(ContextRegistry::class)->context('support policy');
        $sourceKey = data_get($context, 'question_context.models.0.rows.0.model_mind_source.key')
            ?? data_get($context, 'models.0.rows.0.model_mind_source.key');

        $this->assertIsString($sourceKey);

        Http::fake([
            'api.openai.com/v1/responses' => function (Request $request) use ($sourceKey) {
                $instructions = (string) $request['instructions'];

                $this->assertStringContainsString('Source citations:', $instructions);
                $this->assertStringContainsString('model_mind_source', $instructions);
                $this->assertStringContainsString($sourceKey, $instructions);
                $this->assertStringContainsString('Support policy', $instructions);

                return Http::response([
                    'output_text' => "Support replies happen within one business day.\n[[model_mind_source key=\"{$sourceKey}\" columns=\"title, body\"]]",
                ]);
            },
        ]);

        $response = $this->postJson(route('model-mind.chat'), [
            'question' => 'How fast is support?',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('answer', 'Support replies happen within one business day.')
            ->assertJsonCount(1, 'citations')
            ->assertJsonPath('citations.0.model', 'Knowledge entries')
            ->assertJsonPath('citations.0.record', 'Support policy')
            ->assertJsonPath('citations.0.source', 'Knowledge entries: Support policy')
            ->assertJsonPath('citations.0.columns.0', 'title')
            ->assertJsonPath('citations.0.columns.1', 'body')
            ->assertJsonPath('citations.0.action.url', url("/knowledge/{$entry->id}"));

        $assistantMessage = ModelMindMessage::query()
            ->where('role', ModelMindMessage::ROLE_ASSISTANT)
            ->firstOrFail();

        $this->assertSame('Support policy', $assistantMessage->metadata['citations'][0]['record'] ?? null);

        $this->getJson(route('model-mind.session', [
            'session_id' => $response->json('session_id'),
        ]))
            ->assertOk()
            ->assertJsonPath('messages.1.citations.0.record', 'Support policy')
            ->assertJsonPath('messages.1.citations.0.action.url', url("/knowledge/{$entry->id}"));
    }

    public function test_chat_endpoint_adds_question_relevant_records_outside_static_context(): void
    {
        config()->set('model-mind.security.max_rows_per_model', 1);
        config()->set('model-mind.retrieval.limit', 3);

        KnowledgeEntry::query()->create([
            'title' => 'Generic onboarding',
            'body' => 'This record is first in the static context.',
            'is_public' => true,
        ]);
        $entry = KnowledgeEntry::query()->create([
            'title' => 'Samsung Galaxy S24 Ultra',
            'body' => 'Large-screen Android flagship with S Pen, AI tools, and advanced camera zoom.',
            'is_public' => true,
        ]);

        Http::fake([
            'api.openai.com/v1/responses' => function (Request $request) use ($entry) {
                $instructions = (string) $request['instructions'];

                $this->assertStringContainsString('question_context', $instructions);
                $this->assertStringContainsString('Samsung Galaxy S24 Ultra', $instructions);
                $this->assertStringContainsString('Large-screen Android flagship', $instructions);
                $this->assertStringContainsString("[[model_mind_route key=\\\"knowledge.view\\\" entry=\\\"{$entry->id}\\\"]]", $instructions);

                return Http::response([
                    'output_text' => "Samsung Galaxy S24 Ultra is available in the enabled context.\n[[model_mind_route key=\"knowledge.view\" entry=\"{$entry->id}\"]]",
                ]);
            },
        ]);

        $response = $this->postJson(route('model-mind.chat'), [
            'question' => 'samsung s24?',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('answer', 'Samsung Galaxy S24 Ultra is available in the enabled context.')
            ->assertJsonPath('actions.0.kind', 'route')
            ->assertJsonPath('actions.0.url', url("/knowledge/{$entry->id}"));
    }

    public function test_ranked_retrieval_prefers_weighted_column_matches(): void
    {
        config()->set('model-mind.models', [
            KnowledgeEntry::class => [
                'enabled' => true,
                'columns' => 'auto',
                'search_columns' => [
                    'title' => 10,
                    'body' => 1,
                ],
                'limit' => 10,
                'order_by' => ['id' => 'asc'],
            ],
        ]);

        KnowledgeEntry::query()->create([
            'title' => 'Generic mobile policy',
            'body' => 'Samsung Galaxy S24 Ultra appears only in a low-priority body field.',
            'is_public' => true,
        ]);
        KnowledgeEntry::query()->create([
            'title' => 'Samsung Galaxy S24 Ultra',
            'body' => 'A product title match should rank ahead of a body-only match.',
            'is_public' => true,
        ]);

        $context = app(ContextRegistry::class)->context('samsung s24');
        $retrieval = data_get($context, 'question_context.models.0.retrieval');
        $rows = data_get($context, 'question_context.models.0.rows');

        $this->assertSame('ranked_database', $retrieval['engine']);
        $this->assertSame(['title' => 10.0, 'body' => 1.0], $retrieval['weights']);
        $this->assertSame('Samsung Galaxy S24 Ultra', $rows[0]['title']);
        $this->assertContains('title', $retrieval['scores'][0]['columns']);
    }

    public function test_ranked_retrieval_uses_fuzzy_and_multilingual_normalization(): void
    {
        KnowledgeEntry::query()->create([
            'title' => 'Samsung Galaxy S24 Ultra',
            'body' => 'A flagship phone record that should match misspelled searches.',
            'is_public' => true,
        ]);
        KnowledgeEntry::query()->create([
            'title' => 'خطة الدَّعْم',
            'body' => 'Arabic diacritics should normalize for retrieval.',
            'is_public' => true,
        ]);

        $fuzzyContext = app(ContextRegistry::class)->context('samsng galxy');
        $arabicContext = app(ContextRegistry::class)->context('خطة الدعم');

        $this->assertSame('Samsung Galaxy S24 Ultra', data_get($fuzzyContext, 'question_context.models.0.rows.0.title'));
        $this->assertSame('خطة الدَّعْم', data_get($arabicContext, 'question_context.models.0.rows.0.title'));
    }

    public function test_retrieval_can_use_configured_vector_searcher(): void
    {
        config()->set('model-mind.retrieval.vector.enabled', true);
        config()->set('model-mind.retrieval.vector.searcher', FakeVectorSearcher::class);

        $first = KnowledgeEntry::query()->create([
            'title' => 'First vector result',
            'body' => 'Returned second by fake vector search.',
            'is_public' => true,
        ]);
        $second = KnowledgeEntry::query()->create([
            'title' => 'Second vector result',
            'body' => 'Returned first by fake vector search.',
            'is_public' => true,
        ]);
        FakeVectorSearcher::$keys = [$second->id, $first->id];

        $context = app(ContextRegistry::class)->context('semantic vector question');

        $this->assertSame('vector', data_get($context, 'question_context.models.0.retrieval.engine'));
        $this->assertSame('Second vector result', data_get($context, 'question_context.models.0.rows.0.title'));
        $this->assertSame('First vector result', data_get($context, 'question_context.models.0.rows.1.title'));
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

    public function test_expired_sessions_are_reset(): void
    {
        config()->set('model-mind.memory.session_lifetime_minutes', 5);

        $expiredSession = ModelMindSession::query()->create([
            'last_interaction_at' => now()->subMinutes(10),
        ]);
        $expiredSession->messages()->create([
            'role' => ModelMindMessage::ROLE_USER,
            'content' => 'Old question',
        ]);

        $this->getJson(route('model-mind.session', [
            'session_id' => $expiredSession->uuid,
        ]))
            ->assertOk()
            ->assertJsonPath('session_id', null)
            ->assertJsonPath('expires_at', null)
            ->assertJsonPath('expired', true)
            ->assertJsonCount(0, 'messages');

        Http::fake([
            'api.openai.com/v1/responses' => fn () => Http::response([
                'output_text' => 'Fresh session answer.',
            ]),
        ]);

        $response = $this->postJson(route('model-mind.chat'), [
            'session_id' => $expiredSession->uuid,
            'question' => 'Start again',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('answer', 'Fresh session answer.')
            ->assertJsonStructure(['session_id', 'expires_at']);

        $this->assertNotSame($expiredSession->uuid, $response->json('session_id'));
        $this->assertDatabaseCount('model_mind_sessions', 2);
        $this->assertSame(1, $expiredSession->messages()->count());
    }

    public function test_route_actions_can_use_record_label_columns_and_templates(): void
    {
        config()->set('model-mind.models', [
            KnowledgeEntry::class => [
                'enabled' => true,
                'columns' => 'auto',
                'limit' => 10,
                'route_actions' => [
                    'knowledge.view' => [
                        'label' => 'Open knowledge',
                        'label_column' => 'title',
                        'label_template' => 'Open {title}',
                        'route' => 'knowledge.show',
                        'parameters' => ['entry' => 'id'],
                    ],
                ],
            ],
        ]);

        $entry = KnowledgeEntry::query()->create([
            'title' => 'Samsung Galaxy S24 Ultra',
            'body' => 'Large-screen Android flagship.',
            'is_public' => true,
        ]);

        $context = app(ContextRegistry::class)->toPrompt('samsung s24');

        $this->assertStringContainsString('Open Samsung Galaxy S24 Ultra', $context);

        Http::fake([
            'api.openai.com/v1/responses' => fn () => Http::response([
                'output_text' => "Here is the product.\n[[model_mind_route key=\"knowledge.view\" entry=\"{$entry->id}\"]]",
            ]),
        ]);

        $response = $this->postJson(route('model-mind.chat'), [
            'question' => 'samsung s24?',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('answer', 'Here is the product.')
            ->assertJsonPath('actions.0.label', 'Open Samsung Galaxy S24 Ultra')
            ->assertJsonPath('actions.0.kind', 'route')
            ->assertJsonPath('actions.0.url', url("/knowledge/{$entry->id}"));
    }

    public function test_route_actions_are_inferred_from_multilingual_answers(): void
    {
        config()->set('model-mind.models', [
            KnowledgeEntry::class => [
                'enabled' => true,
                'columns' => 'auto',
                'limit' => 10,
                'route_actions' => [
                    'knowledge.view' => [
                        'label' => 'Open product',
                        'label_column' => 'title',
                        'label_template' => 'View {title}',
                        'route' => 'knowledge.show',
                        'parameters' => ['entry' => 'id'],
                    ],
                ],
            ],
        ]);

        $entry = KnowledgeEntry::query()->create([
            'title' => 'Apple iPhone 15 128GB',
            'body' => 'A premium smartphone with 5G.',
            'is_public' => true,
        ]);

        Http::fake([
            'api.openai.com/v1/responses' => fn () => Http::response([
                'output_text' => 'يمكنك فتح صفحة عرض المنتج Apple iPhone 15 128GB للحصول على التفاصيل.',
            ]),
        ]);

        $response = $this->postJson(route('model-mind.chat'), [
            'question' => 'أرسل رابط المنتج',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('answer', 'يمكنك فتح صفحة عرض المنتج Apple iPhone 15 128GB للحصول على التفاصيل.')
            ->assertJsonPath('actions.0.label', 'View Apple iPhone 15 128GB')
            ->assertJsonPath('actions.0.kind', 'route')
            ->assertJsonPath('actions.0.url', url("/knowledge/{$entry->id}"))
            ->assertJsonPath('citations.0.record', 'Apple iPhone 15 128GB')
            ->assertJsonPath('citations.0.action.url', url("/knowledge/{$entry->id}"));
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
