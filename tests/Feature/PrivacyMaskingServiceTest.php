<?php

use Arseno25\FilamentPrivacyBlur\Services\PrivacyMaskingService;

it('masks emails correctly', function () {
    $service = new PrivacyMaskingService;
    expect($service->mask('email', 'john.doe@example.com'))->toBe('j******e@example.com');
});

it('masks phones correctly', function () {
    $service = new PrivacyMaskingService;
    expect($service->mask('phone', '081234567890'))->toBe('0812****7890');
});

it('masks nik correctly', function () {
    $service = new PrivacyMaskingService;
    expect($service->mask('nik', '3173123456789012'))->toBe('3173********9012');
});

it('masks names correctly', function () {
    $service = new PrivacyMaskingService;
    expect($service->mask('full_name', 'John Doe'))->toBe('Jo** Do*');
});

it('returns null if value is null', function () {
    $service = new PrivacyMaskingService;
    expect($service->mask('email', null))->toBeNull();
});
