<?php

namespace App\Services\Scheduler;

use App\Models\ExamSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class ScheduleQuery
{
    public function filters(Request $request): array
    {
        $d = $request->validate([
            'date' => 'nullable|date_format:Y-m-d', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'exam_type' => ['nullable', Rule::in(array_keys(config('scheduler.types')))], 'unit' => ['nullable', Rule::in(config('scheduler.units'))],
            'status' => ['nullable', Rule::in(array_keys(config('scheduler.statuses')))], 'search' => 'nullable|string|max:200',
            'page' => 'nullable|integer|min:1',
        ]);
        $today = CarbonImmutable::today('Asia/Dhaka');
        if (! empty($d['date'])) {
            $d['from'] = $d['to'] = $d['date'];
        } elseif (! empty($d['from']) || ! empty($d['to'])) {
            $d['from'] = $d['from'] ?? $d['to'];
            $d['to'] = $d['to'] ?? $d['from'];
        } else {
            $d['from'] = $today->toDateString();
            $d['to'] = $today->addDays(6)->toDateString();
        }
        abort_if(CarbonImmutable::parse($d['from'])->diffInDays(CarbonImmutable::parse($d['to'])) > 366, 422, __('Choose a range of at most 366 days.'));

        return $d;
    }

    public function query(array $f): Builder
    {
        return ExamSchedule::whereBetween('exam_date', [$f['from'], $f['to']])
            ->when($f['exam_type'] ?? null, fn ($q, $v) => $q->where('exam_type', $v))
            ->when($f['unit'] ?? null, fn ($q, $v) => $q->where('unit', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['search'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('title', 'like', '%'.$v.'%')->orWhere('post_name', 'like', '%'.$v.'%')->orWhere('ministry', 'like', '%'.$v.'%')->orWhere('reference', 'like', '%'.$v.'%')->orWhere('advertisement_number', 'like', '%'.$v.'%')))
            ->orderBy('exam_date')->orderByRaw('CASE WHEN start_time IS NULL THEN 1 ELSE 0 END')->orderBy('start_time')->orderBy('unit')->orderBy('id');
    }

    public function exportRows(array $f): Collection
    {
        $rows = $this->query($f)->limit(config('scheduler.max_export_rows') + 1)->get();
        abort_if($rows->count() > config('scheduler.max_export_rows'), 422, __('Too many rows. Please narrow the date range or filters.'));

        return $rows;
    }
}
