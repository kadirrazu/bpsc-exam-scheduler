<?php

namespace App\Http\Controllers;

use App\Services\Scheduler\Audit;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class PrintAuditController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['report_token' => 'required|string|max:16000']);
        try {
            $report = json_decode(Crypt::decryptString($data['report_token']), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $e) {
            abort(422, __('Invalid report reference.'));
        }
        abort_unless(($report['actor_id'] ?? null) === $request->user()->id && ($report['expires_at'] ?? 0) >= now()->timestamp, 422, __('This report has expired. Reload it before printing.'));
        app(Audit::class)->record('report.print_requested', array_intersect_key($report, array_flip(['report', 'format', 'filters', 'row_count', 'locale', 'generated_at'])));

        return response()->json(['message' => __('Print request recorded.')]);
    }
}
