<?php

namespace App\Services\Scheduler;

use App\Models\ExamType;
use App\Models\Unit;
use Illuminate\Validation\ValidationException;

class Catalog
{
    private function rows(string $kind): array
    {
        $key = 'scheduler.catalog.'.$kind;
        $request = request();
        if (! $request->attributes->has($key)) {
            $model = $kind === 'types' ? ExamType::class : Unit::class;
            $request->attributes->set($key, $model::orderBy('sort_order')->orderBy('id')->get()->keyBy('code')->toArray());
        }

        return $request->attributes->get($key);
    }

    public function clear(): void
    {
        foreach (['types', 'units'] as $kind) {
            request()->attributes->remove('scheduler.catalog.'.$kind);
        }
    }

    public function types(): array
    {
        return array_map(fn ($r) => ['label' => $r['name'], 'viva' => $r['is_viva']], $this->rows('types'));
    }

    public function typeLabel(?string $code): string
    {
        return $this->rows('types')[$code]['name'] ?? $code ?? '';
    }

    public function isViva(?string $code): bool
    {
        return (bool) ($this->rows('types')[$code]['is_viva'] ?? false);
    }

    public function units(string $scope = 'exams'): array
    {
        return array_keys(array_filter($this->rows('units'), fn ($r) => $r[$scope === 'users' ? 'for_users' : 'for_exams']));
    }

    public function unitLabel(?string $code): string
    {
        return $this->rows('units')[$code]['name'] ?? $code ?? __('Not assigned');
    }

    public function unitLabels(string $scope = 'exams'): array
    {
        $labels = [];
        foreach ($this->units($scope) as $code) {
            $labels[$code] = $this->unitLabel($code);
        }

        return $labels;
    }

    public function lockUnit(string $code, string $scope = 'exams'): void
    {
        $row = Unit::where('code', $code)->lockForUpdate()->first();
        if (! $row || ! $row->{$scope === 'users' ? 'for_users' : 'for_exams'}) {
            throw ValidationException::withMessages(['unit' => __('Select a current unit before saving.')]);
        }
    }

    public function lockSchedule(array $data): void
    {
        // All writers take catalog locks in type/unit order; deletion uses the same parent locks.
        $row = ExamType::where('code', $data['exam_type'])->lockForUpdate()->first();
        if (! $row) {
            throw ValidationException::withMessages(['exam_type' => __('Select a current exam type before saving.')]);
        }
        if (($row->is_viva && (! isset($data['board_count']) || ! empty($data['end_time']) || isset($data['center_count']))) ||
            (! $row->is_viva && (isset($data['board_count']) || ! empty($data['board_structure'])))) {
            throw ValidationException::withMessages(['exam_type' => __('The exam type settings changed. Reload the form before saving.')]);
        }
        $this->lockUnit($data['unit']);
        $this->clear();
    }
}
