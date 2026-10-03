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
            // Add tenant_id if it doesn't exist
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
            
            // Add status field if it doesn't exist
            if (!Schema::hasColumn('tenant_invitations', 'status')) {
                $table->string('status')->default('pending')->after('token');
            }
            
            // Add sent_at field if it doesn't exist
            if (!Schema::hasColumn('tenant_invitations', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('status');
            }
            
            // Add completed_at field if it doesn't exist
            if (!Schema::hasColumn('tenant_invitations', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('sent_at');
            }
            
            // Add last_error field if it doesn't exist
            if (!Schema::hasColumn('tenant_invitations', 'last_error')) {
                $table->text('last_error')->nullable()->after('completed_at');
            }
            
            // Add sent_channels field if it doesn't exist
            if (!Schema::hasColumn('tenant_invitations', 'sent_channels')) {
                $table->json('sent_channels')->nullable()->after('channels');
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
                'status',
                'sent_at',
                'completed_at',
                'last_error',
                'sent_channels'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('tenant_invitations', $column)) {
                    if ($column === 'tenant_id' || $column === 'user_id') {
                        $table->dropForeign([$column]);
                    }
                    $table->dropColumn($column);
                }
            }
        });
    }
};