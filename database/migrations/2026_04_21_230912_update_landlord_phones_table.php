<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateLandlordPhonesTable extends Migration
{
    public function up()
    {
        Schema::table('landlord_phones', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('landlord_phones', 'is_primary')) {
                $table->boolean('is_primary')->default(false);
            }
            
            if (!Schema::hasColumn('landlord_phones', 'type')) {
                $table->string('type')->default('additional')->comment('primary, additional, work, home, etc.');
            }
            
            if (!Schema::hasColumn('landlord_phones', 'verified_at')) {
                $table->timestamp('verified_at')->nullable();
            }
            
            if (!Schema::hasColumn('landlord_phones', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            }
            
            if (!Schema::hasColumn('landlord_phones', 'verification_code')) {
                $table->string('verification_code')->nullable();
            }
            
            if (!Schema::hasColumn('landlord_phones', 'verification_sent_at')) {
                $table->timestamp('verification_sent_at')->nullable();
            }
            
            if (!Schema::hasColumn('landlord_phones', 'notes')) {
                $table->text('notes')->nullable();
            }
            
            if (!Schema::hasColumn('landlord_phones', 'status')) {
                $table->enum('status', ['active', 'inactive', 'pending_verification', 'blocked'])->default('active');
            }
            
            if (!Schema::hasColumn('landlord_phones', 'last_used_at')) {
                $table->timestamp('last_used_at')->nullable();
            }
            
            // Add indexes
            $table->index('phone_number');
            $table->index('is_primary');
            $table->index('status');
            $table->index('verified_at');
        });
    }

    public function down()
    {
        Schema::table('landlord_phones', function (Blueprint $table) {
            $table->dropColumn([
                'is_primary',
                'type',
                'verified_at',
                'verified_by',
                'verification_code',
                'verification_sent_at',
                'notes',
                'status',
                'last_used_at'
            ]);
        });
    }
}