<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('property_photos')) {
            Schema::create('property_photos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->constrained()->onDelete('cascade');
                $table->string('photo_path');
                $table->string('thumbnail_path')->nullable();
                $table->string('medium_path')->nullable();
                $table->string('photo_url')->nullable();
                $table->string('thumbnail_url')->nullable();
                $table->string('medium_url')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->integer('sort_order')->default(0);
                $table->string('caption')->nullable();
                $table->string('file_name')->nullable();
                $table->bigInteger('file_size')->nullable();
                $table->string('mime_type')->nullable();
                $table->json('dimensions')->nullable();
                $table->string('alt_text')->nullable();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
                $table->json('metadata')->nullable();
                $table->timestamps();
                
                $table->index('property_id');
                $table->index('is_primary');
                $table->index('sort_order');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('property_photos');
    }
};