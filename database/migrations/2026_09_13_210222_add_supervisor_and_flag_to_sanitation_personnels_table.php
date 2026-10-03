<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_supervisor_and_flag_to_sanitation_personnels_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sanitation_personnels', function (Blueprint $table) {
            if (!Schema::hasColumn('sanitation_personnels', 'supervisor_id')) {
                $table->foreignId('supervisor_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('sanitation_personnels')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('sanitation_personnels', 'can_be_supervisor')) {
                $table->boolean('can_be_supervisor')
                    ->default(false)
                    ->after('role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sanitation_personnels', function (Blueprint $table) {
            if (Schema::hasColumn('sanitation_personnels', 'supervisor_id')) {
                $table->dropForeign(['supervisor_id']);
                $table->dropColumn('supervisor_id');
            }

            if (Schema::hasColumn('sanitation_personnels', 'can_be_supervisor')) {
                $table->dropColumn('can_be_supervisor');
            }
        });
    }
};