<?php

namespace Arseno25\FilamentPrivacyBlur\Http\Controllers;

use Arseno25\FilamentPrivacyBlur\Resolvers\PrivacyConfigResolver;
use Arseno25\FilamentPrivacyBlur\Services\PrivacyAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PrivacyAuditController extends Controller
{
    public function __invoke(Request $request)
    {
        if (! PrivacyConfigResolver::isAuditEnabled()) {
            return response()->json(['status' => 'skipped']);
        }

        $entries = $request->has('batch')
            ? $request->validate([
                'batch' => 'required|array|max:100',
                'batch.*.column' => 'required|string',
                'batch.*.record_id' => 'nullable|string',
                'batch.*.mode' => 'required|string',
                'batch.*.resource' => 'nullable|string',
                'batch.*.panel' => 'nullable|string',
                'batch.*.tenant_id' => 'nullable|string',
            ])['batch']
            : [$request->validate([
                'column' => 'required|string',
                'record_id' => 'nullable|string',
                'mode' => 'required|string',
                'resource' => 'nullable|string',
                'panel' => 'nullable|string',
                'tenant_id' => 'nullable|string',
            ])];

        $page = url()->previous();
        $ip = $request->ip();
        $ua = $request->userAgent();

        foreach ($entries as $entry) {
            PrivacyAuditLogger::logReveal(
                columnName: $entry['column'],
                revealMode: $entry['mode'],
                recordKey: $entry['record_id'] ?? null,
                resource: $entry['resource'] ?? null,
                page: $page,
                panel: $entry['panel'] ?? null,
                ipAddress: $ip,
                userAgent: $ua
            );
        }

        return response()->json(['status' => 'success', 'count' => count($entries)]);
    }
}
