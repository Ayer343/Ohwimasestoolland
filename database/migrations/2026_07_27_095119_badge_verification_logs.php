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
    Schema::create('badge_verification_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('worker_badge_id')->constrained()->onDelete('cascade');
    $table->foreignId('security_post_id')->nullable()->constrained()->onDelete('set null');
    $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
    $table->string('verification_method'); // qr_scan, manual, nfc
    $table->string('ip_address')->nullable();
    $table->string('user_agent')->nullable();
    $table->json('location_data')->nullable(); // GPS if available
    $table->string('status'); // approved, rejected, expired, not_found
    $table->text('notes')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badge_verification_logs');
    }
};
