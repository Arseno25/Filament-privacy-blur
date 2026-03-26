<?php

namespace Arseno25\FilamentPrivacyBlur\DataTransferObjects;

/**
 * Value object representing the result of an authorization check.
 * Captures both the boolean result and the context of how that decision was made.
 */
final class AuthorizationResult
{
    public function __construct(
        public readonly bool $authorized,
        public readonly string $method,
        public readonly string $reason
    ) {}

    /**
     * Create a successful authorization result.
     */
    public static function authorized(string $method = 'gate', string $reason = 'authorized'): self
    {
        return new self(
            authorized: true,
            method: $method,
            reason: $reason,
        );
    }

    /**
     * Create a denied authorization result.
     */
    public static function denied(string $method = 'gate', string $reason = 'denied'): self
    {
        return new self(
            authorized: false,
            method: $method,
            reason: $reason,
        );
    }

    /**
     * Create a result for when no authenticated user exists.
     */
    public static function noUser(): self
    {
        return new self(
            authorized: false,
            method: 'none',
            reason: 'no_authenticated_user',
        );
    }
}
