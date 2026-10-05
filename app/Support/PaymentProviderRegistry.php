<?php

namespace App\Support;

use Illuminate\Support\Collection;

class PaymentProviderRegistry
{
    /**
     * All provider keys in stable display order.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return static::all()
            ->sortBy('sort')
            ->keys()
            ->values()
            ->all();
    }

    /**
     * Full provider definitions keyed by provider key.
     */
    public static function all(): Collection
    {
        return collect(config('payment_providers.providers', []));
    }

    /**
     * Get a single provider definition, or null.
     */
    public static function get(string $key): ?array
    {
        return static::all()->get($key);
    }

    /**
     * Whether a provider key is valid.
     */
    public static function has(string $key): bool
    {
        return static::all()->has($key);
    }

    /**
     * The validation rule string for `in:` checks.
     */
    public static function validationRule(): string
    {
        return 'in:' . implode(',', static::keys());
    }

    /**
     * Display name with fallback.
     */
    public static function name(string $key): string
    {
        return static::get($key)['name']
            ?? ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * Icon class with fallback.
     */
    public static function icon(string $key): string
    {
        return static::get($key)['icon']
            ?? config('payment_providers.fallback.icon');
    }

    /**
     * Brand color with fallback.
     */
    public static function color(string $key): string
    {
        return static::get($key)['color']
            ?? config('payment_providers.fallback.color');
    }

    /**
     * Short description with fallback.
     */
    public static function description(string $key): string
    {
        return static::get($key)['description']
            ?? config('payment_providers.fallback.description');
    }

    /**
     * The SystemSetting boolean column for a provider.
     */
    public static function settingField(string $key): ?string
    {
        return static::get($key)['setting_field'] ?? null;
    }
}