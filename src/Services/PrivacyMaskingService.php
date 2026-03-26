<?php

namespace Arseno25\FilamentPrivacyBlur\Services;

class PrivacyMaskingService
{
    /**
     * Apply masking to a string based on the active strategy.
     */
    public function mask(string $strategy, ?string $value): ?string
    {
        if (blank($value)) {
            return $value;
        }

        return match ($strategy) {
            'email' => $this->maskEmail($value),
            'phone' => $this->maskPhone($value),
            'currency' => '***',
            'full_name' => $this->maskName($value),
            'api_key' => $this->maskApiKey($value),
            'nik' => $this->maskNik($value),
            'address' => $this->maskAddress($value),
            default => $this->maskGeneric($value),
        };
    }

    protected function maskEmail(string $value): string
    {
        $parts = explode('@', $value);
        if (count($parts) !== 2) {
            return $this->maskGeneric($value);
        }

        $local = $parts[0];
        $domain = $parts[1];

        $localLength = mb_strlen($local);
        if ($localLength <= 1) {
            $maskedLocal = '*';
        } elseif ($localLength <= 2) {
            $maskedLocal = str_repeat('*', $localLength);
        } else {
            $maskedLocal = mb_substr($local, 0, 1).str_repeat('*', $localLength - 2).mb_substr($local, -1);
        }

        return $maskedLocal.'@'.$domain;
    }

    protected function maskPhone(string $value): string
    {
        $length = mb_strlen($value);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        // For medium-length phones (5-8 chars), show first 2 and mask rest
        if ($length <= 8) {
            return mb_substr($value, 0, 2).str_repeat('*', $length - 2);
        }

        // For longer phones, show first 4 and last 4
        return mb_substr($value, 0, 4).str_repeat('*', $length - 8).mb_substr($value, -4);
    }

    protected function maskName(string $value): string
    {
        $words = explode(' ', $value);
        $maskedWords = array_map(function ($word) {
            $len = mb_strlen($word);
            if ($len <= 2) {
                return $word;
            }

            return mb_substr($word, 0, 2).str_repeat('*', $len - 2);
        }, $words);

        return implode(' ', $maskedWords);
    }

    protected function maskApiKey(string $value): string
    {
        $length = mb_strlen($value);
        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return mb_substr($value, 0, 3).str_repeat('*', $length - 6).mb_substr($value, -3);
    }

    protected function maskNik(string $value): string
    {
        if (mb_strlen($value) !== 16) {
            return $this->maskGeneric($value);
        }

        return mb_substr($value, 0, 4).str_repeat('*', 8).mb_substr($value, -4);
    }

    protected function maskAddress(string $value): string
    {
        $length = mb_strlen($value);
        if ($length <= 10) {
            return str_repeat('*', $length);
        }

        return mb_substr($value, 0, 10).str_repeat('*', $length - 10);
    }

    protected function maskGeneric(string $value): string
    {
        $length = mb_strlen($value);
        if ($length <= 1) {
            return '*';
        }
        if ($length <= 3) {
            return str_repeat('*', $length);
        }

        return mb_substr($value, 0, 1).str_repeat('*', $length - 2).mb_substr($value, -1);
    }
}
