<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReversalFieldsToPropertyOwnershipTransfers extends Migration
{
    public function up()
    {
        Schema::table('property_ownership_transfers', function (Blueprint $table) {
            $table->timestamp('reversal_requested_at')->nullable();
            $table->unsignedBigInteger('reversal_requested_by')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamp('reversal_processed_at')->nullable();
            $table->unsignedBigInteger('reversal_processed_by')->nullable();
            $table->string('reversal_status')->nullable()->default('none'); // none, pending, approved, rejected, completed
            $table->text('reversal_admin_notes')->nullable();
            $table->timestamp('reversal_deadline')->nullable();
            $table->boolean('is_reversed')->default(false);
            $table->unsignedBigInteger('reversal_transfer_id')->nullable(); // Link to new reversal transfer record
        });
    }

    public function down()
    {
        Schema::table('property_ownership_transfers', function (Blueprint $table) {
            $table->dropColumn([
                'reversal_requested_at',
                'reversal_requested_by',
                'reversal_reason',
                'reversal_processed_at',
                'reversal_processed_by',
                'reversal_status',
                'reversal_admin_notes',
                'reversal_deadline',
                'is_reversed',
                'reversal_transfer_id'
            ]);
        });
    }
}