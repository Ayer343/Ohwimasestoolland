<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusToEmailsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            // Add status column with default value
            $table->enum('status', ['draft', 'sent', 'failed', 'queued'])->default('draft')->after('folder');
            
            // Add indexes for better performance
            $table->index('status');
            $table->index(['folder', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->dropIndex(['status']);
            $table->dropIndex(['folder', 'status']);
        });
    }
}