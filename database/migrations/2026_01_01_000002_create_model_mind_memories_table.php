<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mbs\ModelMind\Support\Database\TableNames;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists(TableNames::memories());
    }
};
