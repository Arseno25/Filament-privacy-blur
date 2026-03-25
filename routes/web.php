<?php

use Arseno25\FilamentPrivacyBlur\Http\Controllers\PrivacyAuditController;
use Illuminate\Support\Facades\Route;

Route::post('/filament-privacy-blur/audit', PrivacyAuditController::class)
    ->name('filament-privacy-blur.audit')
    ->middleware(['web', 'auth']);
