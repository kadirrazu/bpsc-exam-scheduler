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
        $query = $q->query($filters);
        $summary = ['exams' => (clone $query)->count(), 'candidates' => (clone $query)->sum('candidate_count'), 'candidates_unspecified' => (clone $query)->whereNull('candidate_count')->count(), 'centers' => (clone $query)->sum('center_count'), 'boards' => (clone $query)->sum('board_count')];
        $schedules = $query->paginate(25)->withQueryString();
        $response = $r->is('api/*') ? response()->json(['filters' => $filters, 'summary' => $summary, 'schedules' => $schedules]) : response()->view('schedules.index', compact('filters', 'summary', 'schedules'));
        app(Audit::class)->record('report.viewed', ['report' => 'exam_schedule_list', 'format' => $r->is('api/*') ? 'json' : 'web', 'filters' => $filters, 'row_count' => $schedules->count(), 'total_rows' => $schedules->total(), 'page' => $schedules->currentPage(), 'locale' => app()->getLocale()]);

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
