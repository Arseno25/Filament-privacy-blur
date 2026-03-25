<?php

use Arseno25\FilamentPrivacyBlur\Services\PrivacyMaskingService;

it('masks emails correctly', function () {
    $service = new PrivacyMaskingService;
    expect($service->mask('email', 'john.doe@example.com'))->toBe('j******e@example.com');
});

it('masks emails with short local part (2 chars)', function () {
    $service = new PrivacyMaskingService;
    expect($service->mask('email', 'ab@example.com'))->toBe('**@example.com');
});

it('masks emails with single char local part', function () {
    $service = new PrivacyMaskingService;
    expect($service->mask('email', 'a@example.com'))->toBe('*@example.com');
});

it('masks phones correctly (12 chars)', function () {
    $service = new PrivacyMaskingService;
    expect($service->mask('phone', '081234567890'))->toBe('0812****7890');
});

it('masks phones with 5 chars (no crash)', function () {
    $service = new PrivacyMaskingService;
    $result = $service->mask('phone', '12345');
    expect($result)->toBe('12***');
});

it('masks phones with 7 chars (edge case)', function () {
    $service = new PrivacyMaskingService;
    $result = $service->mask('phone', '1234567');
    expect($result)->toBe('12*****');
});

it('masks phones with 4 chars (all masked)', function () {
    $service = new PrivacyMaskingService;
    expect($service->mask('phone', '1234'))->toBe('****');
});

it('masks phones with exactly 8 chars', function () {
    $service = new PrivacyMaskingService;
    $result = $service->mask('phone', '12345678');
    expect($result)->toBe('12******');
});

it('masks phones with 9 chars', function () {
    $service = new PrivacyMaskingService;
    $result = $service->mask('phone', '123456789');
    expect($result)->toBe('1234*6789');
});

it('masks nik correctly', function () {
    $service = new PrivacyMaskingService;
    expect($service->mask('nik', '3173123456789012'))->toBe('3173********9012');
});

it('masks names correctly', function () {
    $service = new PrivacyMaskingService;
    expect($service->mask('full_name', 'John Doe'))->toBe('Jo** Do*');
});

it('masks generic with single char', function () {
    $service = new PrivacyMaskingService;
    expect($service->mask('generic', 'A'))->toBe('*');
});

it('masks generic with 2 chars', function () {
    $service = new PrivacyMaskingService;
    expect($service->mask('generic', 'AB'))->toBe('**');
});

it('returns null if value is null', function () {
    $service = new PrivacyMaskingService;
    expect($service->mask('email', null))->toBeNull();
});

it('returns empty string if value is empty', function () {
    $service = new PrivacyMaskingService;
    expect($service->mask('email', ''))->toBe('');
});
