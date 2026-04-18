<?php

namespace Arseno25\FilamentPrivacyBlur\Filament\Concerns;

use Filament\Infolists\Components\Entry;

trait HandlesEntryMasking
{
    use AppliesBlurFormatting;

    /**
     * Wire up privacy behavior on an infolist Entry.
     */
    public static function applyToEntry(Entry $entry): void
    {
        self::attachBlurFormatter($entry);
    }
}
