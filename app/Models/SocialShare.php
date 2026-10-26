<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialShare extends Model
{
    use HasFactory;

    /**
     * Channels that represent an actual "shared somewhere" action.
     * The special `copy` channel means "caption copied to clipboard" —
     * it's useful for tracking intent but doesn't represent a real post.
     */
    public const CHANNEL_COPY      = 'copy';
    public const CHANNEL_FACEBOOK  = 'facebook';
    public const CHANNEL_WHATSAPP  = 'whatsapp';
    public const CHANNEL_TWITTER   = 'twitter';
    public const CHANNEL_LINKEDIN  = 'linkedin';
    public const CHANNEL_INSTAGRAM = 'instagram';
    public const CHANNEL_TIKTOK    = 'tiktok';

    public const CHANNELS = [
        self::CHANNEL_FACEBOOK,
        self::CHANNEL_WHATSAPP,
        self::CHANNEL_TWITTER,
        self::CHANNEL_LINKEDIN,
        self::CHANNEL_INSTAGRAM,
        self::CHANNEL_TIKTOK,
        self::CHANNEL_COPY,
    ];

    protected $fillable = [
        'ad_campaign_id',
        'shared_by',
        'channel',
        'shared_url',
        'shared_at',
    ];

    protected $casts = [
        'shared_at' => 'datetime',
    ];

    // ─── Relations ───────────────────────────────────────────

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    public function sharer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeForCampaign($query, int $campaignId)
    {
        return $query->where('ad_campaign_id', $campaignId);
    }

    public function scopeForChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeRecentFirst($query)
    {
        return $query->orderByDesc('shared_at');
    }
}