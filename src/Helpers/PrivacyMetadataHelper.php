<?php

namespace Arseno25\FilamentPrivacyBlur\Helpers;

use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Tables\Columns\Column;
use SplObjectStorage;

/**
 * Helper class to store privacy metadata for Filament components.
 * This avoids issues with customProperties not being available on all component types.
 */
class PrivacyMetadataHelper
{
    /** @var SplObjectStorage<Column|Entry|Field, array<string, mixed>> */
    protected static SplObjectStorage $metadata;

    public static function init(): void
    {
        if (! isset(self::$metadata)) {
            self::$metadata = new SplObjectStorage;
        }
    }

    /**
     * Set metadata for a component.
     *
     * @param  Column|Entry|Field  $component
     * @param  array<string, mixed>  $data
     */
    public static function set(object $component, array $data): object
    {
        self::init();

        $existing = self::$metadata->contains($component)
            ? self::$metadata[$component]
            : [];

        self::$metadata[$component] = array_merge($existing, $data);

        return $component;
    }

    /**
     * Get metadata for a component.
     *
     * @param  Column|Entry|Field  $component
     * @return array<string, mixed>
     */
    public static function get(object $component): array
    {
        self::init();

        return self::$metadata->contains($component)
            ? self::$metadata[$component]
            : [];
    }

    /**
     * Get a specific metadata value.
     *
     * @param  Column|Entry|Field  $component
     */
    public static function getValue(object $component, string $key, mixed $default = null): mixed
    {
        return self::get($component)[$key] ?? $default;
    }
}
