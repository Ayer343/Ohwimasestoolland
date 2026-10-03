<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Email extends Model
{
    // ✅ FIX: Enable soft deletes so "deleting twice" never throws
    // a ModelNotFoundException — the second call just no-ops or restores.
    // If you do NOT want soft deletes, delete this `use SoftDeletes;` line
    // and remove `deleted_at` from $fillable + $casts below.
    use SoftDeletes;

    /**
     * ✅ FIX: Explicitly declare the foreign key used by `emailAccount()`.
     *
     * Laravel's default would be `user_email_account_id`, but every
     * controller / query in this app uses `account_id`. Making it explicit
     * prevents silent mismatches when the DB column name differs from
     * the class-based convention.
     *
     * If your DB column really is `user_email_account_id`, change this
     * constant to that value — nothing else in the model needs to move.
     */
    public const ACCOUNT_FK = 'account_id';

    protected $fillable = [
        // ✅ FIX: Use the constant so it stays in sync with emailAccount()
        self::ACCOUNT_FK, // 'account_id'
        'message_id',
        'subject',
        'body',
        'html_body',
        'from_email',
        'from_name',
        'to_email',
        'to_name',
        'cc',
        'bcc',
        'direction', // 'sent' or 'received'
        'is_read',
        'is_replied',
        'is_forwarded',
        'is_important',
        'is_spam',
        'folder',
        'thread_id',
        'parent_id',
        'sent_at',
        'received_at',
        'metadata',
        // ✅ FIX: needed by SoftDeletes if you keep the trait
        'deleted_at',
    ];

    protected $casts = [
        'cc' => 'array',
        'bcc' => 'array',
        'is_read' => 'boolean',
        'is_replied' => 'boolean',
        'is_forwarded' => 'boolean',
        'is_important' => 'boolean',
        'is_spam' => 'boolean',
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
        // ✅ FIX: needed by SoftDeletes
        'deleted_at' => 'datetime',
    ];

    /**
     * ✅ FIX: Explicit foreign key + owner key on the relationship.
     * Without this, Laravel guesses `user_email_account_id`, which
     * doesn't match the rest of the app.
     */
    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(
            UserEmailAccount::class,
            self::ACCOUNT_FK,        // foreign key on `emails` table
            'id'                     // owner key on `user_email_accounts` table
        );
    }

    // =====================================================================
    // ✅ FIX: Query scopes for safe account-scoped lookups
    // =====================================================================
    // These make the idempotent delete pattern from the previous message
    // a one-liner and prevent the class of bug that produced your
    // "No query results for model [App\Models\Email] 2" error.

    /**
     * Scope: restrict to a specific email account.
     *
     * Usage:
     *   Email::forAccount($accountId)->find($emailId);
     */
    public function scopeForAccount(Builder $query, int|string $accountId): Builder
    {
        return $query->where(self::ACCOUNT_FK, $accountId);
    }

    /**
     * Scope: only unread / read / flagged / etc. — optional convenience.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeReceived(Builder $query): Builder
    {
        return $query->where('direction', 'received');
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('direction', 'sent');
    }

    // =====================================================================
    // ✅ FIX: Safe delete helpers — the actual fix for your error
    // =====================================================================
    // Call these from your controller instead of `findOrFail()->delete()`.
    // They return a bool and never throw ModelNotFoundException.

    /**
     * Safely delete an email scoped to an account.
     *
     * Returns true if a row was found & deleted, false if it was already
     * gone or belonged to a different account. Never throws.
     *
     * Usage in a controller:
     *   $deleted = Email::deleteSafely($accountId, $emailId);
     *   if (!$deleted) { // already gone — respond success anyway }
     */
    public static function deleteSafely(int|string $accountId, int|string $emailId): bool
    {
        $email = static::forAccount($accountId)->find($emailId);

        if (! $email) {
            return false; // already deleted, or wrong account — both are "no-op"
        }

        return (bool) $email->delete();
    }

    // =====================================================================
    // Convenience accessors for the UI (optional)
    // =====================================================================

    /**
     * Short preview of the email body for list views.
     */
    public function getPreviewAttribute(): string
    {
        $text = $this->body ?: strip_tags($this->html_body ?? '');
        $text = trim(preg_replace('/\s+/', ' ', $text));

        return \Illuminate\Support\Str::limit($text, 120);
    }

    /**
     * Display name for the sender: "Name <email>" or just the email.
     */
    public function getFromDisplayAttribute(): string
    {
        if ($this->from_name && $this->from_email) {
            return "{$this->from_name} <{$this->from_email}>";
        }

        return $this->from_email ?? '(unknown sender)';
    }
}