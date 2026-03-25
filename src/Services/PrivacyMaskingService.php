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

        $localLength = strlen($local);
        if ($localLength <= 2) {
            $maskedLocal = str_repeat('*', $localLength);
        } else {
            $maskedLocal = substr($local, 0, 1) . str_repeat('*', $localLength - 2) . substr($local, -1);
        }

        return $maskedLocal . '@' . $domain;
    }

    protected function maskPhone(string $value): string
    {
        $length = strlen($value);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 4) . str_repeat('*', $length - 8) . substr($value, -4);
    }

    protected function maskName(string $value): string
    {
        $words = explode(' ', $value);
        $maskedWords = array_map(function ($word) {
            if (strlen($word) <= 2) {
                return $word;
            }

            return substr($word, 0, 2) . str_repeat('*', strlen($word) - 2);
        }, $words);

        return implode(' ', $maskedWords);
    }

    protected function maskApiKey(string $value): string
    {
        if (strlen($value) <= 7) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 3) . str_repeat('*', strlen($value) - 6) . substr($value, -3);
    }

    protected function maskNik(string $value): string
    {
        if (strlen($value) !== 16) {
            return $this->maskGeneric($value);
        }

        return substr($value, 0, 4) . str_repeat('*', 8) . substr($value, -4);
    }

    protected function maskAddress(string $value): string
    {
        if (strlen($value) <= 10) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 10) . str_repeat('*', strlen($value) - 10);
    }

    protected function maskGeneric(string $value): string
    {
        $length = strlen($value);
        if ($length <= 3) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 1) . str_repeat('*', $length - 2) . substr($value, -1);
    }
}
