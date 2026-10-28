<?php

namespace App\Services\Scheduler;

use App\Models\ExamSchedule;
use App\Support\Ui;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ScheduleStatistics
{
    public function report(Request $request): array
    {
        foreach (['year', 'month'] as $field) {
            if ($request->exists($field)) {
                $request->merge([$field => Ui::ascii($request->input($field))]);
            }
        }
        $input = $request->validate([
            'period' => ['nullable', Rule::in(['month', 'year', 'range'])],
            'year' => 'nullable|integer|min:1900|max:9998', 'month' => 'nullable|integer|min:1|max:12',
            'from' => 'required_if:period,range|nullable|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:9998-12-31',
            'to' => 'required_if:period,range|nullable|date_format:Y-m-d|after_or_equal:from|before_or_equal:9998-12-31',
            'unit' => ['nullable', Rule::in(app(Catalog::class)->units())], 'through_today' => 'nullable|boolean',
        ]);
        $today = CarbonImmutable::today('Asia/Dhaka');
        $period = $input['period'] ?? 'month';
        $year = (int) ($input['year'] ?? $today->year);
        $month = $period === 'month' ? (int) ($input['month'] ?? $today->month) : null;
        if ($period === 'range') {
            $start = CarbonImmutable::parse($input['from'], 'Asia/Dhaka');
            $end = CarbonImmutable::parse($input['to'], 'Asia/Dhaka');
        } else {
            $start = CarbonImmutable::create($year, $month ?? 1, 1, 0, 0, 0, 'Asia/Dhaka');
            $end = $period === 'month' ? $start->endOfMonth() : $start->endOfYear();
        }
        $filters = ['period' => $period, 'year' => $period === 'range' ? null : $year, 'month' => $month, 'unit' => $input['unit'] ?? null, 'from' => $start->toDateString(), 'to' => $end->toDateString(), 'through_today' => array_key_exists('through_today', $input) ? (bool) $input['through_today'] : true];
        $query = ExamSchedule::whereBetween('exam_date', [$filters['from'], $filters['to']])
            ->when($filters['through_today'], fn ($q) => $q->where('exam_date', '<=', $today->toDateString()))
            ->when($filters['unit'], fn ($q, $unit) => $q->where('unit', $unit));
        $counts = (clone $query)->select('exam_type', 'status', DB::raw('COUNT(*) AS exam_total'))->groupBy('exam_type', 'status')->get();
        $configured = config('scheduler.statuses');
        $statuses = [];
        foreach (['proposed', 'scheduled', 'completed', 'postponed', 'cancelled'] as $code) {
            if (isset($configured[$code])) {
                $statuses[$code] = $configured[$code];
            }
        }
        foreach ($configured as $code => $label) {
            $statuses[$code] ??= $label;
        }
        $types = app(Catalog::class)->types();
        foreach ($counts as $row) {
            $types[$row->exam_type] ??= ['label' => $row->exam_type];
            $statuses[$row->status] ??= $row->status;
        }
        $totals = array_fill_keys(array_keys($statuses), 0);
        $rows = [];
        foreach ($types as $code => $type) {
            $rows[$code] = ['code' => $code, 'label' => $type['label'], 'exams' => 0, 'statuses' => array_fill_keys(array_keys($statuses), 0)];
        }
        foreach ($counts as $row) {
            $count = (int) $row->exam_total;
            $rows[$row->exam_type]['exams'] += $count;
            $rows[$row->exam_type]['statuses'][$row->status] += $count;
            $totals[$row->status] += $count;
        }

        return ['filters' => $filters, 'as_of' => $today->toDateString(), 'summary' => ['exams' => array_sum($totals), 'units' => (clone $query)->distinct()->count('unit'), 'statuses' => $totals],
            'statuses' => collect($statuses)->map(fn ($label, $code) => ['code' => $code, 'label' => $label])->values()->all(), 'types' => array_values($rows)];
    }
}
