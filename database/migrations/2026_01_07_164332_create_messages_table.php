<?php
// database/migrations/2026_01_07_164332_create_messages_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('body')->nullable();
            $table->string('type', 20)->default('text');
            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('edited_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['conversation_id', 'created_at'], 'messages_conv_created_idx');
            $table->index(['user_id', 'created_at'],          'messages_user_created_idx');
            $table->index('created_at',                       'messages_created_idx');
            $table->index('type',                             'messages_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};