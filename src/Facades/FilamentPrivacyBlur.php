<?php

namespace Arseno25\FilamentPrivacyBlur\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Arseno25\FilamentPrivacyBlur\FilamentPrivacyBlur
 */
class FilamentPrivacyBlur extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Arseno25\FilamentPrivacyBlur\FilamentPrivacyBlur::class;
    }
}
