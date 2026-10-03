<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, check if we need to modify existing columns or add new ones
        Schema::table('tenant_invitations', function (Blueprint $table) {
            // Make user_id nullable if it exists and doesn't allow nulls
            if (Schema::hasColumn('tenant_invitations', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            } else {
                // Add user_id if it doesn't exist
                $table->foreignId('user_id')
                      ->nullable()
                      ->constrained('users')
                      ->onDelete('set null')
                      ->after('id');
            }
            
            // Add tenant_id if it doesn't exist
            if (!Schema::hasColumn('tenant_invitations', 'tenant_id')) {
                $table->foreignId('tenant_id')
                      ->nullable()
                      ->constrained('registration_tenants')
                      ->onDelete('cascade')
                      ->after('id');
            }
            
            // Add sent_at if it doesn't exist
            if (!Schema::hasColumn('tenant_invitations', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('status');
            }
            
            // Add sent_channels if it doesn't exist
            if (!Schema::hasColumn('tenant_invitations', 'sent_channels')) {
                $table->json('sent_channels')->nullable()->after('channels');
            }
            
            // Add custom_message if it doesn't exist
            if (!Schema::hasColumn('tenant_invitations', 'custom_message')) {
                $table->text('custom_message')->nullable()->after('metadata');
            }
            
            // Add cancelled_at and cancelled_by if they don't exist
            if (!Schema::hasColumn('tenant_invitations', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('tenant_invitations', 'cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
                $table->foreign('cancelled_by')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');
            }
        });

        // For MySQL, we need to handle the default value issue differently
        if (DB::connection()->getDriverName() === 'mysql') {
            // Check if we need to modify the user_id column to have a default value
            $columns = DB::select('SHOW COLUMNS FROM tenant_invitations WHERE Field = "user_id"');
            if (!empty($columns)) {
                $column = $columns[0];
                // If column is NOT NULL and has no default, alter it to be nullable
                if ($column->Null === 'NO' && $column->Default === null) {
                    DB::statement('ALTER TABLE tenant_invitations MODIFY user_id BIGINT UNSIGNED NULL');
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_invitations', function (Blueprint $table) {
            $columns = [
                'tenant_id',
                'sent_at',
                'sent_channels',
                'custom_message',
                'cancelled_at',
                'cancelled_by'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('tenant_invitations', $column)) {
                    if (in_array($column, ['tenant_id', 'cancelled_by'])) {
                        $table->dropForeign([$column]);
                    }
                    $table->dropColumn($column);
                }
            }
            
            // Don't drop user_id as it might be the primary key, just make it required again
            if (Schema::hasColumn('tenant_invitations', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable(false)->change();
            }
        });
    }
};