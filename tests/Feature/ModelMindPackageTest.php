<?php

namespace Mbs\ModelMind\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mbs\ModelMind\Models\ModelMindMemory;
use Mbs\ModelMind\Models\ModelMindMessage;
use Mbs\ModelMind\Models\ModelMindSession;
use Mbs\ModelMind\Support\Context\ContextRegistry;
use Mbs\ModelMind\Support\Database\TableNames;
use Mbs\ModelMind\Tests\Fixtures\KnowledgeEntry;
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
        $this->assertStringContainsString('Sources', $html);
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
