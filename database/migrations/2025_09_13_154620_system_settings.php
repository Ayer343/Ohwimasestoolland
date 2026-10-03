<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Drop the table if it exists to start fresh
        Schema::dropIfExists('system_settings');
        
        // Create the table with all columns
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            
            // System Identification
            $table->string('system_name', 191);
            $table->string('system_short_name', 191)->unique();
            $table->string('system_email', 191);
            $table->string('system_phone', 191);
            $table->text('system_address')->nullable();
            $table->string('system_logo', 191)->nullable();
            
            // Currency Configuration
            $table->string('currency_code', 3)->default('GHS');
            $table->string('currency_symbol', 5)->default('GH₵');
            $table->enum('currency_position', ['left', 'right', 'left_with_space', 'right_with_space'])->default('left');
            $table->integer('decimal_places')->default(2);
            
            // Dues Configuration
            $table->decimal('monthly_dues_amount', 10, 2)->default(0);
            $table->enum('calculation_method', ['fixed', 'per_property'])->default('fixed');
            $table->decimal('per_property_amount', 10, 2)->nullable();
            
            // Tenant Dues Configuration
            $table->boolean('enable_tenant_invoicing')->default(false);
            $table->decimal('tenant_monthly_dues_amount', 10, 2)->nullable();
            $table->enum('tenant_calculation_method', ['fixed', 'per_property_unit'])->nullable();
            $table->boolean('auto_generate_tenant_invoices')->default(true);
            $table->boolean('send_tenant_payment_reminders')->default(true);
            $table->integer('tenant_grace_period_days')->nullable();
            $table->decimal('tenant_late_payment_percentage', 5, 2)->nullable();
            $table->decimal('tenant_fixed_penalty_amount', 10, 2)->nullable();
            
            // Payment Terms
            $table->integer('grace_period_days')->default(7);
            $table->decimal('late_payment_percentage', 5, 2)->default(5.00);
            $table->decimal('fixed_penalty_amount', 10, 2)->nullable();
            
            // System Operations
            $table->boolean('auto_generate_invoices')->default(true);
            $table->boolean('send_payment_reminders')->default(true);
            $table->integer('reminder_days_before')->default(3);
            
            // Payment Gateways
            $table->boolean('enable_expresspay')->default(false);
            $table->boolean('enable_hubtel')->default(false);
            $table->boolean('enable_paystack')->default(false);
            $table->boolean('enable_flutterwave')->default(false);

            // Bulk Payment Settings
            $table->boolean('enable_bulk_payments')->default(true);
            $table->integer('max_bulk_months')->default(6);
            $table->decimal('bulk_payment_discount', 5, 2)->nullable()->default(0);

            // WhatsApp Configuration
            $table->enum('whatsapp_provider', ['twilio', 'vonage', 'custom', 'none'])->default('none');
            $table->text('twilio_sid')->nullable();
            $table->text('twilio_token')->nullable();
            $table->string('twilio_whatsapp_from', 191)->nullable();
            $table->text('vonage_key')->nullable();
            $table->text('vonage_secret')->nullable();
            $table->string('vonage_whatsapp_from', 191)->nullable();
            $table->text('whatsapp_api_url')->nullable();
            $table->text('whatsapp_api_key')->nullable();

            // Registration Control
            $table->boolean('allow_registration')->default(true);
            $table->string('registration_disabled_message', 191)->nullable();
            
            // ============================================================
            // CRITICAL FIX: Using raw SQL for JSON columns to avoid MySQL strict mode issues
            // ============================================================
            // We'll add JSON columns using raw SQL after the table is created
            // This bypasses Laravel's Schema builder which sometimes adds defaults
            
            // SMS Settings
            $table->boolean('sms_notifications_enabled')->default(false);
            $table->boolean('sms_reminder_enabled')->default(false);
            $table->boolean('sms_payment_confirmation_enabled')->default(false);
            $table->integer('sms_daily_limit_per_user')->default(10);
            $table->integer('sms_hourly_limit_per_user')->default(3);
            
            // WhatsApp Notification Settings
            $table->boolean('enable_whatsapp_notifications')->default(false);
            $table->boolean('whatsapp_reminder_enabled')->default(false);
            $table->boolean('whatsapp_payment_confirmation_enabled')->default(false);
            
            // Additional Notification Settings
            $table->boolean('enable_notification_preferences')->default(true);
            $table->boolean('force_email_fallback')->default(true);
            $table->integer('notification_retry_attempts')->default(3);
            $table->integer('notification_retry_delay_minutes')->default(5);
            
            // Message Templates
            $table->text('sms_invoice_generated_template')->nullable();
            $table->text('sms_payment_reminder_template')->nullable();
            $table->text('sms_overdue_template')->nullable();
            $table->text('sms_payment_confirmation_template')->nullable();
            $table->text('whatsapp_invoice_generated_template')->nullable();
            $table->text('whatsapp_payment_reminder_template')->nullable();
            $table->text('whatsapp_overdue_template')->nullable();
            $table->text('whatsapp_payment_confirmation_template')->nullable();
            
            // Bulk Notification Settings
            $table->boolean('enable_bulk_notifications')->default(true);
            $table->integer('bulk_notification_batch_size')->default(50);
            $table->integer('bulk_notification_delay_seconds')->default(2);
            
            // Notification Logging
            $table->boolean('log_all_notifications')->default(true);
            $table->boolean('log_notification_content')->default(false);
            $table->integer('notification_log_retention_days')->default(90);
            
            // Year-End Archive Settings
            $table->boolean('enable_year_end_archive')->default(true);
            $table->integer('year_end_archive_month')->default(1);
            $table->integer('year_end_archive_day')->default(15);
            $table->boolean('archive_paid_invoices_only')->default(true);
            $table->boolean('keep_unpaid_invoices')->default(true);
            $table->integer('paid_invoice_retention_months')->default(3);
            $table->boolean('auto_archive_paid_after_retention')->default(true);
            $table->boolean('notify_landlords_before_archive')->default(true);
            $table->integer('archive_notification_days_landlord')->default(30);
            $table->boolean('send_yearly_archive_report_landlord')->default(true);
            
            // Tenant Year-End Archive Settings
            $table->boolean('enable_year_end_archive_tenant')->default(true);
            $table->integer('year_end_archive_month_tenant')->default(1);
            $table->integer('year_end_archive_day_tenant')->default(16);
            $table->integer('paid_invoice_retention_months_tenant')->default(3);
            $table->boolean('auto_archive_paid_after_retention_tenant')->default(true);
            $table->boolean('notify_tenants_before_archive')->default(true);
            $table->integer('archive_notification_days_tenant')->default(30);
            $table->boolean('send_yearly_archive_report_tenant')->default(true);
            
            // Audit
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            
            $table->timestamps();
            $table->softDeletes();
        });

        // ============================================================
        // ADD JSON COLUMNS USING RAW SQL (BYPASSES LARAVEL'S SCHEMA BUILDER)
        // ============================================================
        DB::statement("ALTER TABLE `system_settings` ADD `invoice_notification_channels` JSON NULL COMMENT 'Channels for invoice generation notifications'");
        DB::statement("ALTER TABLE `system_settings` ADD `payment_reminder_channels` JSON NULL COMMENT 'Channels for payment reminder notifications'");
        DB::statement("ALTER TABLE `system_settings` ADD `overdue_notification_channels` JSON NULL COMMENT 'Channels for overdue notifications'");
        DB::statement("ALTER TABLE `system_settings` ADD `payment_confirmation_channels` JSON NULL COMMENT 'Channels for payment confirmation notifications'");
    }

    public function down()
    {
        Schema::dropIfExists('system_settings');
    }
};