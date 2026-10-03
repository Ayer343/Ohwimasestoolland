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
    Schema::create('worker_badges', function (Blueprint $table) {
    $table->id();
    $table->foreignId('construction_worker_id')->constrained()->onDelete('cascade');
    $table->foreignId('construction_contract_id')->constrained()->onDelete('cascade');
    $table->string('badge_number')->unique(); // Format: WB-YYYY-XXXXX
    $table->string('qr_code')->unique(); // QR code identifier
    $table->string('card_number')->unique(); // Physical card number (if needed)
    $table->string('email_sent_to');
    $table->datetime('email_sent_at');
    $table->datetime('last_verified_at')->nullable();
    $table->integer('verification_count')->default(0);
    $table->boolean('is_active')->default(true);
    $table->datetime('valid_from');
    $table->datetime('valid_until');
    $table->json('security_features')->nullable(); // Face ID, biometrics etc
    $table->string('photo_path')->nullable(); // Worker photo
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['badge_number', 'is_active']);
    $table->index(['construction_worker_id', 'valid_until']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('worker_badges');
    }
};
