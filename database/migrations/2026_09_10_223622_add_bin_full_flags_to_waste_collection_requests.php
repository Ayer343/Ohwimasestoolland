<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_bin_full_flags_to_waste_collection_requests.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waste_collection_requests', function (Blueprint $table) {
            // If not already present
            if (!Schema::hasColumn('waste_collection_requests', 'source')) {
                $table->string('source')->default('scheduled')->after('waste_type');
                // values: 'scheduled', 'bin_full', 'manual'
            }
            if (!Schema::hasColumn('waste_collection_requests', 'bin_full_reported_at')) {
                $table->timestamp('bin_full_reported_at')->nullable()->after('source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('waste_collection_requests', function (Blueprint $table) {
            $table->dropColumn(['source', 'bin_full_reported_at']);
        });
    }
};