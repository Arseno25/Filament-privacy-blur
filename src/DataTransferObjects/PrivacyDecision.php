<?php

namespace Arseno25\FilamentPrivacyBlur\DataTransferObjects;

use Arseno25\FilamentPrivacyBlur\Enums\PrivacyMode;

/**
 * Explicit privacy decision value object.
 * Replaces ambiguous boolean authorization with a complete decision model.
 *
 * Properties capture all aspects of how private data should be displayed,
 * whether the user can interact with it, and what audit actions are needed.
 */
final class PrivacyDecision
{
    public function __construct(
        /** User can view the raw data without any privacy effects */
        public readonly bool $canViewRaw,

        /** Data should be masked server-side (replace characters) */
        public readonly bool $shouldRenderMasked,

        /** Data should be blurred via CSS */
        public readonly bool $shouldBlur,

        /** User can reveal via click/hover interaction */
        public readonly bool $canRevealInteractively,

        /** User can reveal via global toggle button */
        public readonly bool $canBeGloballyRevealed,

        /** Reveal actions should be logged to audit */
        public readonly bool $shouldAuditReveal,

        /** Never allow reveal regardless of other permissions */
        public readonly bool $neverReveal,

        /** The effective privacy mode for this decision */
        public readonly PrivacyMode $mode,

        /** CSS blur intensity (1-10) */
        public readonly int $blurAmount,

        /** Human-readable reason for this decision */
        public readonly ?string $authorizationReason = null,
    ) {}

    /**
     * Decision when privacy is completely disabled.
     */
    public static function noPrivacy(): self
    {
        return new self(
            canViewRaw: true,
            shouldRenderMasked: false,
            shouldBlur: false,
            canRevealInteractively: false,
            canBeGloballyRevealed: false,
            shouldAuditReveal: false,
            neverReveal: false,
            mode: PrivacyMode::Disabled,
            blurAmount: 0,
            authorizationReason: 'privacy_disabled',
        );
    }

    /**
     * Decision for a fully authorized user based on the mode.
     */
    public static function fullyAuthorized(PrivacyMode $mode, int $blurAmount): self
    {
        return match ($mode) {
            PrivacyMode::Disabled => self::noPrivacy(),
            PrivacyMode::Blur, PrivacyMode::Mask => new self(
                canViewRaw: true,
                shouldRenderMasked: false,
                shouldBlur: false,
                canRevealInteractively: false,
                canBeGloballyRevealed: false,
                shouldAuditReveal: false,
                neverReveal: false,
                mode: $mode,
                blurAmount: $blurAmount,
                authorizationReason: 'authorized_plain_view',
            ),
            PrivacyMode::BlurClick, PrivacyMode::BlurHover => new self(
                canViewRaw: false,
                shouldRenderMasked: false,
                shouldBlur: true,
                canRevealInteractively: true,
                canBeGloballyRevealed: true,
                shouldAuditReveal: true,
                neverReveal: false,
                mode: $mode,
                blurAmount: $blurAmount,
                authorizationReason: 'authorized_interactive_reveal',
            ),
            PrivacyMode::BlurAuth => new self(
                canViewRaw: true,
                shouldRenderMasked: false,
                shouldBlur: false,
                canRevealInteractively: false,
                canBeGloballyRevealed: false,
                shouldAuditReveal: false,
                neverReveal: false,
                mode: $mode,
                blurAmount: $blurAmount,
                authorizationReason: 'authorized_no_reveal',
            ),
            PrivacyMode::Hybrid => new self(
                canViewRaw: false,
                shouldRenderMasked: true,
                shouldBlur: false,
                canRevealInteractively: false,
                canBeGloballyRevealed: false,
                shouldAuditReveal: false,
                neverReveal: false,
                mode: $mode,
                blurAmount: $blurAmount,
                authorizationReason: 'authorized_masked_view',
            ),
        };
    }

    /**
     * Decision for an unauthorized user.
     */
    public static function unauthorized(
        PrivacyMode $mode,
        int $blurAmount,
        bool $neverReveal = false
    ): self {
        return new self(
            canViewRaw: false,
            shouldRenderMasked: $mode === PrivacyMode::Mask || $mode === PrivacyMode::Hybrid,
            shouldBlur: $mode !== PrivacyMode::Mask,
            canRevealInteractively: false,
            canBeGloballyRevealed: false,
            shouldAuditReveal: false,
            neverReveal: $neverReveal,
            mode: $mode,
            blurAmount: $blurAmount,
            authorizationReason: 'unauthorized',
        );
    }

    /**
     * Return a new decision with neverReveal set to true.
     */
    public function withNeverReveal(): self
    {
        return new self(
            canViewRaw: false,
            shouldRenderMasked: $this->shouldRenderMasked,
            shouldBlur: $this->shouldBlur,
            canRevealInteractively: false,
            canBeGloballyRevealed: false,
            shouldAuditReveal: false,
            neverReveal: true,
            mode: $this->mode,
            blurAmount: $this->blurAmount,
            authorizationReason: 'never_reveal_override',
        );
    }

    /**
     * Return a new decision with forced blur (for hidden roles).
     */
    public function forceBlur(): self
    {
        return new self(
            canViewRaw: false,
            shouldRenderMasked: false,
            shouldBlur: true,
            canRevealInteractively: false,
            canBeGloballyRevealed: false,
            shouldAuditReveal: false,
            neverReveal: false,
            mode: PrivacyMode::Blur,
            blurAmount: $this->blurAmount,
            authorizationReason: 'forced_blur_hidden_roles',
        );
    }

    /**
     * Convert to legacy array format for backward compatibility.
     *
     * @return array{mode: PrivacyMode, should_blur: bool, should_mask: bool, reveal_enabled: bool, blur_amount: int}
     */
    public function toLegacyArray(): array
    {
        return [
            'mode' => $this->mode,
            'should_blur' => $this->shouldBlur,
            'should_mask' => $this->shouldRenderMasked,
            'reveal_enabled' => $this->canRevealInteractively,
            'blur_amount' => $this->blurAmount,
            'decision' => $this,
        ];
    }

    /**
     * Check if any privacy effect should be applied.
     */
    public function hasPrivacyEffect(): bool
    {
        return $this->shouldBlur || $this->shouldRenderMasked;
    }

    /**
     * Check if reveal is possible through any mechanism.
     */
    public function canReveal(): bool
    {
        if ($this->neverReveal) {
            return false;
        }

        return $this->canRevealInteractively || $this->canBeGloballyRevealed;
    }
}
