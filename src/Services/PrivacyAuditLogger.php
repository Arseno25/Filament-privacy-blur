<?php

namespace Arseno25\FilamentPrivacyBlur\Services;

use Arseno25\FilamentPrivacyBlur\Models\PrivacyRevealLog;
use Arseno25\FilamentPrivacyBlur\Resolvers\PrivacyConfigResolver;
use Filament\Facades\Filament;

class PrivacyAuditLogger
{
    /**
     * Log a reveal interaction if auditing is enabled.
     */
    public static function logReveal(
        string $columnName,
        string $revealMode,
        ?string $recordKey = null,
        ?string $resource = null,
        ?string $page = null
    ): void {
        if (! PrivacyConfigResolver::isAuditEnabled()) {
            return;
        }

        $panel = Filament::getCurrentPanel();
        $tenant = Filament::getTenant();
        $user = auth()->user();

        PrivacyRevealLog::create([
            'user_id' => $user ? $user->getAuthIdentifier() : null,
            'tenant_id' => $tenant ? $tenant->getKey() : null,
            'panel_id' => $panel ? $panel->getId() : null,
            'resource' => $resource,
            'page' => $page,
            'column_name' => $columnName,
            'record_key' => $recordKey,
            'reveal_mode' => $revealMode,
        ]);
    }
}
