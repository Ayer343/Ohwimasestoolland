<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // database/migrations/xxxx_xx_xx_xxxxxx_make_invitation_id_nullable_in_agent_invitation_logs.php
public function up()
{
    Schema::table('agent_invitation_logs', function (Blueprint $table) {
        $table->unsignedBigInteger('invitation_id')->nullable()->change();
    });
}

public function down()
{
    Schema::table('agent_invitation_logs', function (Blueprint $table) {
        $table->unsignedBigInteger('invitation_id')->nullable(false)->change();
    });
}
};
