<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeveloperSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('developer_settings', function (Blueprint $table) {
            $table->id();
            
            // Developer Information
            $table->string('developer_name');
            $table->string('developer_email')->unique();
            $table->string('developer_phone')->nullable();
            $table->string('developer_company')->nullable();
            $table->string('developer_website')->nullable();
            $table->text('developer_address')->nullable();
            
            // System Access Configuration
            $table->boolean('developer_access_enabled')->default(true);
            $table->json('allowed_ips')->nullable(); // IP whitelist
            $table->json('allowed_features')->nullable(); // Features developer can access
            $table->string('developer_secret_key')->unique()->nullable(); // API secret
            
            // Billing Configuration
            $table->decimal('monthly_billing_amount', 10, 2)->default(0.00);
            $table->string('billing_currency')->default('GHS');
            $table->string('billing_cycle')->default('monthly'); // monthly, quarterly, yearly
            $table->date('billing_start_date')->nullable();
            $table->date('next_billing_date')->nullable();
            $table->boolean('billing_active')->default(false);
            $table->string('billing_status')->default('pending'); // pending, active, suspended, cancelled
            
            // Payment Information
            $table->string('payment_method')->nullable(); // mtn, bank_transfer, paystack
            $table->string('payment_mobile_number')->nullable();
            $table->string('payment_account_name')->nullable();
            $table->string('payment_account_number')->nullable();
            $table->string('payment_bank_name')->nullable();
            $table->string('payment_bank_branch')->nullable();
            
            // Email Configuration (Developer's own email settings)
            $table->string('developer_smtp_host')->nullable();
            $table->integer('developer_smtp_port')->nullable();
            $table->string('developer_smtp_username')->nullable();
            $table->text('developer_smtp_password')->nullable(); // Encrypted
            $table->string('developer_smtp_encryption')->default('tls');
            $table->string('developer_email_from')->nullable();
            $table->string('developer_email_from_name')->nullable();
            
            // API Configuration
            $table->string('api_base_url')->nullable();
            $table->string('api_version')->default('v1');
            $table->boolean('api_enabled')->default(true);
            $table->json('api_rate_limits')->nullable();
            
            // Monitoring & Logging
            $table->boolean('enable_system_monitoring')->default(true);
            $table->boolean('enable_error_tracking')->default(true);
            $table->boolean('enable_performance_monitoring')->default(true);
            $table->integer('log_retention_days')->default(90);
            
            // Maintenance Mode
            $table->boolean('maintenance_mode')->default(false);
            $table->text('maintenance_message')->nullable();
            $table->json('maintenance_allowed_ips')->nullable();
            $table->timestamp('maintenance_start')->nullable();
            $table->timestamp('maintenance_end')->nullable();
            
            // Security Settings
            $table->boolean('enable_two_factor')->default(false);
            $table->integer('session_timeout')->default(120); // minutes
            $table->integer('max_login_attempts')->default(5);
            $table->integer('password_expiry_days')->default(90);
            
            // System Performance
            $table->integer('cache_duration')->default(3600);
            $table->boolean('enable_query_cache')->default(true);
            $table->integer('max_upload_size')->default(2048); // KB
            $table->integer('max_execution_time')->default(300); // seconds
            
            // Backup Configuration
            $table->boolean('enable_auto_backup')->default(true);
            $table->string('backup_frequency')->default('daily'); // daily, weekly, monthly
            $table->integer('backup_retention_days')->default(30);
            $table->json('backup_storage_locations')->nullable();
            
            // Analytics & Reporting
            $table->boolean('enable_analytics')->default(true);
            $table->string('analytics_provider')->default('internal'); // google, internal
            $table->string('analytics_tracking_id')->nullable();
            $table->boolean('enable_daily_reports')->default(true);
            $table->json('report_recipients')->nullable();
            
            // Custom Configuration
            $table->json('custom_config')->nullable();
            $table->json('feature_flags')->nullable();
            $table->json('environment_variables')->nullable();
            
            // Status & Metadata
            $table->string('status')->default('active'); // active, inactive, suspended
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('developer_email');
            $table->index('billing_status');
            $table->index('status');
            $table->index(['developer_access_enabled', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('developer_settings');
    }
}