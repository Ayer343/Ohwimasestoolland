<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class IncreaseActionColumnSizeInAgentInvitationLogs extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agent_invitation_logs', function (Blueprint $table) {
            // Increase action column size to prevent truncation
            $table->string('action', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_invitation_logs', function (Blueprint $table) {
            // Revert back to original size if needed
            $table->string('action', 20)->change();
        });
    }
}