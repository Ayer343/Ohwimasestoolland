<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Email extends Model
{
    use SoftDeletes;

    /**
     * Foreign key on the `emails` table pointing at `user_email_accounts.id`.
     *
     * ⚠️ MUST match the actual DB column name exactly.
     * Verified via SHOW CREATE TABLE: `user_email_account_id`.
     */
    public const ACCOUNT_FK = 'user_email_account_id';

    /**
     * Columns that may be mass-assigned.
     *
     * ⚠️ Every field EmailService writes must be listed here, or Laravel
     * will silently drop it and let MySQL use the column default
     * (which is what caused the `status => 'draft'` bug).
     */
    protected $fillable = [
        // ── Account linkage ──────────────────────────────────────
        self::ACCOUNT_FK,       // 'user_email_account_id'
        'user_id',

        // ── Message identity ─────────────────────────────────────
        'message_id',
        'thread_id',
        'parent_id',
        'in_reply_to',
        'references',

        // ── Headers ──────────────────────────────────────────────
        'from_email',
        'from_name',
        'to_email',
        'to_name',
        'cc',
        'bcc',
        'reply_to_email',
        'reply_to_name',
        'subject',

        // ── Body ─────────────────────────────────────────────────
        'body',
        'html_body',
        'text_body',
        'body_preview',

        // ── Classification ───────────────────────────────────────
        'folder',
        'direction',
        'status',
        'priority',

        // ── Flags ────────────────────────────────────────────────
        'is_read',
        'is_replied',
        'is_forwarded',
        'is_important',
        'is_spam',
        'is_trashed',
        'is_draft',

        // ── Attachments ──────────────────────────────────────────
        'attachment_count',
        'attachments_info',
        'size_bytes',

        // ── Timestamps ───────────────────────────────────────────
        'sent_at',
        'received_at',
        'read_at',
        'replied_at',
        'forwarded_at',

        // ── Extras ───────────────────────────────────────────────
        'flags',
        'metadata',
        'headers',

        // ── Soft deletes ─────────────────────────────────────────
        'deleted_at',
    ];

    /**
     * Attribute casts.
     *
     * ⚠️ Without the boolean casts, MySQL returns `0`/`1` as strings and
     * `if ($email->is_read)` behaves unpredictably. Without the JSON
     * casts, `cc`/`bcc` come back as raw JSON strings instead of arrays.
     */
    protected $casts = [
        // JSON columns
        'cc'               => 'array',
        'bcc'              => 'array',
        'flags'            => 'array',
        'metadata'         => 'array',
        'headers'          => 'array',
        'attachments_info' => 'array',

        // Booleans
        'is_read'          => 'boolean',
        'is_replied'       => 'boolean',
        'is_forwarded'     => 'boolean',
        'is_important'     => 'boolean',
        'is_spam'          => 'boolean',
        'is_trashed'       => 'boolean',
        'is_draft'         => 'boolean',

        // Datetimes
        'sent_at'          => 'datetime',
        'received_at'      => 'datetime',
        'read_at'          => 'datetime',
        'replied_at'       => 'datetime',
        'forwarded_at'     => 'datetime',
        'deleted_at'       => 'datetime',

        // Integers
        'attachment_count' => 'integer',
        'size_bytes'       => 'integer',
    ];

    // =====================================================================
    // Relationships
    // =====================================================================

    /**
     * The email account this message belongs to.
     *
     * Uses the ACCOUNT_FK constant so renaming the column only requires
     * updating that one line.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(
            UserEmailAccount::class,
            self::ACCOUNT_FK,   // 'user_email_account_id'
            'id'                // owner key on user_email_accounts
        );
    }

    /**
     * Alias for `account()`.
     *
     * Some older code paths (and the controller's `viewEmail` handler)
     * call `$email->emailAccount`. Keep the alias so nothing breaks.
     */
    public function emailAccount(): BelongsTo
    {
        return $this->account();
    }

    /**
     * The user who owns this email (denormalized via `user_id` column).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // =====================================================================
    // Query scopes
    // =====================================================================

    /**
     * Restrict to a specific email account.
     *
     * Usage:
     *   Email::forAccount($accountId)->get();
     *   Email::forAccount($accountId)->find($emailId);
     */
    public function scopeForAccount(Builder $query, int|string $accountId): Builder
    {
        return $query->where(self::ACCOUNT_FK, $accountId);
    }

    /**
     * Only unread emails.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    /**
     * Only read emails.
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->where('is_read', true);
    }

    /**
     * Incoming emails — matches enum value 'incoming'.
     *
     * ⚠️ Do NOT use 'received' — the schema enum is ('incoming','outgoing').
     */
    public function scopeIncoming(Builder $query): Builder
    {
        return $query->where('direction', 'incoming');
    }

    /**
     * Outgoing emails — matches enum value 'outgoing'.
     *
     * ⚠️ Do NOT use 'sent' — the schema enum is ('incoming','outgoing').
     */
    public function scopeOutgoing(Builder $query): Builder
    {
        return $query->where('direction', 'outgoing');
    }

    /**
     * Filter by folder (INBOX, SENT, DRAFTS, etc.).
     */
    public function scopeInFolder(Builder $query, string $folder): Builder
    {
        return $query->where('folder', strtoupper($folder));
    }

    /**
     * Only spam-flagged emails.
     */
    public function scopeSpam(Builder $query): Builder
    {
        return $query->where('is_spam', true);
    }

    /**
     * Only non-spam, non-trashed emails.
     */
    public function scopeClean(Builder $query): Builder
    {
        return $query->where('is_spam', false)->where('is_trashed', false);
    }

    // =====================================================================
    // Safe delete helper
    // =====================================================================

    /**
     * Delete an email scoped to an account without throwing.
     *
     * Returns:
     *   true  — row was found and deleted
     *   false — row was already gone OR belonged to a different account
     *
     * Never throws ModelNotFoundException.
     *
     * @param  bool  $force  If true, hard-delete (bypass soft deletes).
     *                       Recommended for IMAP-synced messages so that
     *                       a re-sync doesn't hit `emails_account_message_unique`.
     *
     * Usage in a controller:
     *   $ok = Email::deleteSafely($accountId, $emailId, force: true);
     *   // respond with success either way — the row is gone now
     */
    public static function deleteSafely(
        int|string $accountId,
        int|string $emailId,
        bool $force = false
    ): bool {
        $query = static::forAccount($accountId);

        if ($force) {
            $query->withTrashed();
        }

        $email = $query->find($emailId);

        if (! $email) {
            return false;
        }

        return (bool) ($force ? $email->forceDelete() : $email->delete());
    }

    // =====================================================================
    // Accessors
    // =====================================================================

    /**
     * Short text preview of the email — for list views.
     */
    public function getPreviewAttribute(): string
    {
        $text = $this->body ?: strip_tags($this->html_body ?? '');
        $text = trim(preg_replace('/\s+/', ' ', $text));

        return Str::limit($text, 120);
    }

    /**
     * Formatted sender — "Name <email>" or just the email address.
     */
    public function getFromDisplayAttribute(): string
    {
        if ($this->from_name && $this->from_email) {
            return "{$this->from_name} <{$this->from_email}>";
        }

        return $this->from_email ?? '(unknown sender)';
    }

    /**
     * Formatted recipient — "Name <email>" or just the email address.
     */
    public function getToDisplayAttribute(): string
    {
        if ($this->to_name && $this->to_email) {
            return "{$this->to_name} <{$this->to_email}>";
        }

        return $this->to_email ?? '(unknown recipient)';
    }

    /**
     * Friendly direction label for the UI.
     */
    public function getDirectionLabelAttribute(): string
    {
        return match ($this->direction) {
            'incoming' => 'Received',
            'outgoing' => 'Sent',
            default    => ucfirst((string) $this->direction),
        };
    }
    /**
 * Derived boolean — "does this email have attachments?"
 *
 * The schema has no `has_attachments` column, so we compute it from
 * `attachment_count` on the fly. This keeps the DB lean and means
 * there's never a risk of the two columns drifting out of sync.
 */
public function getHasAttachmentsAttribute(): bool
{
    return ($this->attachment_count ?? 0) > 0;
}

}