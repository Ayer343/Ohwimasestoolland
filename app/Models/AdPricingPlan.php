<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdPricingPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'billing_period', 'duration_months', 'price',
        'currency', 'discount_percent', 'description', 'sort_order',
        'is_active', 'effective_from', 'effective_to', 'created_by',
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'duration_months'  => 'integer',
        'sort_order'       => 'integer',
        'is_active'        => 'boolean',
        'effective_from'   => 'datetime',
        'effective_to'     => 'datetime',
    ];

    public function scopeActive($query)
    {
        $now = now();
        return $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $now);
            })
            ->orderBy('sort_order');
    }

    public function getFormattedPriceAttribute(): string
    {
        return ($this->currency === 'GHS' ? '₵' : $this->currency . ' ')
            . number_format((float) $this->price, 2);
    }
}