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
        // ============================================ //
        // ADD MISSING COLUMNS TO construction_milestones //
        // ============================================ //
        if (Schema::hasTable('construction_milestones')) {
            
            // Add completed_at column
            if (!Schema::hasColumn('construction_milestones', 'completed_at')) {
                Schema::table('construction_milestones', function (Blueprint $table) {
                    $table->timestamp('completed_at')->nullable()->after('status');
                    $table->index('completed_at');
                });
            }

            // Add due_date column if it doesn't exist (check other possible names)
            if (!Schema::hasColumn('construction_milestones', 'due_date') &&
                !Schema::hasColumn('construction_milestones', 'completion_date') &&
                !Schema::hasColumn('construction_milestones', 'estimated_completion_date') &&
                !Schema::hasColumn('construction_milestones', 'milestone_date') &&
                !Schema::hasColumn('construction_milestones', 'target_date')) {
                
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

        // ============================================ //
        // ADD MISSING COLUMNS TO construction_contracts //
        // ============================================ //
        if (Schema::hasTable('construction_contracts')) {
            
            // Add actual_completion_date column
            if (!Schema::hasColumn('construction_contracts', 'actual_completion_date')) {
                Schema::table('construction_contracts', function (Blueprint $table) {
                    $table->timestamp('actual_completion_date')->nullable()->after('estimated_completion_date');
                    $table->index('actual_completion_date');
                });
            }

            // Add contract_start_date if it doesn't exist
            if (!Schema::hasColumn('construction_contracts', 'contract_start_date')) {
                Schema::table('construction_contracts', function (Blueprint $table) {
                    $table->timestamp('contract_start_date')->nullable()->after('contract_date');
                    $table->index('contract_start_date');
                });
            }

            // Add contract_end_date if it doesn't exist
            if (!Schema::hasColumn('construction_contracts', 'contract_end_date')) {
                Schema::table('construction_contracts', function (Blueprint $table) {
                    $table->timestamp('contract_end_date')->nullable()->after('contract_start_date');
                    $table->index('contract_end_date');
                });
            }

            // Add progress_percentage if it doesn't exist
            if (!Schema::hasColumn('construction_contracts', 'progress_percentage')) {
                Schema::table('construction_contracts', function (Blueprint $table) {
                    $table->integer('progress_percentage')->default(0)->after('contract_amount');
                });
            }

            // Add estimated_completion_date if it doesn't exist
            if (!Schema::hasColumn('construction_contracts', 'estimated_completion_date')) {
                Schema::table('construction_contracts', function (Blueprint $table) {
                    $table->timestamp('estimated_completion_date')->nullable()->after('contract_end_date');
                    $table->index('estimated_completion_date');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop columns from construction_milestones
        if (Schema::hasTable('construction_milestones')) {
            Schema::table('construction_milestones', function (Blueprint $table) {
                $columns = ['completed_at', 'due_date', 'completion_notes', 'evidence_path'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('construction_milestones', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        // Drop columns from construction_contracts
        if (Schema::hasTable('construction_contracts')) {
            Schema::table('construction_contracts', function (Blueprint $table) {
                $columns = ['actual_completion_date', 'contract_start_date', 'contract_end_date', 'progress_percentage', 'estimated_completion_date'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('construction_contracts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};