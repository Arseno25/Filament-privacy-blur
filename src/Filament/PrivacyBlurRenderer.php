<?php

namespace Arseno25\FilamentPrivacyBlur\Filament;

use Arseno25\FilamentPrivacyBlur\DataTransferObjects\PrivacyDecision;
use Arseno25\FilamentPrivacyBlur\Enums\PrivacyMode;
use Arseno25\FilamentPrivacyBlur\Resolvers\PrivacyConfigResolver;
use Arseno25\FilamentPrivacyBlur\Services\PrivacyMaskingService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class PrivacyBlurRenderer
{
    private const SHARED_CLASSES = 'fi-text-transparent transition-all duration-300 select-none';

    /**
     * Render a blur-wrapped span for the given decision and state.
     */
    public static function renderBlurSpan(PrivacyDecision $decision, mixed $state): HtmlString
    {
        $blurClass = "fi-privacy-blur fi-pb-{$decision->blurAmount}";
        $extraClass = self::resolveExtraClass($decision);
        $classes = trim("{$blurClass} {$extraClass} " . self::SHARED_CLASSES);

        return new HtmlString(
            "<span class=\"{$classes}\">" . e((string) $state) . '</span>'
        );
    }

    /**
     * Apply a masking strategy to the given state.
     */
    public static function applyMasking(mixed $state, ?Model $record, mixed $maskStrategy): mixed
    {
        if ($maskStrategy instanceof Closure) {
            return app()->call($maskStrategy, ['state' => (string) $state, 'record' => $record]);
        }

        $strategyStr = PrivacyConfigResolver::resolveMaskStrategy(
            is_string($maskStrategy) ? $maskStrategy : null
        );

        return app(PrivacyMaskingService::class)->mask($strategyStr, (string) $state);
    }

    private static function resolveExtraClass(PrivacyDecision $decision): string
    {
        if (! $decision->canRevealInteractively) {
            return '';
        }

        return match ($decision->mode) {
            PrivacyMode::BlurClick => 'fi-cursor-pointer',
            PrivacyMode::BlurHover => 'fi-hover',
            default => '',
        };
    }
}
