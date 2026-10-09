<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleRequest;
use App\Models\ExamSchedule;
use App\Services\Scheduler\Audit;
use App\Services\Scheduler\ScheduleExport;
use App\Services\Scheduler\ScheduleQuery;
use App\Services\Scheduler\ScheduleWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ScheduleController extends Controller
{
    public function index(Request $r, ScheduleQuery $q)
    {
        $filters = $q->filters($r);
        $isDashboard = $r->routeIs('dashboard');
        $query = $q->query($filters);
        $summary = ['exams' => (clone $query)->count(), 'units' => (clone $query)->distinct()->count('unit'), 'exam_types' => (clone $query)->distinct()->count('exam_type'), 'grades' => (clone $query)->distinct()->count('post_grade')];
        if ($filters['display'] === 'grouped') {
            $direction = $filters['scope'] === 'all' ? 'desc' : 'asc';
            $datePage = max(1, (int) $r->input('page', 1));
            $totalDates = (clone $query)->reorder()->distinct()->count('exam_date');
            $dateItems = (clone $query)->reorder()->select('exam_date')->distinct()
                ->orderBy('exam_date', $direction)->forPage($datePage, 25)->get();
            $pagination = (new \Illuminate\Pagination\LengthAwarePaginator($dateItems, $totalDates, 25, $datePage, ['path' => $r->url()]))->withQueryString();
            $dates = $pagination->getCollection()->pluck('exam_date')->map(fn ($date) => $date->format('Y-m-d'));
            $schedules = (clone $query)->whereIn('exam_date', $dates)->get();
            $rowOffset = $dates->isEmpty() ? 0 : (clone $query)->where('exam_date', $direction === 'desc' ? '>' : '<', $dates->first())->count();
            $pagination->setCollection($schedules->groupBy(fn ($s) => $s->exam_date->format('Y-m-d'))
                ->map(fn ($rows, $date) => ['exam_date' => $date, 'exams' => $rows->values()])->values());
        } else {
            $schedules = $query->paginate(25)->withQueryString();
            $pagination = $schedules;
            $rowOffset = max(0, ($schedules->firstItem() ?? 1) - 1);
        }
        $dateSpans = collect($filters['display'] === 'flat' ? $schedules->items() : $schedules)->groupBy(fn ($s) => $s->exam_date->format('Y-m-d'))->map->count();
        $response = $r->is('api/*') ? response()->json(['filters' => $filters, 'summary' => $summary, 'schedules' => $pagination]) : response()->view('schedules.index', compact('filters', 'summary', 'schedules', 'isDashboard', 'pagination', 'rowOffset', 'dateSpans'));
        app(Audit::class)->record('report.viewed', ['report' => 'exam_schedule_list', 'format' => $r->is('api/*') ? 'json' : 'web', 'filters' => $filters, 'row_count' => $schedules->count(), 'total_rows' => $summary['exams'], 'page' => $pagination->currentPage(), 'pagination_unit' => $filters['display'] === 'grouped' ? 'dates' : 'exams', 'locale' => app()->getLocale()]);

        return $response;
    }

    public function create()
    {
        Gate::authorize('edit-schedules');

        return view('schedules.form', ['schedule' => new ExamSchedule(['status' => 'scheduled', 'exam_date' => today()])]);
    }

    public function store(ScheduleRequest $r, ScheduleWriter $w)
    {
        $s = $w->create($r->validated(), $r->user());

        return $r->is('api/*') ? response()->json(['data' => $s], 201) : redirect()->route('schedules.show', $s)->with('success', __('Schedule created.'));
    }

    public function show(Request $r, ExamSchedule $schedule)
    {
        $response = $r->is('api/*') ? response()->json(['data' => $schedule]) : response()->view('schedules.show', compact('schedule'));
        app(Audit::class)->record('report.viewed', ['report' => 'exam_schedule_details', 'format' => $r->is('api/*') ? 'json' : 'web', 'locale' => app()->getLocale()], $schedule);

        return $response;
    }

    public function edit(ExamSchedule $schedule)
    {
        Gate::authorize('edit-schedules');

        return view('schedules.form', compact('schedule'));
    }

    public function update(ScheduleRequest $r, ExamSchedule $schedule, ScheduleWriter $w)
    {
        $s = $w->update($schedule, $r->validated(), $r->user());

        return $r->is('api/*') ? response()->json(['data' => $s]) : redirect()->route('schedules.show', $s)->with('success', __('Schedule updated.'));
    }

    public function destroy(Request $r, ExamSchedule $schedule, ScheduleWriter $w)
    {
        Gate::authorize('manage-system');
        $d = $r->validate(['confirmation' => 'required|in:DELETE', 'version' => 'required|integer|min:1']);
        $w->delete($schedule, (int) $d['version'], $r->user());

        return $r->is('api/*') ? response()->noContent() : redirect()->route('schedules.index')->with('success', __('Schedule deleted. Audit history preserved.'));
    }

    public function export(Request $r, string $format, ScheduleQuery $q, ScheduleExport $export)
    {
        abort_unless(in_array($format, ['xlsx', 'pdf', 'print'], true), 404);
        $filters = $q->filters($r);
        $schedules = $q->exportRows($filters);

        return $export->response($format, $schedules, $filters);
    }
}
