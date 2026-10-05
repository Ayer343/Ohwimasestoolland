<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppTemplate extends Model
{
    /**
     * ✅ FIX: Laravel's snake-caser would derive `whats_app_templates`
     *    from `WhatsAppTemplate`, but the migration created `whatsapp_templates`.
     *    Setting the table name explicitly removes the mismatch and
     *    prevents SQLSTATE[42S02] "Table doesn't exist" errors.
     */
    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'name',
        'description',
        'content',
        'category',
        'status',
        'variables',
        'is_default',
        'provider_template_id',
        'last_synced_at',
        'sync_status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'variables'      => 'array',
        'is_default'     => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    protected $with = ['creator', 'updater'];

    /**
     * Get the creator
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the updater
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope for active templates
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for default templates
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get templates by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Replace variables in template content
     */
    public function render(array $data): string
    {
        $content = $this->content;

        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }
}