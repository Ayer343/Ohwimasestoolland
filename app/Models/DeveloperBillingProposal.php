<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeveloperBillingProposal extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'developer_setting_id',
        'old_amount',
        'new_amount',
        'currency',
        'cycle',
        'proposed_by',
        'reason',
        'status',
        'reviewed_by',
        'review_notes',
        'reviewed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'old_amount' => 'decimal:2',
        'new_amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the developer settings associated with the proposal.
     */
    public function developerSetting(): BelongsTo
    {
        return $this->belongsTo(DeveloperSetting::class, 'developer_setting_id');
    }

    /**
     * Get the user who proposed the billing change.
     */
    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    /**
     * Get the user who reviewed the proposal.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope for pending proposals.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for approved proposals.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for rejected proposals.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Check if the proposal is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the proposal is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the proposal is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Get the amount difference.
     */
    public function getAmountDifferenceAttribute(): float
    {
        return (float) $this->new_amount - (float) $this->old_amount;
    }

    /**
     * Get the percentage change.
     */
    public function getPercentageChangeAttribute(): float
    {
        if ($this->old_amount == 0) {
            return 0;
        }
        
        return ((float) $this->new_amount - (float) $this->old_amount) / (float) $this->old_amount * 100;
    }
}