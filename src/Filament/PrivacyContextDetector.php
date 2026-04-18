<?php

namespace Arseno25\FilamentPrivacyBlur\Filament;

class PrivacyContextDetector
{
    /**
     * Resolve the Filament resource class from a component, if mounted.
     */
    public static function resolveResourceClass(object $component): ?string
    {
        try {
            if (method_exists($component, 'getLivewire')) {
                $livewire = $component->getLivewire();
                if ($livewire && method_exists($livewire, 'getResource')) {
                    return $livewire::getResource();
                }
            }
        } catch (\Throwable) {
            // Component is not mounted — e.g. in a unit test
        }

        return null;
    }

    /**
     * Detect if the current request is an export context.
     * Checks route name and Filament's export header.
     */
    public static function isExportContext(): bool
    {
        $route = request()->route();
        if ($route) {
            $routeName = $route->getName() ?? '';
            if (preg_match('/\bexport\b/', $routeName)) {
                return true;
            }
        }

        if (request()->hasHeader('X-Filament-Export')) {
            return true;
        }

        return false;
    }
}
