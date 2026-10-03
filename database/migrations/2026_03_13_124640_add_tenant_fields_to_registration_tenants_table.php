<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_tenants', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('notes');
            $table->date('lease_start_date')->nullable()->after('is_primary');
            $table->date('lease_end_date')->nullable()->after('lease_start_date');
            $table->decimal('monthly_rent', 10, 2)->nullable()->after('lease_end_date');
            $table->json('documents')->nullable()->after('monthly_rent');
            $table->timestamp('verified_at')->nullable()->after('documents');
            $table->foreignId('verified_by')->nullable()->constrained('users')->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('registration_tenants', function (Blueprint $table) {
            $table->dropColumn([
                'is_primary',
                'lease_start_date',
                'lease_end_date',
                'monthly_rent',
                'documents',
                'verified_at',
                'verified_by'
            ]);
        });
    }
};