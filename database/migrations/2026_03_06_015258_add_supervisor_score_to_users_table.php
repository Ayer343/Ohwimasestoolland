<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add supervisor_score column only
            if (!Schema::hasColumn('users', 'supervisor_score')) {
                $table->integer('supervisor_score')
                      ->default(0)
                      ->after('supervisor_level') // or wherever you want it
                      ->comment('Performance score for supervisors');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'supervisor_score')) {
                $table->dropColumn('supervisor_score');
            }
        });
    }
};