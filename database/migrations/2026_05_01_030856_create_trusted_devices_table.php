<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- trusted_devices ----
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

                $table->index(['user_id', 'device_fingerprint']);
                $table->index('expires_at');
                $table->index('user_id');
                $table->index('device_type');
            });
        }

        // ---- device_tokens ----
        if (!Schema::hasTable('device_tokens')) {
            Schema::create('device_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('device_name', 255);
                $table->string('token', 191);
                $table->string('platform', 50)->nullable();
                $table->string('device_model', 100)->nullable();
                $table->string('os_version', 50)->nullable();
                $table->string('app_version', 50)->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['user_id', 'device_name']);
                $table->index('token');
                $table->index('is_active');
            });
        }

        // ---- login_activities ----
        if (!Schema::hasTable('login_activities')) {
            Schema::create('login_activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
                $table->string('action', 50);
                $table->string('type', 20)->default('web');
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('device', 50)->nullable();
                $table->string('platform', 100)->nullable();
                $table->string('browser', 100)->nullable();
                $table->json('location')->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('success')->default(true);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->index(['user_id', 'created_at']);
                $table->index(['action', 'created_at']);
                $table->index('ip_address');
                $table->index('created_at');
            });
        }

        // ---- Users table: add columns one at a time, fresh check each time ----
        $userColumns = [
            ['devices',                    fn(Blueprint $t) => $t->json('devices')->nullable()],
            ['device_trusts',              fn(Blueprint $t) => $t->json('device_trusts')->nullable()],
            ['last_activity_at',           fn(Blueprint $t) => $t->timestamp('last_activity_at')->nullable()],
            ['login_count',                fn(Blueprint $t) => $t->integer('login_count')->default(0)],
            ['last_login_ip',              fn(Blueprint $t) => $t->string('last_login_ip', 45)->nullable()],
            ['last_login_at',              fn(Blueprint $t) => $t->timestamp('last_login_at')->nullable()],
            ['two_factor_backup_codes',    fn(Blueprint $t) => $t->json('two_factor_backup_codes')->nullable()],
            ['two_factor_method',          fn(Blueprint $t) => $t->string('two_factor_method')->nullable()],
            ['two_factor_enabled_at',      fn(Blueprint $t) => $t->timestamp('two_factor_enabled_at')->nullable()],
            ['two_factor_secret',          fn(Blueprint $t) => $t->string('two_factor_secret')->nullable()],
            ['two_factor_enabled',         fn(Blueprint $t) => $t->boolean('two_factor_enabled')->default(false)],
            ['password_changed_at',        fn(Blueprint $t) => $t->timestamp('password_changed_at')->nullable()],
            ['password_history',           fn(Blueprint $t) => $t->json('password_history')->nullable()],
            ['temp_password',              fn(Blueprint $t) => $t->boolean('temp_password')->default(false)],
            ['notification_settings',      fn(Blueprint $t) => $t->json('notification_settings')->nullable()],
            ['preferred_language',         fn(Blueprint $t) => $t->string('preferred_language', 10)->default('en')],
            ['timezone',                   fn(Blueprint $t) => $t->string('timezone', 50)->nullable()],
            ['metadata',                   fn(Blueprint $t) => $t->json('metadata')->nullable()],
            ['account_locked_until',       fn(Blueprint $t) => $t->timestamp('account_locked_until')->nullable()],
            ['failed_login_attempts',      fn(Blueprint $t) => $t->integer('failed_login_attempts')->default(0)],
            ['last_failed_login_at',       fn(Blueprint $t) => $t->timestamp('last_failed_login_at')->nullable()],
            ['email_verified_at',          fn(Blueprint $t) => $t->timestamp('email_verified_at')->nullable()],
            ['email_verification_token',   fn(Blueprint $t) => $t->string('email_verification_token')->nullable()],
            ['email_verification_sent_at', fn(Blueprint $t) => $t->timestamp('email_verification_sent_at')->nullable()],
        ];

        foreach ($userColumns as [$name, $adder]) {
            if (!Schema::hasColumn('users', $name)) {
                Schema::table('users', function (Blueprint $table) use ($adder) {
                    $adder($table);
                });
            }
        }

        // ---- personal_access_tokens columns ----
        if (Schema::hasTable('personal_access_tokens')) {
            $patColumns = [
                ['device_info', fn(Blueprint $t) => $t->json('device_info')->nullable()],
                ['ip_address',  fn(Blueprint $t) => $t->string('ip_address', 45)->nullable()],
                ['user_agent',  fn(Blueprint $t) => $t->text('user_agent')->nullable()],
                ['expires_at',  fn(Blueprint $t) => $t->timestamp('expires_at')->nullable()],
            ];

            foreach ($patColumns as [$name, $adder]) {
                if (!Schema::hasColumn('personal_access_tokens', $name)) {
                    Schema::table('personal_access_tokens', function (Blueprint $table) use ($adder) {
                        $adder($table);
                    });
                }
            }
        }

        // ---- password reset tables ----
        if (!Schema::hasTable('password_reset_tokens') && !Schema::hasTable('password_resets')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token', 191);
                $table->timestamp('created_at')->nullable();
                $table->index('token');
            });
        }

        // ---- sessions ----
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

        // ---- failed_jobs ----
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

        // ---- jobs ----
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

        // ---- cache / cache_locks ----
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

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('login_activities');

        $userColumns = [
            'devices', 'device_trusts', 'last_activity_at', 'login_count', 'last_login_ip',
            'last_login_at', 'two_factor_backup_codes', 'two_factor_method',
            'two_factor_enabled_at', 'two_factor_secret', 'two_factor_enabled',
            'password_changed_at', 'password_history', 'temp_password',
            'notification_settings', 'preferred_language', 'timezone', 'metadata',
            'account_locked_until', 'failed_login_attempts', 'last_failed_login_at',
            'email_verification_token', 'email_verification_sent_at',
        ];

        foreach ($userColumns as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};