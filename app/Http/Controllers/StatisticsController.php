<?php

namespace App\Http\Controllers;

use App\Services\Scheduler\Audit;
use App\Services\Scheduler\ScheduleStatistics;
use Illuminate\Http\Request;

class StatisticsController extends Controller
{
    public function index(Request $r, ScheduleStatistics $statistics)
    {
        $report = $statistics->report($r);
        $response = $r->is('api/*') ? response()->json($report) : response()->view('statistics.index', $report);
        app(Audit::class)->record('report.viewed', ['report' => 'exam_statistics', 'format' => $r->is('api/*') ? 'json' : 'web', 'filters' => $report['filters'], 'as_of' => $report['as_of'], 'summary' => $report['summary'], 'locale' => app()->getLocale()]);

        return $response;
    }
}
