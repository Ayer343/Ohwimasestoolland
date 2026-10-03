<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // First, check the current column definition
        Schema::table('post_qr_codes', function (Blueprint $table) {
            // Drop the column if it has a default value
            $table->dropColumn('expires_at');
        });

        // Re-add it with correct default
        Schema::table('post_qr_codes', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->default(null)->after('code_type');
        });

        // Fix any existing static codes that got incorrect expires_at
        DB::table('post_qr_codes')
            ->where('code_type', 'static')
            ->update(['expires_at' => null]);
    }

    public function down()
    {
        Schema::table('post_qr_codes', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });

        Schema::table('post_qr_codes', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('code_type');
        });
    }
};