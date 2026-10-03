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
        Schema::table('tenant_invitations', function (Blueprint $table) {
            // Add tenant_id if it doesn't exist (for direct tenant relationship)
            if (!Schema::hasColumn('tenant_invitations', 'tenant_id')) {
                $table->foreignId('tenant_id')
                      ->nullable()
                      ->constrained('registration_tenants')
                      ->onDelete('cascade')
                      ->after('id');
            }
            
            // Add user_id if it doesn't exist (for the tenant's user account)
            if (!Schema::hasColumn('tenant_invitations', 'user_id')) {
                $table->foreignId('user_id')
                      ->nullable()
                      ->constrained('users')
                      ->onDelete('set null')
                      ->after('tenant_id');
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_invitations', function (Blueprint $table) {
            $columns = [
                'tenant_id',
                'user_id',
                'sent_at',
                'sent_channels',
                'custom_message',
                'cancelled_at',
                'cancelled_by'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('tenant_invitations', $column)) {
                    if (in_array($column, ['tenant_id', 'user_id', 'cancelled_by'])) {
                        $table->dropForeign([$column]);
                    }
                    $table->dropColumn($column);
                }
            }
        });
    }
};