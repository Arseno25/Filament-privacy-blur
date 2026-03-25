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

        $validated = $request->validate([
            'column' => 'required|string',
            'record_id' => 'nullable|string',
            'mode' => 'required|string',
            'resource' => 'nullable|string',
            'panel' => 'nullable|string',
        ]);

        PrivacyAuditLogger::logReveal(
            columnName: $validated['column'],
            revealMode: $validated['mode'],
            recordKey: $validated['record_id'] ?? null,
            resource: $validated['resource'] ?? null,
            page: url()->previous()
        );

        return response()->json(['status' => 'success']);
    }
}
