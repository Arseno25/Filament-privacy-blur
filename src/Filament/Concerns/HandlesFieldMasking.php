<?php

namespace Arseno25\FilamentPrivacyBlur\Filament\Concerns;

use Arseno25\FilamentPrivacyBlur\Filament\ColumnPrivacyMacros;
use Arseno25\FilamentPrivacyBlur\Helpers\PrivacyMetadataHelper;
use Filament\Forms\Components\Field;

trait HandlesFieldMasking
{
    /**
     * Wire up privacy behavior on a form Field.
     *
     * Uses extraInputAttributes when available (TextInput, Textarea, etc.)
     * so the blur class lands on the actual input element rather than
     * the wrapping container.
     */
    public static function applyToField(Field $field): void
    {
        $callback = function () use ($field) {
            $meta = PrivacyMetadataHelper::get($field);
            $decision = ColumnPrivacyMacros::resolveDecisionForField($field, $field->getRecord(), $meta);

            if (! $decision->hasPrivacyEffect()) {
                return [];
            }

            return [
                'class' => "fi-privacy-blur fi-pb-{$decision->blurAmount}",
                'data-privacy-input' => 'true',
            ];
        };

        if (method_exists($field, 'extraInputAttributes')) {
            $field->extraInputAttributes($callback);
        } else {
            $field->extraAttributes($callback);
        }
    }
}
