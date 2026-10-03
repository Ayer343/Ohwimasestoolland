<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('system_metrics', function (Blueprint $table) {
            $table->id();
            $table->decimal('cpu_usage', 5, 2)->default(0);
            $table->decimal('memory_usage', 5, 2)->default(0);
            $table->decimal('disk_usage', 5, 2)->default(0);
            $table->decimal('load_average_1min', 5, 2)->default(0);
            $table->decimal('load_average_5min', 5, 2)->default(0);
            $table->decimal('load_average_15min', 5, 2)->default(0);
            $table->integer('database_connections')->default(0);
            $table->integer('queue_size')->default(0);
            $table->integer('alerts_count')->default(0);
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();
            
            $table->index('recorded_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('system_metrics');
    }
};