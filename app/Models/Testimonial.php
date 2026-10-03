<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Testimonial extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'role',
        'content',
        'rating',
        'avatar',
        'property_location',
        'is_approved',
        'is_featured',
        'display_order',
        'approved_at',
        'approved_by',
        'metadata'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_approved' => 'boolean',
        'is_featured' => 'boolean',
        'rating' => 'integer',
        'display_order' => 'integer',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'avatar_url',
        'star_rating',
        'excerpt',
        'rating_percentage',
        'status_label',
        'status_color'
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * Get the user who submitted this testimonial.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the admin who approved this testimonial.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ============================================
    // SCOPES
    // ============================================

    /**
     * Scope a query to only include approved testimonials.
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope a query to only include pending testimonials.
     */
    public function scopePending($query)
    {
        return $query->where('is_approved', false);
    }

    /**
     * Scope a query to only include featured testimonials.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope a query to order by display_order then created_at.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order', 'asc')->orderBy('created_at', 'desc');
    }

    /**
     * Scope a query to only include testimonials with minimum rating.
     */
    public function scopeHighRated($query, $minRating = 4)
    {
        return $query->where('rating', '>=', $minRating);
    }

    /**
     * Scope a query to filter by rating range.
     */
    public function scopeRatingBetween($query, $min, $max)
    {
        return $query->whereBetween('rating', [$min, $max]);
    }

    /**
     * Scope a query to search by name, email, or content.
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function($q) use ($searchTerm) {
            $q->where('name', 'like', "%{$searchTerm}%")
              ->orWhere('email', 'like', "%{$searchTerm}%")
              ->orWhere('content', 'like', "%{$searchTerm}%")
              ->orWhere('role', 'like', "%{$searchTerm}%");
        });
    }

    /**
     * Scope a query to only include testimonials submitted by registered users.
     */
    public function scopeFromRegisteredUsers($query)
    {
        return $query->whereNotNull('user_id');
    }

    /**
     * Scope a query to only include guest submissions.
     */
    public function scopeFromGuests($query)
    {
        return $query->whereNull('user_id');
    }

    /**
     * Scope a query for a specific date range.
     */
    public function scopeDateBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // ============================================
    // ACCESSORS & MUTATORS
    // ============================================

    /**
     * Get the avatar URL attribute.
     */
    public function getAvatarUrlAttribute()
    {
        if ($this->avatar && Storage::disk('public')->exists($this->avatar)) {
            return asset('storage/' . $this->avatar);
        }
        
        // Check if user has avatar
        if ($this->user && $this->user->avatar_url) {
            return $this->user->avatar_url;
        }
        
        // Generate initials avatar
        $initials = strtoupper(substr($this->name, 0, 2));
        $colors = ['2c76c9', '10b981', 'f59e0b', 'ef4444', '8b5cf6', 'ec489a'];
        $color = $colors[array_rand($colors)];
        
        return "https://ui-avatars.com/api/?name={$initials}&background={$color}&color=fff&size=100&bold=true";
    }

    /**
     * Get the star rating as a string of stars.
     */
    public function getStarRatingAttribute()
    {
        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }

    /**
     * Get the rating as a percentage.
     */
    public function getRatingPercentageAttribute()
    {
        return ($this->rating / 5) * 100;
    }

    /**
     * Get an excerpt of the content.
     */
    public function getExcerptAttribute($length = 120)
    {
        if (strlen($this->content) <= $length) {
            return $this->content;
        }
        
        $excerpt = substr($this->content, 0, $length);
        $lastSpace = strrpos($excerpt, ' ');
        
        if ($lastSpace !== false) {
            $excerpt = substr($excerpt, 0, $lastSpace);
        }
        
        return $excerpt . '...';
    }

    /**
     * Get a longer excerpt (for listing pages).
     */
    public function getLongExcerptAttribute($length = 200)
    {
        if (strlen($this->content) <= $length) {
            return $this->content;
        }
        
        $excerpt = substr($this->content, 0, $length);
        $lastSpace = strrpos($excerpt, ' ');
        
        if ($lastSpace !== false) {
            $excerpt = substr($excerpt, 0, $lastSpace);
        }
        
        return $excerpt . '...';
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute()
    {
        if ($this->trashed()) {
            return 'Deleted';
        }
        
        return $this->is_approved ? 'Approved' : 'Pending';
    }

    /**
     * Get the status color for badges.
     */
    public function getStatusColorAttribute()
    {
        if ($this->trashed()) {
            return 'danger';
        }
        
        return $this->is_approved ? 'success' : 'warning';
    }

    /**
     * Get the featured badge label.
     */
    public function getFeaturedLabelAttribute()
    {
        return $this->is_featured ? 'Featured' : 'Not Featured';
    }

    /**
     * Get the days since submission.
     */
    public function getDaysSinceSubmissionAttribute()
    {
        return $this->created_at->diffInDays(now());
    }

    /**
     * Get the days in trash (if soft deleted).
     */
    public function getDaysInTrashAttribute()
    {
        if ($this->trashed() && $this->deleted_at) {
            return $this->deleted_at->diffInDays(now());
        }
        
        return 0;
    }

    /**
     * Check if testimonial is expiring soon (25+ days in trash).
     */
    public function getIsExpiringSoonAttribute()
    {
        return $this->days_in_trash >= 25;
    }

    /**
     * Get days until permanent deletion.
     */
    public function getDaysUntilPermanentAttribute()
    {
        if ($this->trashed()) {
            return max(0, 30 - $this->days_in_trash);
        }
        
        return 0;
    }

    /**
     * Get the user type name (for display).
     */
    public function getUserTypeNameAttribute()
    {
        if ($this->user) {
            return $this->user->getTypeName();
        }
        
        return 'Guest';
    }

    /**
     * Get the submission method (from metadata).
     */
    public function getSubmissionMethodAttribute()
    {
        return $this->metadata['submission_method'] ?? 'Unknown';
    }

    /**
     * Get the IP address from metadata.
     */
    public function getIpAddressAttribute()
    {
        return $this->metadata['ip_address'] ?? 'Unknown';
    }

    /**
     * Get the user agent from metadata.
     */
    public function getUserAgentAttribute()
    {
        return $this->metadata['user_agent'] ?? 'Unknown';
    }

    /**
     * Get the rejection reason if rejected.
     */
    public function getRejectionReasonAttribute()
    {
        return $this->metadata['rejection_reason'] ?? null;
    }

    /**
     * Check if testimonial was rejected.
     */
    public function getWasRejectedAttribute()
    {
        return isset($this->metadata['rejected_at']);
    }

    /**
     * Get the admin notes.
     */
    public function getAdminNotesAttribute()
    {
        return $this->metadata['admin_notes'] ?? null;
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Check if testimonial can be edited.
     */
    public function canBeEdited()
    {
        return !$this->trashed() && !$this->is_approved;
    }

    /**
     * Check if testimonial can be approved.
     */
    public function canBeApproved()
    {
        return !$this->trashed() && !$this->is_approved;
    }

    /**
     * Check if testimonial can be featured.
     */
    public function canBeFeatured()
    {
        return !$this->trashed() && $this->is_approved;
    }

    /**
     * Check if testimonial can be restored.
     */
    public function canBeRestored()
    {
        return $this->trashed();
    }

    /**
     * Check if testimonial can be permanently deleted.
     */
    public function canBePermanentlyDeleted()
    {
        return $this->trashed();
    }

    /**
     * Get the rating distribution for all approved testimonials.
     */
    public static function getRatingDistribution()
    {
        return [
            5 => self::approved()->where('rating', 5)->count(),
            4 => self::approved()->where('rating', 4)->count(),
            3 => self::approved()->where('rating', 3)->count(),
            2 => self::approved()->where('rating', 2)->count(),
            1 => self::approved()->where('rating', 1)->count(),
        ];
    }

    /**
     * Get average rating for approved testimonials.
     */
    public static function getAverageRating()
    {
        return round(self::approved()->avg('rating') ?? 0, 1);
    }

    /**
     * Get total number of approved testimonials.
     */
    public static function getTotalApproved()
    {
        return self::approved()->count();
    }

    /**
     * Get monthly submission stats.
     */
    public static function getMonthlyStats($months = 6)
    {
        return self::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit($months)
            ->get();
    }

    /**
     * Get authentication type breakdown.
     */
    public static function getAuthTypeBreakdown()
    {
        return [
            'authenticated' => self::whereNotNull('user_id')->count(),
            'guest' => self::whereNull('user_id')->count(),
        ];
    }

    // ============================================
    // BOOT METHOD
    // ============================================

    /**
     * Boot the model and add event listeners.
     */
    protected static function boot()
    {
        parent::boot();
        
        // Set default display order when creating
        static::creating(function ($testimonial) {
            if (empty($testimonial->display_order)) {
                $maxOrder = self::max('display_order') ?? 0;
                $testimonial->display_order = $maxOrder + 1;
            }
        });
        
        // Log when testimonial is approved - FIXED: changed $testiminal to $testimonial
        static::updating(function ($testimonial) {
            if ($testimonial->isDirty('is_approved') && $testimonial->is_approved && !$testimonial->getOriginal('is_approved')) {
                \Illuminate\Support\Facades\Log::info('Testimonial approved', [
                    'testimonial_id' => $testimonial->id,
                    'approved_by' => auth()->id()
                ]);
            }
        });
    }
}