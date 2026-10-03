<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('reports', function (Blueprint $table) {
        $table->id();
        $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
        $table->foreignId('assignment_id')->nullable()->constrained('plan_agent_assignments')->nullOnDelete();
        $table->string('title');
        $table->text('content');
        $table->string('status')->default('pending'); // pending | in_review | resolved
        $table->string('priority')->default('normal'); // low | normal | high | urgent
        $table->timestamp('reported_at')->nullable();
        $table->timestamp('resolved_at')->nullable();
        $table->timestamps();
        $table->softDeletes();

        $table->index(['agent_id', 'status']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
