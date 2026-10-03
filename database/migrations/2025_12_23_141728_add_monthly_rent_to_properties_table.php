<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('monthly_rent', 10, 2)->nullable()->default(null)->after('description');
        });
        
        // Add column comments
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE properties MODIFY monthly_rent DECIMAL(10,2) NULL COMMENT 'Monthly rental amount for the property'");
        } elseif (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON COLUMN properties.monthly_rent IS 'Monthly rental amount for the property'");
        }
    }

    public function down()
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('monthly_rent');
        });
    }
};