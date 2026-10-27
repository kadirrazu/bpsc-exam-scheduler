<?php

namespace App\Http\Controllers;

use App\Services\Scheduler\Audit;
use App\Services\Scheduler\Catalog;
use App\Services\Scheduler\ScheduleQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CalendarController extends Controller
{
    public function index(Request $r, ScheduleQuery $query, Catalog $catalog)
    {
        $input = $r->validate(['month' => 'nullable|date_format:Y-m', 'date' => 'nullable|date_format:Y-m-d']);
        $month = CarbonImmutable::createFromFormat('!Y-m', $input['month'] ?? CarbonImmutable::today('Asia/Dhaka')->format('Y-m'), 'Asia/Dhaka');
        // Navigation stays within HTML date inputs and Gregorian four-digit years.
        abort_if($month->year < 1900 || $month->year > 9998, 422, __('Select a valid month.'));
        $selectedDate = $input['date'] ?? null;
        if ($selectedDate && substr($selectedDate, 0, 7) !== $month->format('Y-m')) {
            throw ValidationException::withMessages(['date' => __('Select a date within the displayed month.')]);
        }
        $filters = $query->filters($r);
        $monthFilters = array_replace($filters, ['date' => null, 'from' => $month->toDateString(), 'to' => $month->endOfMonth()->toDateString(), 'scope' => 'week']);
        $totals = $query->query($monthFilters)->reorder()->select('exam_date', 'exam_type', DB::raw('COUNT(*) AS exam_count'))->groupBy('exam_date', 'exam_type')->orderBy('exam_date')->orderBy('exam_type')->get();
        $days = [];
        $monthTotal = 0;
        foreach ($totals as $row) {
            $key = $row->exam_date->format('Y-m-d');
            $count = (int) $row->exam_count;
            $days[$key] ??= ['total' => 0, 'types' => []];
            $days[$key]['total'] += $count;
            $monthTotal += $count;
            $days[$key]['types'][] = ['code' => $row->exam_type, 'label' => $catalog->typeLabel($row->exam_type), 'count' => $count];
        }
        $schedules = null;
        $pagination = null;
        $rowOffset = 0;
        $dateSpans = collect();
        if ($selectedDate) {
            $filters = array_replace($filters, ['date' => $selectedDate, 'from' => $selectedDate, 'to' => $selectedDate, 'display' => 'flat', 'scope' => 'week']);
            $schedules = $query->query($filters)->paginate(25)->withQueryString();
            $pagination = $schedules;
            $rowOffset = max(0, ($schedules->firstItem() ?? 1) - 1);
            $dateSpans = collect($schedules->items())->groupBy(fn ($s) => $s->exam_date->format('Y-m-d'))->map->count();
        }
        // Only known filters are propagated into links; date/page reset when changing month.
        $navigation = collect($filters)->only(['exam_type', 'unit', 'status', 'search'])->filter(fn ($v) => $v !== null && $v !== '')->all();
        $gridStart = $month->startOfWeek(0);
        $gridEnd = $month->endOfMonth()->endOfWeek(6);
        $cells = [];
        for ($day = $gridStart; $day <= $gridEnd; $day = $day->addDay()) {
            $key = $day->toDateString();
            $stats = $days[$key] ?? ['total' => 0, 'types' => []];
            $cells[] = ['date' => $day, 'in_month' => $day->format('Y-m') === $month->format('Y-m'), 'stats' => $stats];
        }
        app(Audit::class)->record('report.viewed', ['report' => 'exam_calendar', 'format' => $r->is('api/*') ? 'json' : 'web', 'month' => $month->format('Y-m'), 'date' => $selectedDate, 'filters' => $navigation, 'month_total' => $monthTotal, 'row_count' => $schedules?->count() ?? 0, 'page' => $schedules?->currentPage(), 'locale' => app()->getLocale()]);
        if ($r->is('api/*')) {
            return response()->json(['month' => $month->format('Y-m'), 'filters' => $navigation, 'total' => $monthTotal, 'days' => (object) $days, 'selected_date' => $selectedDate, 'schedules' => $schedules]);
        }

        return view('calendar.index', compact('month', 'selectedDate', 'days', 'monthTotal', 'filters', 'schedules', 'pagination', 'rowOffset', 'dateSpans', 'navigation', 'cells'));
    }
}
