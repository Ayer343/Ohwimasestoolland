<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('content');
            $table->string('category')->nullable()->index();
            $table->string('status')->default('active')->index();
            $table->json('variables')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->string('provider_template_id')->nullable()->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};