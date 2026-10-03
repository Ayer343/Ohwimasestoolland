<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArchiveCleanupLogsTable extends Migration
{
    public function up()
    {
        Schema::create('archive_cleanup_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('performed_by');
            $table->string('performed_by_name');
            $table->string('method', 20)->default('by_age'); // by_age, by_selection
            $table->integer('years_old')->nullable();
            $table->integer('selected_count')->nullable();
            $table->date('cutoff_date')->nullable();
            $table->integer('records_deleted');
            $table->decimal('total_amount', 15, 2);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            
            $table->index('performed_by');
            $table->index('created_at');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('archive_cleanup_logs');
    }
}