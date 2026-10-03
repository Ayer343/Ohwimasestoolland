<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsToSecuritySchedulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('security_schedules', function (Blueprint $table) {
            // Add missing columns that the model is trying to insert
            if (!Schema::hasColumn('security_schedules', 'handover_completed')) {
                $table->boolean('handover_completed')->default(false)->after('handover_info');
            }
            
            if (!Schema::hasColumn('security_schedules', 'is_approved')) {
                $table->boolean('is_approved')->default(false)->after('status');
            }
            
            if (!Schema::hasColumn('security_schedules', 'include_breaks')) {
                $table->boolean('include_breaks')->default(false)->after('special_instructions');
            }
            
            if (!Schema::hasColumn('security_schedules', 'break_duration')) {
                $table->integer('break_duration')->default(0)->after('include_breaks');
            }
            
            if (!Schema::hasColumn('security_schedules', 'late_minutes')) {
                $table->integer('late_minutes')->default(0)->after('checkout_time');
            }
            
            if (!Schema::hasColumn('security_schedules', 'overtime_minutes')) {
                $table->integer('overtime_minutes')->default(0)->after('late_minutes');
            }
            
            if (!Schema::hasColumn('security_schedules', 'checkin_notes')) {
                $table->text('checkin_notes')->nullable()->after('checkin_time');
            }
            
            if (!Schema::hasColumn('security_schedules', 'current_break_id')) {
                $table->integer('current_break_id')->nullable()->after('break_duration');
            }
            
            if (!Schema::hasColumn('security_schedules', 'break_start_time')) {
                $table->datetime('break_start_time')->nullable()->after('current_break_id');
            }
            
            if (!Schema::hasColumn('security_schedules', 'break_end_time')) {
                $table->datetime('break_end_time')->nullable()->after('break_start_time');
            }
            
            if (!Schema::hasColumn('security_schedules', 'break_status')) {
                $table->string('break_status')->nullable()->after('break_end_time');
            }
            
            if (!Schema::hasColumn('security_schedules', 'break_history')) {
                $table->json('break_history')->nullable()->after('break_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('security_schedules', function (Blueprint $table) {
            $table->dropColumn([
                'handover_completed',
                'is_approved',
                'include_breaks',
                'break_duration',
                'late_minutes',
                'overtime_minutes',
                'checkin_notes',
                'current_break_id',
                'break_start_time',
                'break_end_time',
                'break_status',
                'break_history'
            ]);
        });
    }
}