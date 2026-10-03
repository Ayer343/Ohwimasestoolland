<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_contracts', function (Blueprint $table) {
            // ✅ Add missing invitation tracking fields
            if (!Schema::hasColumn('construction_contracts', 'contractor_invitation_sent_at')) {
                $table->timestamp('contractor_invitation_sent_at')->nullable()->after('contractor_invitation_attempts');
            }
            
            if (!Schema::hasColumn('construction_contracts', 'contractor_invitation_channels')) {
                $table->json('contractor_invitation_channels')->nullable()->after('contractor_invitation_sent_at');
            }
            
            if (!Schema::hasColumn('construction_contracts', 'contractor_invitation_type')) {
                $table->string('contractor_invitation_type')->nullable()->default('welcome')->after('contractor_invitation_channels');
            }
            
            if (!Schema::hasColumn('construction_contracts', 'contractor_invitation_accepted_at')) {
                $table->timestamp('contractor_invitation_accepted_at')->nullable()->after('contractor_invitation_type');
            }
            
            if (!Schema::hasColumn('construction_contracts', 'contractor_user_id')) {
                $table->foreignId('contractor_user_id')->nullable()->after('contractor_invitation_accepted_at')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('construction_contracts', function (Blueprint $table) {
            $columns = [
                'contractor_invitation_sent_at',
                'contractor_invitation_channels',
                'contractor_invitation_type',
                'contractor_invitation_accepted_at',
                'contractor_user_id',
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('construction_contracts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};