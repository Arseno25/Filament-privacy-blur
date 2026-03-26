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
        ?string $page = null,
        ?string $panel = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        if (! PrivacyConfigResolver::isAuditEnabled()) {
            return;
        }

        $panelId = $panel ?? (Filament::getCurrentPanel() ? Filament::getCurrentPanel()->getId() : null);
        $tenant = Filament::getTenant();
        $user = auth()->user();

        PrivacyRevealLog::create([
            'user_id' => $user ? $user->getAuthIdentifier() : null,
            'tenant_id' => $tenant ? $tenant->getKey() : null,
            'panel_id' => $panelId,
            'resource' => $resource,
            'page' => $page,
            'column_name' => $columnName,
            'record_key' => $recordKey,
            'reveal_mode' => $revealMode,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
