<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // In the migration file:
public function up()
{
    Schema::table('user_invitations', function (Blueprint $table) {
        // Option 1: Change status column length
        $table->string('status', 50)->default('pending')->change();
        
        // Option 2: Add opened_at column
        $table->timestamp('opened_at')->nullable()->after('sent_at');
        
        // Option 3: Add metadata column if not exists
        if (!Schema::hasColumn('user_invitations', 'metadata')) {
            $table->json('metadata')->nullable();
        }
    });
}

public function down()
{
    Schema::table('user_invitations', function (Blueprint $table) {
        $table->string('status', 20)->default('pending')->change();
        $table->dropColumn('opened_at');
    });
}

};
