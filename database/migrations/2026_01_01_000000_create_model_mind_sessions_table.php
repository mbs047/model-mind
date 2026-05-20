<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mbs\ModelMind\Support\Database\TableNames;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists(TableNames::sessions());
    }
};
