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
        Schema::create('property_transfer_tenant_notifications', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('transfer_id')
                  ->constrained('property_ownership_transfers')
                  ->onDelete('cascade');
            
            $table->foreignId('tenant_id')
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('Tenant being notified');
            
            $table->foreignId('property_unit_id')
                  ->nullable()
                  ->constrained('property_units')
                  ->onDelete('cascade')
                  ->comment('Specific unit tenant occupies');
            
            // 🔥 FIXED: Check if leases table exists before adding foreign key
            if (Schema::hasTable('leases')) {
                $table->foreignId('lease_id')
                      ->nullable()
                      ->constrained('leases')
                      ->onDelete('set null')
                      ->comment('Current lease agreement');
            } else {
                $table->foreignId('lease_id')
                      ->nullable()
                      ->comment('Current lease agreement (table not available)');
            }
            
            // Notification status
            $table->enum('notification_status', [
                'pending',      // Notification not sent yet
                'sent',         // Notification sent to tenant
                'acknowledged', // Tenant acknowledged notification
                'objected',     // Tenant objected to transfer
                'consented',    // Tenant consented to transfer
            ])->default('pending');
            
            // Notification details
            $table->string('notification_method')
                  ->nullable()
                  ->comment('How notification was sent (email, sms, in_app)');
            
            $table->timestamp('notification_sent_at')
                  ->nullable()
                  ->comment('When notification was sent');
            
            $table->timestamp('tenant_responded_at')
                  ->nullable()
                  ->comment('When tenant responded');
            
            $table->text('tenant_response_notes')
                  ->nullable()
                  ->comment('Notes from tenant response');
            
            $table->text('admin_notes')
                  ->nullable()
                  ->comment('Admin notes about tenant notification');
            
            // Lease/rental agreement details
            $table->date('lease_end_date')
                  ->nullable()
                  ->comment('When current lease ends');
            
            $table->decimal('current_rent_amount', 10, 2)
                  ->nullable()
                  ->comment('Current rent amount');
            
            // Metadata
            $table->json('metadata')
                  ->nullable()
                  ->comment('Additional metadata');
            
            $table->timestamps();
            $table->softDeletes();
            
            // 🔥 FIXED: Custom, shorter index names
            $table->index(['transfer_id', 'tenant_id'], 'pttn_transfer_tenant_idx');
            $table->index('notification_status', 'pttn_status_idx');
            $table->unique(['transfer_id', 'tenant_id'], 'pttn_unique_transfer_tenant');
            
            // Additional useful indexes
            $table->index('tenant_id', 'pttn_tenant_idx');
            $table->index('property_unit_id', 'pttn_unit_idx');
            $table->index('notification_sent_at', 'pttn_sent_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_transfer_tenant_notifications');
    }
};