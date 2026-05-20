<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mbs\ModelMind\Support\Database\TableNames;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable(TableNames::sessions())) {
            Schema::create(TableNames::sessions(), function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->longText('conversation_summary')->nullable();
                $table->unsignedInteger('message_count')->default(0);
                $table->unsignedInteger('compacted_message_count')->default(0);
                $table->timestamp('compacted_at')->nullable();
                $table->timestamp('last_interaction_at')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable(TableNames::messages())) {
            Schema::create(TableNames::messages(), function (Blueprint $table): void {
                $table->id();
                $table->foreignId('model_mind_session_id');
                $table->uuid('uuid')->unique();
                $table->string('role')->index();
                $table->longText('content');
                $table->json('metadata')->nullable();
                $table->string('feedback')->nullable()->index();
                $table->text('feedback_note')->nullable();
                $table->timestamp('feedback_at')->nullable();
                $table->timestamps();

                $table->index(['model_mind_session_id', 'created_at'], 'model_mind_messages_session_created_index');
                $table->foreign('model_mind_session_id', 'model_mind_messages_session_fk')
                    ->references('id')
                    ->on(TableNames::sessions())
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable(TableNames::memories())) {
            Schema::create(TableNames::memories(), function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('source')->index();
                $table->string('title')->nullable();
                $table->longText('content');
                $table->char('content_hash', 64)->unique();
                $table->json('metadata')->nullable();
                $table->unsignedSmallInteger('weight')->default(1);
                $table->timestamp('learned_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable(TableNames::events())) {
            Schema::create(TableNames::events(), function (Blueprint $table): void {
                $table->id();
                $table->foreignId('model_mind_session_id')->nullable();
                $table->foreignId('model_mind_message_id')->nullable();
                $table->uuid('uuid')->unique();
                $table->string('type')->index();
                $table->string('provider')->nullable()->index();
                $table->string('provider_model')->nullable();
                $table->unsignedInteger('latency_ms')->nullable()->index();
                $table->unsignedInteger('input_tokens')->nullable();
                $table->unsignedInteger('output_tokens')->nullable();
                $table->unsignedInteger('total_tokens')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at')->nullable()->index();
                $table->timestamps();

                $table->index(['type', 'occurred_at'], 'model_mind_events_type_occurred_index');
                $table->index(['provider', 'provider_model'], 'model_mind_events_provider_model_index');
                $table->foreign('model_mind_session_id', 'model_mind_events_session_fk')
                    ->references('id')
                    ->on(TableNames::sessions())
                    ->nullOnDelete();
                $table->foreign('model_mind_message_id', 'model_mind_events_message_fk')
                    ->references('id')
                    ->on(TableNames::messages())
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(TableNames::events());
        Schema::dropIfExists(TableNames::memories());
        Schema::dropIfExists(TableNames::messages());
        Schema::dropIfExists(TableNames::sessions());
    }
};
