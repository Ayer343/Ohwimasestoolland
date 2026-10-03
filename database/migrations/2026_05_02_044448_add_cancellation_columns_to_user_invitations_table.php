<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('user_invitations', function (Blueprint $table) {
            // Check if columns don't exist before adding
            if (!Schema::hasColumn('user_invitations', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }
            
            if (!Schema::hasColumn('user_invitations', 'cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')->nullable();
            }
            
            if (!Schema::hasColumn('user_invitations', 'cancellation_reason')) {
                $table->string('cancellation_reason')->nullable();
            }
            
            // Add foreign key if cancelled_by column was just added or exists
            if (Schema::hasColumn('user_invitations', 'cancelled_by')) {
                // Check if foreign key doesn't already exist
                $foreignKeys = collect(DB::select('SHOW KEYS FROM user_invitations WHERE Key_name = ?', ['user_invitations_cancelled_by_foreign']));
                if ($foreignKeys->isEmpty()) {
                    $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');
                }
            }
        });
    }

    public function down()
    {
        Schema::table('user_invitations', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['cancelled_by']);
            
            // Drop columns
            $table->dropColumn(['cancelled_at', 'cancelled_by', 'cancellation_reason']);
        });
    }
};