<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateRotationGroupsForeignKey extends Migration
{
    public function up()
    {
        // Drop the existing foreign key
        Schema::table('rotation_groups', function (Blueprint $table) {
            $table->dropForeign(['security_post_id']);
        });
        
        // Add new foreign key with ON DELETE SET NULL
        Schema::table('rotation_groups', function (Blueprint $table) {
            $table->foreign('security_post_id')
                  ->references('id')
                  ->on('security_posts')
                  ->onDelete('set null');
        });
    }
    
    public function down()
    {
        // Drop the modified foreign key
        Schema::table('rotation_groups', function (Blueprint $table) {
            $table->dropForeign(['security_post_id']);
        });
        
        // Restore original foreign key
        Schema::table('rotation_groups', function (Blueprint $table) {
            $table->foreign('security_post_id')
                  ->references('id')
                  ->on('security_posts');
        });
    }
}