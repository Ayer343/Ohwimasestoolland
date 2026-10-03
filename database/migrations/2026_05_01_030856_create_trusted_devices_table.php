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
        // Create trusted_devices table
        if (!Schema::hasTable('trusted_devices')) {
            Schema::create('trusted_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('device_fingerprint', 255);
                $table->text('user_agent')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('device_type', 50)->nullable();
                $table->string('platform', 100)->nullable();
                $table->string('browser', 100)->nullable();
                $table->timestamp('trusted_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
                
                // Indexes for performance
                $table->index(['user_id', 'device_fingerprint']);
                $table->index('expires_at');
                $table->index('user_id');
                $table->index('device_type');
            });
        }

        // Create device_tokens table for push notifications - FIXED
        if (!Schema::hasTable('device_tokens')) {
            Schema::create('device_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('device_name', 255);
                $table->string('token', 191); // Changed from text to string with length
                $table->string('platform', 50)->nullable(); // ios, android, web
                $table->string('device_model', 100)->nullable();
                $table->string('os_version', 50)->nullable();
                $table->string('app_version', 50)->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                
                $table->index(['user_id', 'device_name']);
                $table->index('token'); // Now this works because token is string(191)
                $table->index('is_active');
            });
        }

        // Create login_activities table for tracking
        if (!Schema::hasTable('login_activities')) {
            Schema::create('login_activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
                $table->string('action', 50); // login, logout, failed, 2fa_verify, password_change
                $table->string('type', 20)->default('web'); // web, mobile, api, social
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('device', 50)->nullable();
                $table->string('platform', 100)->nullable();
                $table->string('browser', 100)->nullable();
                $table->json('location')->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('success')->default(true);
                $table->timestamp('created_at')->nullable();
                
                $table->index(['user_id', 'created_at']);
                $table->index(['action', 'created_at']);
                $table->index('ip_address');
                $table->index('created_at');
            });
        }

        // Add columns to users table - Check if each column exists first
        Schema::table('users', function (Blueprint $table) {
            // Device and session management
            if (!Schema::hasColumn('users', 'devices')) {
                $table->json('devices')->nullable();
            }
            if (!Schema::hasColumn('users', 'device_trusts')) {
                $table->json('device_trusts')->nullable();
            }
            
            // Activity tracking
            if (!Schema::hasColumn('users', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'login_count')) {
                $table->integer('login_count')->default(0);
            }
            if (!Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip', 45)->nullable();
            }
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
            
            // Two-factor authentication
            if (!Schema::hasColumn('users', 'two_factor_backup_codes')) {
                $table->json('two_factor_backup_codes')->nullable();
            }
            if (!Schema::hasColumn('users', 'two_factor_method')) {
                $table->string('two_factor_method')->nullable(); // email, sms, authenticator
            }
            if (!Schema::hasColumn('users', 'two_factor_enabled_at')) {
                $table->timestamp('two_factor_enabled_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'two_factor_secret')) {
                $table->string('two_factor_secret')->nullable();
            }
            if (!Schema::hasColumn('users', 'two_factor_enabled')) {
                $table->boolean('two_factor_enabled')->default(false);
            }
            
            // Password management
            if (!Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'password_history')) {
                $table->json('password_history')->nullable(); // Store last 5 password hashes
            }
            if (!Schema::hasColumn('users', 'temp_password')) {
                $table->boolean('temp_password')->default(false);
            }
            
            // User preferences
            if (!Schema::hasColumn('users', 'notification_settings')) {
                $table->json('notification_settings')->nullable();
            }
            if (!Schema::hasColumn('users', 'preferred_language')) {
                $table->string('preferred_language', 10)->default('en');
            }
            if (!Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone', 50)->nullable();
            }
            if (!Schema::hasColumn('users', 'metadata')) {
                $table->json('metadata')->nullable();
            }
            
            // Account status
            if (!Schema::hasColumn('users', 'account_locked_until')) {
                $table->timestamp('account_locked_until')->nullable();
            }
            if (!Schema::hasColumn('users', 'failed_login_attempts')) {
                $table->integer('failed_login_attempts')->default(0);
            }
            if (!Schema::hasColumn('users', 'last_failed_login_at')) {
                $table->timestamp('last_failed_login_at')->nullable();
            }
            
            // Email verification
            if (!Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'email_verification_token')) {
                $table->string('email_verification_token')->nullable();
            }
            if (!Schema::hasColumn('users', 'email_verification_sent_at')) {
                $table->timestamp('email_verification_sent_at')->nullable();
            }
        });

        // Add device_info to personal_access_tokens table
        if (Schema::hasTable('personal_access_tokens')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                if (!Schema::hasColumn('personal_access_tokens', 'device_info')) {
                    $table->json('device_info')->nullable();
                }
                if (!Schema::hasColumn('personal_access_tokens', 'ip_address')) {
                    $table->string('ip_address', 45)->nullable();
                }
                if (!Schema::hasColumn('personal_access_tokens', 'user_agent')) {
                    $table->text('user_agent')->nullable();
                }
                if (!Schema::hasColumn('personal_access_tokens', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable();
                }
            });
        }

        // Create password_reset_tokens table (Laravel 11+)
        if (!Schema::hasTable('password_reset_tokens') && !Schema::hasTable('password_resets')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token', 191); // Fixed: added length
                $table->timestamp('created_at')->nullable();
                $table->index('token');
            });
        } elseif (!Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function (Blueprint $table) {
                $table->string('email')->index();
                $table->string('token', 191); // Fixed: added length
                $table->timestamp('created_at')->nullable();
            });
        }

        // Create sessions table for better session management
        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        // Create failed_jobs table for queue handling
        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        // Create jobs table for background processing
        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
                $table->index(['queue', 'reserved_at']);
            });
        }

        // Create cache table for rate limiting
        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        if (!Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order to avoid foreign key constraints
        Schema::dropIfExists('trusted_devices');
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('login_activities');
        
        // Drop columns from users table - Only drop if they exist
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'devices',
                'device_trusts',
                'last_activity_at',
                'login_count',
                'last_login_ip',
                'last_login_at',
                'two_factor_backup_codes',
                'two_factor_method',
                'two_factor_enabled_at',
                'two_factor_secret',
                'two_factor_enabled',
                'password_changed_at',
                'password_history',
                'temp_password',
                'notification_settings',
                'preferred_language',
                'timezone',
                'metadata',
                'account_locked_until',
                'failed_login_attempts',
                'last_failed_login_at',
                'email_verified_at',
                'email_verification_token',
                'email_verification_sent_at'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        
        // Drop columns from personal_access_tokens table
        if (Schema::hasTable('personal_access_tokens')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $columns = ['device_info', 'ip_address', 'user_agent', 'expires_at'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('personal_access_tokens', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
        
        // Drop optional tables if they exist
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('password_resets');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};