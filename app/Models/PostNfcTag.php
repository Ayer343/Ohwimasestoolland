<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostNfcTag extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'post_nfc_tags';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'post_id',
        'tag_id',
        'name',
        'metadata',
        'is_active',
        'last_used_at',
        'last_used_by',
        'registered_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'registered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array<int, string>
     */
    protected $dates = [
        'last_used_at',
        'registered_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'is_active' => true,
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the security post that owns this NFC tag.
     */
    public function post()
    {
        return $this->belongsTo(SecurityPost::class, 'post_id')->withTrashed();
    }

    /**
     * Get the user who last used this NFC tag.
     */
    public function lastUsedBy()
    {
        return $this->belongsTo(User::class, 'last_used_by')->withTrashed();
    }

    /**
     * Get all verification logs that used this NFC tag.
     */
    public function verificationLogs()
    {
        return $this->hasMany(VerificationLog::class, 'metadata->nfc_tag_id', 'id');
    }

    // ==================== SCOPES ====================

    /**
     * Scope a query to only include active NFC tags.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive NFC tags.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope a query to only include NFC tags for a specific post.
     */
    public function scopeForPost($query, $postId)
    {
        return $query->where('post_id', $postId);
    }

    /**
     * Scope a query to only include NFC tags with a specific tag ID.
     */
    public function scopeWithTagId($query, $tagId)
    {
        return $query->where('tag_id', $tagId);
    }

    /**
     * Scope a query to only include NFC tags used after a given date.
     */
    public function scopeUsedAfter($query, $date)
    {
        return $query->where('last_used_at', '>=', Carbon::parse($date));
    }

    /**
     * Scope a query to only include NFC tags used before a given date.
     */
    public function scopeUsedBefore($query, $date)
    {
        return $query->where('last_used_at', '<=', Carbon::parse($date));
    }

    /**
     * Scope a query to only include NFC tags that have been used.
     */
    public function scopeHasBeenUsed($query)
    {
        return $query->whereNotNull('last_used_at');
    }

    /**
     * Scope a query to only include NFC tags that have never been used.
     */
    public function scopeNeverUsed($query)
    {
        return $query->whereNull('last_used_at');
    }

    // ==================== ACCESSORS ====================

    /**
     * Get the NFC tag's status.
     */
    public function getStatusAttribute()
    {
        if (!$this->is_active) {
            return 'inactive';
        }
        
        return 'active';
    }

    /**
     * Get the NFC tag's status color for UI.
     */
    public function getStatusColorAttribute()
    {
        return $this->is_active ? 'success' : 'danger';
    }

    /**
     * Get the last used ago time.
     */
    public function getLastUsedAgoAttribute()
    {
        return $this->last_used_at ? $this->last_used_at->diffForHumans() : 'Never used';
    }

    /**
     * Get the usage count for this NFC tag.
     */
    public function getUsageCountAttribute()
    {
        return $this->verificationLogs()->count();
    }

    /**
     * Get the NFC tag's short ID.
     */
    public function getShortTagIdAttribute()
    {
        if (strlen($this->tag_id) <= 12) {
            return $this->tag_id;
        }
        
        return substr($this->tag_id, 0, 6) . '...' . substr($this->tag_id, -6);
    }

    /**
     * Get the registration age.
     */
    public function getRegisteredAgoAttribute()
    {
        return $this->registered_at->diffForHumans();
    }

    // ==================== MUTATORS ====================

    /**
     * Mark this NFC tag as used.
     */
    public function markAsUsed($userId = null)
    {
        $this->last_used_at = now();
        
        if ($userId) {
            $this->last_used_by = $userId;
        }
        
        $this->save();
    }

    /**
     * Activate this NFC tag.
     */
    public function activate()
    {
        $this->is_active = true;
        $this->save();
    }

    /**
     * Deactivate this NFC tag.
     */
    public function deactivate()
    {
        $this->is_active = false;
        $this->save();
    }

    // ==================== CUSTOM METHODS ====================

    /**
     * Register a new NFC tag.
     */
    public static function register($postId, $tagId, $name = null, $metadata = [])
    {
        return self::create([
            'post_id' => $postId,
            'tag_id' => $tagId,
            'name' => $name,
            'metadata' => array_merge([
                'registered_by' => auth()->id(),
                'registered_at' => now()->toDateTimeString(),
            ], $metadata),
            'registered_at' => now(),
        ]);
    }

    /**
     * Verify an NFC tag.
     */
    public function verify($tagId, $userId = null)
    {
        if ($this->tag_id !== $tagId) {
            return [
                'valid' => false,
                'reason' => 'Tag ID mismatch',
            ];
        }

        if (!$this->is_active) {
            return [
                'valid' => false,
                'reason' => 'NFC tag is inactive',
            ];
        }

        $this->markAsUsed($userId);

        return [
            'valid' => true,
            'post_id' => $this->post_id,
            'post_name' => $this->post->name,
            'tag_name' => $this->name,
            'used_at' => $this->last_used_at,
        ];
    }

    /**
     * Check if tag is valid.
     */
    public function isValid()
    {
        return $this->is_active;
    }

    /**
     * Get NFC tag statistics.
     */
    public static function getStats($postId = null)
    {
        $query = self::query();
        
        if ($postId) {
            $query->where('post_id', $postId);
        }

        return [
            'total' => $query->count(),
            'active' => (clone $query)->active()->count(),
            'inactive' => (clone $query)->inactive()->count(),
            'used' => (clone $query)->hasBeenUsed()->count(),
            'never_used' => (clone $query)->neverUsed()->count(),
            'by_post' => (clone $query)
                ->selectRaw('post_id, COUNT(*) as count')
                ->groupBy('post_id')
                ->with('post:id,name')
                ->get()
                ->mapWithKeys(function($item) {
                    return [$item->post->name => $item->count];
                })
                ->toArray(),
        ];
    }

    /**
     * Get usage history.
     */
    public function getUsageHistory($limit = 10)
    {
        return $this->verificationLogs()
            ->with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($log) {
                return [
                    'id' => $log->id,
                    'user' => $log->user->name ?? 'Unknown',
                    'action' => $log->action,
                    'status' => $log->status,
                    'used_at' => $log->created_at->format('Y-m-d H:i:s'),
                    'used_ago' => $log->created_at->diffForHumans(),
                ];
            });
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($nfcTag) {
            if (empty($nfcTag->registered_at)) {
                $nfcTag->registered_at = now();
            }
        });

        static::created(function ($nfcTag) {
            activity()
                ->performedOn($nfcTag)
                ->causedBy(auth()->user())
                ->log('NFC tag registered for post: ' . $nfcTag->post->name);
        });

        static::updated(function ($nfcTag) {
            if ($nfcTag->wasChanged('last_used_at')) {
                activity()
                    ->performedOn($nfcTag)
                    ->causedBy($nfcTag->lastUsedBy)
                    ->log('NFC tag used for verification');
            }
            
            if ($nfcTag->wasChanged('is_active')) {
                activity()
                    ->performedOn($nfcTag)
                    ->causedBy(auth()->user())
                    ->log('NFC tag ' . ($nfcTag->is_active ? 'activated' : 'deactivated'));
            }
        });
    }
}