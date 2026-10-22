<?php

use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Model;

it('masks a private export column by default without exposing the original', function () {
    $column = ExportColumn::make('secret')->private();

    expect($column->formatState('super-secret-token'))
        ->toBe('s****************n')
        ->not->toBe('super-secret-token');
});

it('supports a custom mask strategy after private', function () {
    $column = ExportColumn::make('email')->private()->maskUsing('email');

    expect($column->formatState('johndoe@example.com'))->toBe('j*****e@example.com');
});

it('supports a custom mask strategy before private', function () {
    $column = ExportColumn::make('email')->maskUsing('email')->private();

    expect($column->formatState('johndoe@example.com'))->toBe('j*****e@example.com');
});

it('does not implicitly enable privacy when only maskUsing is called', function () {
    $column = ExportColumn::make('email')->maskUsing('email');

    expect($column->formatState('johndoe@example.com'))->toBe('johndoe@example.com');
});

it('respects private false without applying any masking', function () {
    $column = ExportColumn::make('email')->private(false)->maskUsing('email');

    expect($column->formatState('johndoe@example.com'))->toBe('johndoe@example.com');
});

it('keeps masking strategies isolated between export columns', function () {
    $email = ExportColumn::make('email')->private()->maskUsing('email');
    $phone = ExportColumn::make('phone')->private()->maskUsing('phone');

    expect($email->formatState('johndoe@example.com'))->toBe('j*****e@example.com')
        ->and($phone->formatState('081234567890'))->toBe('0812****7890');
});

it('provides state and Eloquent record to custom export mask callbacks', function () {
    $record = new class extends Model {};

    $column = ExportColumn::make('secret')
        ->private()
        ->maskUsing(fn (string $state, ?Model $record = null) => $record ? 'masked-record' : 'masked');

    expect($column->formatState('sensitive'))->toBe('masked');

    // ExportColumn obtains the record from its Exporter during real export;
    // direct formatState() calls have no bound exporter/record.
});

it('enables Filament formula-injection protection for private export columns', function () {
    $column = ExportColumn::make('email')->private()->maskUsing('email');

    expect($column->shouldPreventFormulaInjection())->toBeTrue();
});

it('does not render HTML or CSS blur markup inside export data', function () {
    $column = ExportColumn::make('email')->private()->maskUsing('email');

    $result = $column->formatState('johndoe@example.com');

    expect($result)->toBeString()
        ->not->toContain('<span')
        ->not->toContain('fi-privacy-blur');
});
