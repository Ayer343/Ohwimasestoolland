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
        Schema::table('security_posts', function (Blueprint $table) {
            $table->string('color')->nullable()->after('name')->comment('Color code for calendar display');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security_posts', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};