<?php

// database/migrations/YYYY_MM_DD_HHMMSS_add_landlord_confirmation_to_property_family_links.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_family_links', function (Blueprint $table) {
            $table->timestamp('landlord_confirmed_at')->nullable()->after('reviewed_at');
            $table->foreignId('landlord_confirmed_by')
                  ->nullable()
                  ->after('landlord_confirmed_at')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('property_family_links', function (Blueprint $table) {
            $table->dropConstrainedForeignId('landlord_confirmed_by');
            $table->dropColumn('landlord_confirmed_at');
        });
    }
};