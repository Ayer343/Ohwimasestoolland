<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_shares', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ad_campaign_id')
                ->constrained('ad_campaigns')
                ->cascadeOnDelete();

            $table->foreignId('shared_by')
                ->constrained('users')
                ->restrictOnDelete();

            // Channel the share was performed on.
            // Values: homepage (never recorded — always live), facebook,
            // whatsapp, twitter, linkedin, instagram, tiktok, copy.
            $table->string('channel', 40);

            // The exact URL that was shared. Nullable because the "copy"
            // channel (copy caption to clipboard) doesn't produce a URL.
            $table->string('shared_url', 500)->nullable();

            // When the admin clicked the share button. Kept as an explicit
            // column (not just created_at) so future backfills/deduplications
            // don't depend on the created_at timestamp.
            $table->timestamp('shared_at');

            $table->timestamps();

            // Fast lookups for "shares of campaign X, sorted by time".
            $table->index(['ad_campaign_id', 'shared_at']);

            // Fast lookups for "recent shares by channel" (admin dashboard).
            $table->index(['channel', 'shared_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_shares');
    }
};