<?php

namespace Arseno25\FilamentPrivacyBlur\Filament\Concerns;

use Arseno25\FilamentPrivacyBlur\Filament\ColumnPrivacyMacros;
use Arseno25\FilamentPrivacyBlur\Filament\PrivacyBlurRenderer;
use Arseno25\FilamentPrivacyBlur\Filament\PrivacyContextDetector;
use Arseno25\FilamentPrivacyBlur\Helpers\PrivacyMetadataHelper;
use Filament\Infolists\Components\Entry;
use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared blur-formatting behavior for Columns and Entries.
 *
 * Both components expose the same `extraAttributes` / `formatStateUsing`
 * surface, so the wiring is identical — only the target type differs.
 * The dedicated trait methods below (applyToColumn / applyToEntry) keep
 * semantic clarity at the call site while reusing the formatter pipeline.
 */
trait AppliesBlurFormatting
{
    protected static function attachBlurFormatter(Column | Entry $component): void
    {
        $component->extraAttributes(function (?Model $record = null) use ($component) {
            $columnName = $component->getName();
            $meta = PrivacyMetadataHelper::get($component);
            $decision = ColumnPrivacyMacros::resolveDecisionForField($component, $record, $meta);

            PrivacyMetadataHelper::set($component, ['_last_decision' => $decision->toLegacyArray()]);

            if (! $decision->hasPrivacyEffect()) {
                return [];
            }

            return ColumnPrivacyMacros::buildPrivacyAttributes($decision, $columnName, $record, $meta, $component);
        });

        if (! method_exists($component, 'formatStateUsing')) {
            return;
        }

        $component->formatStateUsing(function ($state, ?Model $record = null) use ($component) {
            $meta = PrivacyMetadataHelper::get($component);
            $decision = ColumnPrivacyMacros::resolveDecisionForField($component, $record, $meta);

            if ($decision->shouldRenderMasked) {
                return PrivacyBlurRenderer::applyMasking($state, $record, $meta['mask_strategy'] ?? null);
            }

            // Exports can't render blur — fall back to masking
            if ($decision->shouldBlur && PrivacyContextDetector::isExportContext()) {
                return PrivacyBlurRenderer::applyMasking($state, $record, $meta['mask_strategy'] ?? null);
            }

            if ($decision->shouldBlur) {
                return PrivacyBlurRenderer::renderBlurSpan($decision, $state);
            }

            return $state;
        });
    }
}
