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
        Schema::table('security_supervisor_assignments', function (Blueprint $table) {
            // Add approval-related columns
            if (!Schema::hasColumn('security_supervisor_assignments', 'approval_status')) {
                $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('approved')->after('is_active');
            }
            
            if (!Schema::hasColumn('security_supervisor_assignments', 'requires_approval')) {
                $table->boolean('requires_approval')->default(false)->after('approval_status');
            }
            
            if (!Schema::hasColumn('security_supervisor_assignments', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('requires_approval');
            }
            
            if (!Schema::hasColumn('security_supervisor_assignments', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            
            // Add indexes for performance
            $table->index('approval_status');
            $table->index('approved_by');
            $table->index('requires_approval');
            
            // Add foreign key constraint if approved_by references users table
            if (!Schema::hasColumn('security_supervisor_assignments', 'approved_by')) {
                // Foreign key is added with the column above
            } else {
                // Only add foreign key if the column exists and we're sure it references users
                $table->foreign('approved_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security_supervisor_assignments', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'approval_status',
                'requires_approval',
                'approved_by',
                'approved_at'
            ]);
        });
    }
};