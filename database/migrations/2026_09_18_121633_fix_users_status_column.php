<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Use string, not enum — avoids this class of bug forever.
            $table->string('status', 32)->default('active')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', [
                'pending', 'active', 'suspended',
                'inactive', 'verification_required',
            ])->default('active')->change();
        });
    }
};