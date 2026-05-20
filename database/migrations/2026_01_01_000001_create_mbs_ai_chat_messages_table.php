<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mbs_ai_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mbs_ai_chat_session_id');
            $table->uuid('uuid')->unique();
            $table->string('role')->index();
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->string('feedback')->nullable()->index();
            $table->text('feedback_note')->nullable();
            $table->timestamp('feedback_at')->nullable();
            $table->timestamps();

            $table->index(['mbs_ai_chat_session_id', 'created_at'], 'mbs_ai_chat_messages_session_created_index');
            $table->foreign('mbs_ai_chat_session_id', 'mbs_ai_chat_messages_session_fk')
                ->references('id')
                ->on('mbs_ai_chat_sessions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mbs_ai_chat_messages');
    }
};
