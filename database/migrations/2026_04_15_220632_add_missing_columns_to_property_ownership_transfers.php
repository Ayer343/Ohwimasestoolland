<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('property_ownership_transfers', function (Blueprint $table) {
            // Add rejected_by_id if missing
            if (!Schema::hasColumn('property_ownership_transfers', 'rejected_by_id')) {
                $table->foreignId('rejected_by_id')->nullable()->after('completed_by_id')->constrained('users')->nullOnDelete();
            }
            
            // Add indexes for better performance on completed transfers
            $table->index(['status', 'completed_at'], 'idx_status_completed_at');
            $table->index(['current_landlord_id', 'status'], 'idx_current_landlord_status');
            $table->index(['new_landlord_id', 'status'], 'idx_new_landlord_status');
        });
    }

    public function down()
    {
        Schema::table('property_ownership_transfers', function (Blueprint $table) {
            $table->dropForeign(['rejected_by_id']);
            $table->dropColumn('rejected_by_id');
            $table->dropIndex('idx_status_completed_at');
            $table->dropIndex('idx_current_landlord_status');
            $table->dropIndex('idx_new_landlord_status');
        });
    }
};