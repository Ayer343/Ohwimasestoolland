<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('developer_setting_id')->constrained('developer_settings')->onDelete('cascade');
            $table->string('invoice_number')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('GHS');
            $table->text('description')->nullable();
            $table->enum('invoice_type', ['recurring', 'one_time', 'additional', 'penalty', 'adjustment'])->default('recurring');
            $table->date('issue_date');
            $table->date('due_date');
            $table->integer('payment_due_days')->default(30);
            $table->enum('status', ['pending', 'paid', 'overdue', 'cancelled', 'partial'])->default('pending');
            $table->boolean('is_recurring')->default(false);
            $table->boolean('is_custom')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('billing_cycle_reference')->nullable();
            $table->text('notes')->nullable();
            $table->json('items')->nullable();
            $table->timestamps();
            
            $table->index(['developer_setting_id', 'status']);
            $table->index(['due_date', 'status']);
            $table->index('invoice_number');
        });
    }

    public function down()
    {
        Schema::dropIfExists('billing_invoices');
    }
};