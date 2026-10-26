<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- users: drop the bogus rank/score/level columns ----
        Schema::table('users', function (Blueprint $table) {
            $drop = [];
            foreach ([
                'security_supervisor_rank',
                'security_supervisor_level',
                'security_supervisor_role',
                'security_supervisor_score',
            ] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop) {
                $table->dropColumn($drop);
            }
        });

        // ---- supervisor assignments: NO community_id, add title ----
        Schema::table('security_supervisor_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('security_supervisor_assignments', 'title')) {
                $table->string('title')->default('Head of Security')->after('assigned_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('security_supervisor_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('security_supervisor_assignments', 'title')) {
                $table->dropColumn('title');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('security_supervisor_level')->nullable();
            $table->integer('security_supervisor_score')->nullable();
        });
    }
};