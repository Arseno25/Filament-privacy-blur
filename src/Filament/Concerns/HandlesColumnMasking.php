<?php

namespace Arseno25\FilamentPrivacyBlur\Filament\Concerns;

use Filament\Tables\Columns\Column;

trait HandlesColumnMasking
{
    use AppliesBlurFormatting;

    /**
     * Wire up privacy behavior on a table Column.
     */
    public static function applyToColumn(Column $column): void
    {
        self::attachBlurFormatter($column);
    }
}
