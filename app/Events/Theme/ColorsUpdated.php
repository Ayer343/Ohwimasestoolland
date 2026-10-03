<?php

namespace App\Events\Theme;

use App\DTOs\Theme\ThemeColorsDTO;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ColorsUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * When the event occurred (UTC).
     * Initialized in the constructor for explicit ordering.
     */
    public readonly CarbonImmutable $occurredAt;

    public function __construct(
        /** The user whose colors were changed. */
        public readonly User $user,

        /** The new (post-update) color state. */
        public readonly ThemeColorsDTO $colors,

        /** The previous color state, if known — enables diffing in listeners. */
        public readonly ?ThemeColorsDTO $previous = null,

        /** The actor who performed the change (defaults to $user if self-service). */
        public readonly ?User $updatedBy = null,

        /** Optional source tag: 'web', 'api', 'admin', 'cli', 'import'. */
        public readonly ?string $source = null,

        /** Optional explicit timestamp (defaults to now). */
        ?CarbonImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? CarbonImmutable::now('UTC');
    }

    /**
     * ============================================
     * DIFF HELPERS
     * ============================================
     */

    /**
     * Return only the fields that actually changed, keyed by snake_case name.
     * If $previous is null, returns the full current set (treat as "all new").
     *
     * @return array<string, array{from: ?string, to: string}>
     */
    public function changedColors(): array
    {
        $current = $this->colors->toArray();
        $prev    = $this->previous?->toArray() ?? [];

        $changed = [];
        foreach ($current as $key => $newValue) {
            $oldValue = $prev[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changed[$key] = ['from' => $oldValue, 'to' => $newValue];
            }
        }
        return $changed;
    }

    /**
     * List the snake_case keys that changed.
     *
     * @return string[]
     */
    public function changedKeys(): array
    {
        return array_keys($this->changedColors());
    }

    /**
     * True when at least one color differs from $previous.
     * If $previous is null, returns true (can't compare).
     */
    public function hasChanges(): bool
    {
        if ($this->previous === null) {
            return true;
        }
        return !empty($this->changedColors());
    }

    /**
     * True when the user reset colors back to defaults
     * (current state matches ThemeColorsDTO::default()).
     */
    public function isResetToDefaults(): bool
    {
        return !$this->colors->isCustom();
    }

    /**
     * True when the caller self-updated their own colors
     * (no distinct actor provided).
     */
    public function isSelfUpdate(): bool
    {
        return $this->updatedBy === null || $this->updatedBy->is($this->user);
    }

    /**
     * ============================================
     * SERIALIZATION
     * ============================================
     */

    /**
     * Convert to a plain array for logging / broadcasting / audit trails.
     * Includes both the state and the diff to make downstream consumers
     * self-sufficient.
     */
    public function toArray(): array
    {
        return [
            'user_id'      => $this->user->id,
            'updated_by'   => $this->updatedBy?->id,
            'source'       => $this->source,
            'occurred_at'  => $this->occurredAt->toIso8601String(),
            'colors'       => $this->colors->toArray(),
            'previous'     => $this->previous?->toArray(),
            'changed'      => $this->changedColors(),
            'is_reset'     => $this->isResetToDefaults(),
            'is_self'      => $this->isSelfUpdate(),
        ];
    }

    /**
     * Optional: allow direct JSON encoding.
     * Useful when the event doubles as a broadcast payload.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Laravel's default serialization route (queueing) uses PHP serialize().
     * Providing __serialize()/__unserialize() lets us control the payload
     * and future-proof against adding non-serializable fields.
     *
     * Note: because properties are readonly, __unserialize() builds a new
     * instance via the constructor — but PHP's unserialize() machinery calls
     * __unserialize() on an uninitialized object. We can't reassign readonly
     * props, so instead we use a helper static that returns a hydrated copy
     * and rely on `__unserialize` being invoked only when a matching
     * `__serialize` shaped payload is restored — Laravel already handles
     * this by re-fetching Eloquent models via SerializesModels, so we don't
     * need to rehydrate manually. To keep things simple we implement
     * __serialize only.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'user_id'      => $this->user->getKey(),
            'updated_by'   => $this->updatedBy?->getKey(),
            'source'       => $this->source,
            'occurred_at'  => $this->occurredAt->format(DATE_ATOM),
            'colors'       => $this->colors->toArray(),
            'previous'     => $this->previous?->toArray(),
        ];
    }

    /**
     * Rehydrate from a __serialize() payload.
     * Models are re-fetched by Laravel's SerializesModels when the queue
     * restores them, so we resolve IDs here.
     *
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        // Resolve the User models from their IDs.
        $user       = User::findOrFail($data['user_id']);
        $updatedBy  = isset($data['updated_by']) && $data['updated_by'] !== null
            ? User::find($data['updated_by'])
            : null;

        // Rebuild DTOs.
        $colors   = ThemeColorsDTO::fromArray($data['colors'] ?? []);
        $previous = isset($data['previous']) && $data['previous'] !== null
            ? ThemeColorsDTO::fromArray($data['previous'])
            : null;

        $occurredAt = CarbonImmutable::parse($data['occurred_at'] ?? 'now');

        // Because the properties are readonly, we cannot assign them here.
        // Instead, we throw if the object wasn't already hydrated — this
        // signals a misuse of native PHP unserialize on this class.
        //
        // Laravel's queue uses SerializesModels which restores Eloquent
        // models *before* unserialize() finalizes, but readonly props
        // still can't be mutated here.
        //
        // The pragmatic approach: don't rely on native unserialize for
        // readonly DTOs. Keep the payload minimal and let SerializesModels
        // reconstruct models via their IDs; DTOs are cheap to rebuild.
        throw new \LogicException(
            'ColorsUpdated must be reconstructed via fromPayload() rather than native unserialize.'
        );
    }

    /**
     * ============================================
     * RECONSTRUCTION
     * ============================================
     * Explicit factory for safely rebuilding from a payload
     * (e.g., reading from an audit log, replaying events, testing).
     */
    public static function fromPayload(array $payload): self
    {
        $user = User::findOrFail($payload['user_id']);
        $updatedBy = !empty($payload['updated_by'])
            ? User::find($payload['updated_by'])
            : null;

        return new self(
            user:       $user,
            colors:     ThemeColorsDTO::fromArray($payload['colors'] ?? []),
            previous:   !empty($payload['previous'])
                ? ThemeColorsDTO::fromArray($payload['previous'])
                : null,
            updatedBy:  $updatedBy,
            source:     $payload['source'] ?? null,
            occurredAt: !empty($payload['occurred_at'])
                ? CarbonImmutable::parse($payload['occurred_at'])
                : null,
        );
    }
}