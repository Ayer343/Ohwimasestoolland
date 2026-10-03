<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRotationGroupsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('rotation_groups')) {
            Schema::create('rotation_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type'); // 'day', 'night', 'evening', 'mixed'
                $table->text('description')->nullable();
                $table->longText('configuration')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('type');
                $table->index('is_active');
            });
            
            Schema::table('rotation_groups', function (Blueprint $table) {
                $table->foreign('created_by')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('rotation_groups');
    }
}