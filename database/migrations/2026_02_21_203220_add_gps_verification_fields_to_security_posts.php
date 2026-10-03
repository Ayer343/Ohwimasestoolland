<?php
// database/migrations/xxxx_xx_xx_add_gps_verification_fields_to_security_posts.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGpsVerificationFieldsToSecurityPosts extends Migration
{
    public function up()
    {
        Schema::table('security_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('security_posts', 'checkin_radius')) {
                $table->integer('checkin_radius')->default(100)->after('max_personnel');
            }
            
            if (!Schema::hasColumn('security_posts', 'require_gps_verification')) {
                $table->boolean('require_gps_verification')->default(false)->after('checkin_radius');
            }
            
            if (!Schema::hasColumn('security_posts', 'checkin_grace_period')) {
                $table->integer('checkin_grace_period')->default(5)->after('require_gps_verification');
            }
        });
    }

    public function down()
    {
        Schema::table('security_posts', function (Blueprint $table) {
            $table->dropColumn([
                'checkin_radius', 
                'require_gps_verification',
                'checkin_grace_period'
            ]);
        });
    }
}