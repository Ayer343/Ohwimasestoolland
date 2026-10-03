<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if the column exists before adding
        if (!Schema::hasColumn('construction_milestones', 'completed_at')) {
            Schema::table('construction_milestones', function (Blueprint $table) {
                $table->timestamp('completed_at')->nullable()->after('status');
                $table->index('completed_at');
            });
        }

        // Also check if we need to add other common milestone columns
        if (!Schema::hasColumn('construction_milestones', 'due_date')) {
            Schema::table('construction_milestones', function (Blueprint $table) {
                $table->timestamp('due_date')->nullable()->after('status');
                $table->index('due_date');
            });
        }

        // Add completion_notes if it doesn't exist
        if (!Schema::hasColumn('construction_milestones', 'completion_notes')) {
            Schema::table('construction_milestones', function (Blueprint $table) {
                $table->text('completion_notes')->nullable()->after('completed_at');
            });
        }

        // Add evidence_path if it doesn't exist
        if (!Schema::hasColumn('construction_milestones', 'evidence_path')) {
            Schema::table('construction_milestones', function (Blueprint $table) {
                $table->string('evidence_path')->nullable()->after('completion_notes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('construction_milestones', function (Blueprint $table) {
            $columns = ['completed_at', 'due_date', 'completion_notes', 'evidence_path'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('construction_milestones', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};