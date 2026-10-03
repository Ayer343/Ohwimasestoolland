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
        // ========================================== //
        // 1️⃣ MODIFY EXISTING COLUMNS                 //
        // ========================================== //
        
        // Make provider nullable
        DB::statement('ALTER TABLE sms_logs MODIFY provider VARCHAR(191) NULL');
        
        // Make phone_number nullable
        DB::statement('ALTER TABLE sms_logs MODIFY phone_number VARCHAR(191) NULL');
        
        // Modify status ENUM to include 'pending'
        DB::statement("ALTER TABLE sms_logs MODIFY status ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending'");
        
        // ========================================== //
        // 2️⃣ ADD NEW COLUMNS ONE BY ONE             //
        // ========================================== //
        
        // Provider Information
        if (!Schema::hasColumn('sms_logs', 'provider_name')) {
            DB::statement('ALTER TABLE sms_logs ADD provider_name VARCHAR(191) NULL AFTER provider');
        }
        
        if (!Schema::hasColumn('sms_logs', 'country_code')) {
            DB::statement('ALTER TABLE sms_logs ADD country_code VARCHAR(5) NULL AFTER phone_number');
        }
        
        // Message Details
        if (!Schema::hasColumn('sms_logs', 'message_length')) {
            DB::statement('ALTER TABLE sms_logs ADD message_length INT NULL AFTER message');
        }
        
        if (!Schema::hasColumn('sms_logs', 'message_parts')) {
            DB::statement('ALTER TABLE sms_logs ADD message_parts INT NOT NULL DEFAULT 1 AFTER message_length');
        }
        
        // Status Tracking
        if (!Schema::hasColumn('sms_logs', 'status_updated_at')) {
            DB::statement('ALTER TABLE sms_logs ADD status_updated_at TIMESTAMP NULL AFTER status');
        }
        
        // Provider Response
        if (!Schema::hasColumn('sms_logs', 'external_id')) {
            DB::statement('ALTER TABLE sms_logs ADD external_id VARCHAR(191) NULL AFTER message_id');
        }
        
        if (!Schema::hasColumn('sms_logs', 'error_code')) {
            DB::statement('ALTER TABLE sms_logs ADD error_code VARCHAR(191) NULL AFTER error_message');
        }
        
        if (!Schema::hasColumn('sms_logs', 'error_type')) {
            DB::statement('ALTER TABLE sms_logs ADD error_type VARCHAR(191) NULL AFTER error_code');
        }
        
        // Performance Metrics
        if (!Schema::hasColumn('sms_logs', 'is_test')) {
            DB::statement('ALTER TABLE sms_logs ADD is_test TINYINT(1) NOT NULL DEFAULT 0 AFTER error_type');
        }
        
        if (!Schema::hasColumn('sms_logs', 'execution_time')) {
            DB::statement('ALTER TABLE sms_logs ADD execution_time DOUBLE NULL AFTER is_test');
        }
        
        if (!Schema::hasColumn('sms_logs', 'status_code')) {
            DB::statement('ALTER TABLE sms_logs ADD status_code INT NULL AFTER execution_time');
        }
        
        if (!Schema::hasColumn('sms_logs', 'api_latency')) {
            DB::statement('ALTER TABLE sms_logs ADD api_latency DOUBLE NULL AFTER status_code');
        }
        
        // Additional Data
        if (!Schema::hasColumn('sms_logs', 'details')) {
            DB::statement('ALTER TABLE sms_logs ADD details JSON NULL AFTER api_latency');
        }
        
        if (!Schema::hasColumn('sms_logs', 'metadata')) {
            DB::statement('ALTER TABLE sms_logs ADD metadata JSON NULL AFTER details');
        }
        
        if (!Schema::hasColumn('sms_logs', 'tags')) {
            DB::statement('ALTER TABLE sms_logs ADD tags JSON NULL AFTER metadata');
        }
        
        // User Association
        if (!Schema::hasColumn('sms_logs', 'user_id')) {
            DB::statement('ALTER TABLE sms_logs ADD user_id BIGINT UNSIGNED NULL AFTER tags');
        }
        
        if (!Schema::hasColumn('sms_logs', 'user_type')) {
            DB::statement('ALTER TABLE sms_logs ADD user_type VARCHAR(191) NULL AFTER user_id');
        }
        
        // Timestamps
        if (!Schema::hasColumn('sms_logs', 'sent_at')) {
            DB::statement('ALTER TABLE sms_logs ADD sent_at TIMESTAMP NULL AFTER user_type');
        }
        
        if (!Schema::hasColumn('sms_logs', 'delivered_at')) {
            DB::statement('ALTER TABLE sms_logs ADD delivered_at TIMESTAMP NULL AFTER sent_at');
        }
        
        if (!Schema::hasColumn('sms_logs', 'failed_at')) {
            DB::statement('ALTER TABLE sms_logs ADD failed_at TIMESTAMP NULL AFTER delivered_at');
        }
        
        if (!Schema::hasColumn('sms_logs', 'retry_at')) {
            DB::statement('ALTER TABLE sms_logs ADD retry_at TIMESTAMP NULL AFTER failed_at');
        }
        
        if (!Schema::hasColumn('sms_logs', 'retry_count')) {
            DB::statement('ALTER TABLE sms_logs ADD retry_count INT NOT NULL DEFAULT 0 AFTER retry_at');
        }
        
        // Cost Tracking
        if (!Schema::hasColumn('sms_logs', 'cost')) {
            DB::statement('ALTER TABLE sms_logs ADD cost DECIMAL(10,4) NULL AFTER retry_count');
        }
        
        if (!Schema::hasColumn('sms_logs', 'currency')) {
            DB::statement("ALTER TABLE sms_logs ADD currency VARCHAR(3) NOT NULL DEFAULT 'GHS' AFTER cost");
        }
        
        // Scheduling
        if (!Schema::hasColumn('sms_logs', 'scheduled_at')) {
            DB::statement('ALTER TABLE sms_logs ADD scheduled_at TIMESTAMP NULL AFTER currency');
        }
        
        if (!Schema::hasColumn('sms_logs', 'is_scheduled')) {
            DB::statement('ALTER TABLE sms_logs ADD is_scheduled TINYINT(1) NOT NULL DEFAULT 0 AFTER scheduled_at');
        }
        
        // Campaign Tracking
        if (!Schema::hasColumn('sms_logs', 'campaign_id')) {
            DB::statement('ALTER TABLE sms_logs ADD campaign_id VARCHAR(191) NULL AFTER is_scheduled');
        }
        
        if (!Schema::hasColumn('sms_logs', 'template_id')) {
            DB::statement('ALTER TABLE sms_logs ADD template_id VARCHAR(191) NULL AFTER campaign_id');
        }
        
        if (!Schema::hasColumn('sms_logs', 'batch_id')) {
            DB::statement('ALTER TABLE sms_logs ADD batch_id VARCHAR(191) NULL AFTER template_id');
        }
        
        // ========================================== //
        // 3️⃣ ADD FOREIGN KEY CONSTRAINT             //
        // ========================================== //
        
        // Add foreign key for user_id
        try {
            DB::statement('ALTER TABLE sms_logs ADD CONSTRAINT sms_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL');
        } catch (\Exception $e) {
            // Foreign key might already exist
        }
        
        // ========================================== //
        // 4️⃣ ADD INDEXES                            //
        // ========================================== //
        
        try {
            DB::statement('ALTER TABLE sms_logs ADD INDEX status_created_at_index (status, created_at)');
        } catch (\Exception $e) {}
        
        try {
            DB::statement('ALTER TABLE sms_logs ADD INDEX provider_status_index (provider, status)');
        } catch (\Exception $e) {}
        
        try {
            DB::statement('ALTER TABLE sms_logs ADD INDEX phone_number_created_at_index (phone_number, created_at)');
        } catch (\Exception $e) {}
        
        try {
            DB::statement('ALTER TABLE sms_logs ADD INDEX user_id_created_at_index (user_id, created_at)');
        } catch (\Exception $e) {}
        
        try {
            DB::statement('ALTER TABLE sms_logs ADD INDEX status_sent_at_index (status, sent_at)');
        } catch (\Exception $e) {}
        
        try {
            DB::statement('ALTER TABLE sms_logs ADD INDEX message_id_index (message_id)');
        } catch (\Exception $e) {}
        
        try {
            DB::statement('ALTER TABLE sms_logs ADD INDEX external_id_index (external_id)');
        } catch (\Exception $e) {}
        
        try {
            DB::statement('ALTER TABLE sms_logs ADD INDEX batch_id_index (batch_id)');
        } catch (\Exception $e) {}
        
        try {
            DB::statement('ALTER TABLE sms_logs ADD INDEX campaign_id_index (campaign_id)');
        } catch (\Exception $e) {}
        
        try {
            DB::statement('ALTER TABLE sms_logs ADD INDEX is_test_created_at_index (is_test, created_at)');
        } catch (\Exception $e) {}
        
        try {
            DB::statement('ALTER TABLE sms_logs ADD INDEX is_scheduled_status_index (is_scheduled, status)');
        } catch (\Exception $e) {}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ========================================== //
        // 1️⃣ DROP FOREIGN KEY                       //
        // ========================================== //
        
        try {
            DB::statement('ALTER TABLE sms_logs DROP FOREIGN KEY sms_logs_user_id_foreign');
        } catch (\Exception $e) {}
        
        // ========================================== //
        // 2️⃣ DROP INDEXES                           //
        // ========================================== //
        
        $indexes = [
            'status_created_at_index',
            'provider_status_index',
            'phone_number_created_at_index',
            'user_id_created_at_index',
            'status_sent_at_index',
            'message_id_index',
            'external_id_index',
            'batch_id_index',
            'campaign_id_index',
            'is_test_created_at_index',
            'is_scheduled_status_index'
        ];
        
        foreach ($indexes as $index) {
            try {
                DB::statement("ALTER TABLE sms_logs DROP INDEX {$index}");
            } catch (\Exception $e) {}
        }
        
        // ========================================== //
        // 3️⃣ DROP NEW COLUMNS                       //
        // ========================================== //
        
        $columns = [
            'provider_name',
            'country_code',
            'message_length',
            'message_parts',
            'status_updated_at',
            'external_id',
            'error_code',
            'error_type',
            'is_test',
            'execution_time',
            'status_code',
            'api_latency',
            'details',
            'metadata',
            'tags',
            'user_id',
            'user_type',
            'sent_at',
            'delivered_at',
            'failed_at',
            'retry_at',
            'retry_count',
            'cost',
            'currency',
            'scheduled_at',
            'is_scheduled',
            'campaign_id',
            'template_id',
            'batch_id'
        ];

        foreach ($columns as $column) {
            try {
                if (Schema::hasColumn('sms_logs', $column)) {
                    DB::statement("ALTER TABLE sms_logs DROP COLUMN {$column}");
                }
            } catch (\Exception $e) {}
        }
        
        // ========================================== //
        // 4️⃣ REVERT STATUS ENUM                     //
        // ========================================== //
        
        try {
            DB::statement("ALTER TABLE sms_logs MODIFY status ENUM('success', 'failed') NOT NULL");
        } catch (\Exception $e) {}
        
        // ========================================== //
        // 5️⃣ REVERT NULLABLE CHANGES                //
        // ========================================== //
        
        try {
            DB::statement('ALTER TABLE sms_logs MODIFY provider VARCHAR(191) NOT NULL');
        } catch (\Exception $e) {}
        
        try {
            DB::statement('ALTER TABLE sms_logs MODIFY phone_number VARCHAR(191) NOT NULL');
        } catch (\Exception $e) {}
    }
};