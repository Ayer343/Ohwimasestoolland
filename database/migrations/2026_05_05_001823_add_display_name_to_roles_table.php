<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('roles', function (Blueprint $table) {
            // Add display_name column if it doesn't exist
            if (!Schema::hasColumn('roles', 'display_name')) {
                $table->string('display_name')->nullable()->after('name');
            }
            
            // Add priority column if it doesn't exist (also mentioned in error)
            if (!Schema::hasColumn('roles', 'priority')) {
                $table->integer('priority')->default(0)->after('display_name');
            }
        });
        
        // Update existing roles with display names
        \DB::table('roles')->update([
            'display_name' => \DB::raw('CONCAT(UCASE(LEFT(name, 1)), SUBSTRING(name, 2))')
        ]);
    }

    public function down()
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'priority']);
        });
    }
};