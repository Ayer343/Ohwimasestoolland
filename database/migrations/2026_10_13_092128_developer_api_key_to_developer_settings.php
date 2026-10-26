<?php
// 2026_10_13_091742_add_developer_api_key_to_developer_settings.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('developer_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('developer_settings', 'developer_api_key')) {
                // The API key itself is shown once and discarded; the
                // column exists so the settings blade can display "last
                // generated" info and so the model can be honest about
                // what it stores.
                $table->string('developer_api_key', 128)
                    ->nullable()
                    ->after('developer_secret_key');

                $table->timestamp('api_key_last_generated')
                    ->nullable()
                    ->after('developer_api_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('developer_settings', function (Blueprint $table) {
            if (Schema::hasColumn('developer_settings', 'api_key_last_generated')) {
                $table->dropColumn('api_key_last_generated');
            }
            if (Schema::hasColumn('developer_settings', 'developer_api_key')) {
                $table->dropColumn('developer_api_key');
            }
        });
    }
};