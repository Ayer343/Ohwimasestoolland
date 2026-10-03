<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSecurityPostsTable extends Migration
{
    public function up()
    {
        Schema::create('security_posts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('type', ['main_gate', 'internal_gate', 'patrol_point', 'monitoring_post']);
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('digital_address')->nullable();
            $table->json('equipment')->nullable(); // e.g., ['cctv', 'intercom', 'barrier_gate']
            $table->integer('max_personnel')->default(2);
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_checkin')->default(true);
            $table->json('working_hours')->nullable(); // e.g., {"start": "06:00", "end": "18:00"}
            $table->json('restrictions')->nullable(); // e.g., {"vehicles": ["trucks", "commercial"], "access_level": "resident_only"}
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('security_posts');
    }
}