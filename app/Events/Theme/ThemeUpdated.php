<?php

namespace App\Events\Theme;

use App\DTOs\Theme\ThemeColorsDTO;
use App\DTOs\Theme\ThemeSettingsDTO;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JsonSerializable;

class ThemeUpdated implements ShouldDispatchAfterCommit, JsonSerializable
{
    use Dispatchable, SerializesModels;

    /**
     * When the update occurred (UTC).
     */
    public readonly CarbonImmutable $occurredAt;

    public function __construct(
        /** The user whose theme settings were updated. */
        public readonly User $user,

        /** The new (post-update) settings state. */
        public readonly ThemeSettingsDTO $settings,

        /** The previous settings state, if known — enables diffing. */
        public readonly ?ThemeSettingsDTO $previous = null,

        /** The actor who performed the update (null = self-service). */
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
     * Return only the settings fields that actually changed, keyed by
     * snake_case name. If $previous is null, returns the full current set.
     *
     * Note: 'custom_colors' is *excluded* from this diff by default — it's
     * colors, not settings. See changedNestedColors() for that.
     *
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function changedSettings(): array
    {
        $current = $this->settings->toArray();
        $prev    = $this->previous?->toArray() ?? [];

        // Don't fold nested colors into settings diff.
        unset($current['custom_colors']);

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
     * List the snake_case settings keys that changed.
     *
     * @return string[]
     */
    public function changedKeys(): array
    {
        return array_keys($this->changedSettings());
    }

    /**
     * True when at least one settings field differs from $previous.
     * If $previous is null, returns true (can't compare).
     */
    public function hasChanges(): bool
    {
        if ($this->previous === null) {
            return true;
        }
        return !empty($this->changedSettings());
    }

    /**
     * Diff the nested custom colors, if both current and previous have them.
     * Returns snake_case color keys → ['from' => ..., 'to' => ...].
     *
     * If previous colors are unknown, returns [].
     *
     * @return array<string, array{from: ?string, to: string}>
     */
    public function changedNestedColors(): array
    {
        $current = $this->settings->customColors ?? null;
        $prev    = $this->previous?->customColors ?? null;

        if ($current === null || $prev === null) {
            return [];
        }

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
     * True if the nested custom colors changed as part of this update.
     */
    public function hasNestedColorChanges(): bool
    {
        return !empty($this->changedNestedColors());
    }

    /**
     * True when the update includes any colors payload (used or not).
     * Useful to detect potential overlap with ColorsUpdated.
     */
    public function carriesColorsPayload(): bool
    {
        return $this->settings->customColors !== null;
    }

    /**
     * True when the caller self-updated their own theme.
     */
    public function isSelfUpdate(): bool
    {
        return $this->updatedBy === null || $this->updatedBy->is($this->user);
    }

    /**
     * Convenience: nested colors as a DTO, if present.
     */
    public function nestedColorsAsDTO(): ?ThemeColorsDTO
    {
        return $this->carriesColorsPayload()
            ? ThemeColorsDTO::fromArray($this->settings->customColors)
            : null;
    }

    /**
     * ============================================
     * SERIALIZATION
     * ============================================
     */

    public function toArray(): array
    {
        return [
            'user_id'               => $this->user->id,
            'updated_by'            => $this->updatedBy?->id,
            'source'                => $this->source,
            'occurred_at'           => $this->occurredAt->toIso8601String(),
            'settings'              => $this->settings->toArray(),
            'previous'              => $this->previous?->toArray(),
            'changed_settings'      => $this->changedSettings(),
            'changed_nested_colors' => $this->changedNestedColors(),
            'has_nested_colors'     => $this->carriesColorsPayload(),
            'is_self'               => $this->isSelfUpdate(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * ============================================
     * RECONSTRUCTION
     * ============================================
     */

    /**
     * Rebuild from a toArray() payload (audit replay, testing, custom transports).
     */
    public static function fromPayload(array $payload): self
    {
        $user = User::findOrFail($payload['user_id']);
        $updatedBy = !empty($payload['updated_by'])
            ? User::find($payload['updated_by'])
            : null;

        return new self(
            user:       $user,
            settings:   ThemeSettingsDTO::fromArray($payload['settings'] ?? []),
            previous:   !empty($payload['previous'])
                ? ThemeSettingsDTO::fromArray($payload['previous'])
                : null,
            updatedBy:  $updatedBy,
            source:     $payload['source'] ?? null,
            occurredAt: !empty($payload['occurred_at'])
                ? CarbonImmutable::parse($payload['occurred_at'])
                : null,
        );
    }
}