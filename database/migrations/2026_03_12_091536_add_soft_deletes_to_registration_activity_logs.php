<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_activity_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('registration_activity_logs', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('registration_activity_logs', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};