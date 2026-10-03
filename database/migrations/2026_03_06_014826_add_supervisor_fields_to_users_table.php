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
            // Add can_be_supervisor boolean flag
            if (!Schema::hasColumn('users', 'can_be_supervisor')) {
                $table->boolean('can_be_supervisor')
                      ->default(false)
                      ->after('remember_token')
                      ->comment('Whether user can be assigned as supervisor');
            }
            
            // Add supervisor_level column
            if (!Schema::hasColumn('users', 'supervisor_level')) {
                $table->tinyInteger('supervisor_level')
                      ->default(0)
                      ->after('can_be_supervisor')
                      ->comment('0=Not supervisor, 1=Level1, 2=Level2, etc.');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['can_be_supervisor', 'supervisor_level']);
        });
    }
};