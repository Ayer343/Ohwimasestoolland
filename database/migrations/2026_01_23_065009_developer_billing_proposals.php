<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('developer_billing_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('developer_setting_id')->constrained()->onDelete('cascade');
            $table->decimal('old_amount', 10, 2);
            $table->decimal('new_amount', 10, 2);
            $table->string('currency', 3)->default('GHS');
            $table->enum('cycle', ['monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->foreignId('proposed_by')->constrained('users')->onDelete('cascade');
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            
            $table->index(['developer_setting_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('developer_billing_proposals');
    }
};