<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // In the migration file
public function up()
{
    Schema::table('user_invitations', function (Blueprint $table) {
        $table->string('invitation_type')->nullable()->after('user_id');
        // Or if you want to rename 'purpose' to 'invitation_type':
        // $table->renameColumn('purpose', 'invitation_type');
    });
}

public function down()
{
    Schema::table('user_invitations', function (Blueprint $table) {
        $table->dropColumn('invitation_type');
        // Or if renamed:
        // $table->renameColumn('invitation_type', 'purpose');
    });
}

};
