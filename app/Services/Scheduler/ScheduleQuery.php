<?php

namespace App\Services\Scheduler;

use App\Models\ExamSchedule;
use App\Support\Ui;
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
            'display' => ['nullable', Rule::in(['flat', 'grouped'])],
            'page' => 'nullable|integer|min:1', 'scope' => ['nullable', Rule::in(['all', 'week'])],
        ]);
        $today = CarbonImmutable::today('Asia/Dhaka');
        $d['scope'] = $request->routeIs('dashboard') ? 'week' : ($d['scope'] ?? 'all');
        if (! empty($d['date'])) {
            $d['from'] = $d['to'] = $d['date'];
        } elseif (! empty($d['from']) || ! empty($d['to'])) {
            $d['from'] = $d['from'] ?? $d['to'];
            $d['to'] = $d['to'] ?? $d['from'];
        } elseif ($d['scope'] === 'week') {
            $d['from'] = $today->toDateString();
            $d['to'] = $today->addDays(6)->toDateString();
        }
        $d['display'] = $d['display'] ?? 'flat';
        $d['from'] ??= null;
        $d['to'] ??= null;

        return $d;
    }

    public function query(array $f): Builder
    {
        $query = ExamSchedule::query()
            ->when($f['from'] ?? null, fn ($q, $v) => $q->where('exam_date', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->where('exam_date', '<=', $v))
            ->when($f['exam_type'] ?? null, fn ($q, $v) => $q->where('exam_type', $v))
            ->when($f['unit'] ?? null, fn ($q, $v) => $q->where('unit', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['search'] ?? null, function ($q, $v) {
                $terms = array_unique([$v, Ui::ascii($v), Ui::bengaliDigits($v)]);
                $q->where(function ($q) use ($terms) {
                    foreach ($terms as $term) {
                        foreach (['title', 'post_name', 'ministry', 'reference', 'advertisement_number'] as $field) {
                            $q->orWhere($field, 'like', '%'.$term.'%');
                        }
                    }
                });
            });

        if (($f['scope'] ?? 'all') === 'all') {
            return $query->orderByDesc('exam_date')->orderByDesc('id');
        }

        return $query->orderBy('exam_date')->orderByRaw('CASE WHEN start_time IS NULL THEN 1 ELSE 0 END')->orderBy('start_time')->orderBy('unit')->orderBy('id');
    }

    public function exportRows(array $f): Collection
    {
        $rows = $this->query($f)->limit(config('scheduler.max_export_rows') + 1)->get();
        abort_if($rows->count() > config('scheduler.max_export_rows'), 422, __('Too many rows. Please narrow the date range or filters.'));

        return $rows;
    }
}
