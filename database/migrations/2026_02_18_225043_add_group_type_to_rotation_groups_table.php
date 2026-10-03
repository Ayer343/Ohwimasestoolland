<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rotation_groups', function (Blueprint $table) {
            $table->string('group_type')->default('day')->after('name');
            // or if it should be nullable:
            // $table->string('group_type')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rotation_groups', function (Blueprint $table) {
            $table->dropColumn('group_type');
        });
    }
};