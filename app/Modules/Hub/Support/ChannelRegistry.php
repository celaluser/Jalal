<?php

namespace App\Modules\Hub\Support;

/** The adapters that can read incoming orders. Add-ons register more. */
final class ChannelRegistry
{
    /** @var array<string, ChannelAdapter> */
    private static array $adapters = [];

    public static function register(ChannelAdapter $adapter): void
    {
        self::$adapters[$adapter->kind()] = $adapter;
    }

    public static function get(string $kind): ChannelAdapter
    {
        return self::$adapters[$kind] ?? self::$adapters['generic'] ?? new GenericAdapter;
    }

    /** @return array<string, ChannelAdapter> */
    public static function all(): array
    {
        return self::$adapters;
    }

    public static function flush(): void
    {
        self::$adapters = [];
    }
}
