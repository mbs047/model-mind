<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mbs\ModelMind\Support\Database\TableNames;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists(TableNames::messages());
    }
};
