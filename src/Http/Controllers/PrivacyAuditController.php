<?php

namespace Arseno25\FilamentPrivacyBlur\Http\Controllers;

use Arseno25\FilamentPrivacyBlur\Services\PrivacyAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PrivacyAuditController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'column' => 'required|string',
            'record_id' => 'nullable|string',
            'mode' => 'nullable|string',
        ]);

        PrivacyAuditLogger::logReveal(
            columnName: $validated['column'],
            revealMode: $validated['mode'] ?? 'blur_click',
            recordKey: $validated['record_id'] ?? null,
            resource: null, // Hard to dynamically resolve from generic macro without extra wiring
            page: url()->previous() // Log the URL they clicked from
        );

        return response()->json(['status' => 'success']);
    }
}
