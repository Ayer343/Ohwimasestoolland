<?php

namespace App\Events\Theme;

use App\DTOs\Theme\ThemeColorsDTO;
use App\DTOs\Theme\ThemeSettingsDTO;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThemeReset implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * Scope constants — mirrors the $scope property.
     */
    public const SCOPE_ALL      = 'all';
    public const SCOPE_SETTINGS = 'settings';
    public const SCOPE_COLORS   = 'colors';

    /**
     * When the reset occurred (UTC).
     */
    public readonly CarbonImmutable $occurredAt;

    public function __construct(
        /** The user whose theme was reset. */
        public readonly User $user,

        /**
         * What was reset: 'all', 'settings', or 'colors'.
         * Constrained by the SCOPE_* constants.
         */
        public readonly string $scope = self::SCOPE_ALL,

        /**
         * The settings state *before* the reset (null if scope excludes settings
         * or if unknown). Enables diffing in listeners.
         */
        public readonly ?ThemeSettingsDTO $previousSettings = null,

        /**
         * The colors state *before* the reset (null if scope excludes colors
         * or if unknown). Enables diffing in listeners.
         */
        public readonly ?ThemeColorsDTO $previousColors = null,

        /** The actor who performed the reset (null = self-service). */
        public readonly ?User $updatedBy = null,

        /** Optional source tag: 'web', 'api', 'admin', 'cli', 'import'. */
        public readonly ?string $source = null,

        /** Optional explicit timestamp (defaults to now). */
        ?CarbonImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? CarbonImmutable::now('UTC');

        if (!in_array($scope, self::allScopes(), true)) {
            throw new \InvalidArgumentException(
                "Invalid scope '{$scope}'. Expected one of: " . implode(', ', self::allScopes())
            );
        }
    }

    /**
     * ============================================
     * SCOPE HELPERS
     * ============================================
     */

    public static function allScopes(): array
    {
        return [self::SCOPE_ALL, self::SCOPE_SETTINGS, self::SCOPE_COLORS];
    }

    public function resetsSettings(): bool
    {
        return in_array($this->scope, [self::SCOPE_ALL, self::SCOPE_SETTINGS], true);
    }

    public function resetsColors(): bool
    {
        return in_array($this->scope, [self::SCOPE_ALL, self::SCOPE_COLORS], true);
    }

    public function isFullReset(): bool
    {
        return $this->scope === self::SCOPE_ALL;
    }

    /**
     * ============================================
     * DIFF HELPERS
     * ============================================
     */

    /**
     * Return only the settings fields that changed during the reset,
     * keyed by snake_case name. Empty array if no settings reset occurred.
     *
     * @return array<string, array{from: ?string, to: string}>
     */
    public function changedSettings(): array
    {
        if (!$this->resetsSettings() || $this->previousSettings === null) {
            return [];
        }

        $defaults = ThemeSettingsDTO::default()->toArray();
        $previous = $this->previousSettings->toArray();

        $changed = [];
        foreach ($defaults as $key => $defaultValue) {
            $oldValue = $previous[$key] ?? null;
            if ($oldValue !== $defaultValue) {
                $changed[$key] = ['from' => $oldValue, 'to' => $defaultValue];
            }
        }
        return $changed;
    }

    /**
     * Return only the color fields that changed during the reset,
     * keyed by snake_case name. Empty array if no colors reset occurred.
     *
     * @return array<string, array{from: ?string, to: string}>
     */
    public function changedColors(): array
    {
        if (!$this->resetsColors() || $this->previousColors === null) {
            return [];
        }

        $defaults = ThemeColorsDTO::default()->toArray();
        $previous = $this->previousColors->toArray();

        $changed = [];
        foreach ($defaults as $key => $defaultValue) {
            $oldValue = $previous[$key] ?? null;
            if ($oldValue !== $defaultValue) {
                $changed[$key] = ['from' => $oldValue, 'to' => $defaultValue];
            }
        }
        return $changed;
    }

    /**
     * True if anything actually changed as a result of the reset.
     * If nothing changed (already at defaults), listeners can no-op.
     */
    public function hasChanges(): bool
    {
        return !empty($this->changedSettings()) || !empty($this->changedColors());
    }

    /**
     * True when no distinct actor was provided (user reset their own theme).
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

    public function toArray(): array
    {
        return [
            'user_id'            => $this->user->id,
            'updated_by'         => $this->updatedBy?->id,
            'source'             => $this->source,
            'scope'              => $this->scope,
            'occurred_at'        => $this->occurredAt->toIso8601String(),
            'previous_settings'  => $this->previousSettings?->toArray(),
            'previous_colors'    => $this->previousColors?->toArray(),
            'changed_settings'   => $this->changedSettings(),
            'changed_colors'     => $this->changedColors(),
            'is_full_reset'      => $this->isFullReset(),
            'is_self'            => $this->isSelfUpdate(),
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
            user:             $user,
            scope:            $payload['scope'] ?? self::SCOPE_ALL,
            previousSettings: !empty($payload['previous_settings'])
                ? ThemeSettingsDTO::fromArray($payload['previous_settings'])
                : null,
            previousColors:   !empty($payload['previous_colors'])
                ? ThemeColorsDTO::fromArray($payload['previous_colors'])
                : null,
            updatedBy:        $updatedBy,
            source:           $payload['source'] ?? null,
            occurredAt:       !empty($payload['occurred_at'])
                ? CarbonImmutable::parse($payload['occurred_at'])
                : null,
        );
    }
}