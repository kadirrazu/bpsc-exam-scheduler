<?php

namespace App\Http\Requests;

use App\Support\Ui;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $data = [];
        foreach (['vacant_posts', 'post_grade', 'advertisement_year', 'candidate_count', 'center_count', 'board_count', 'version'] as $field) {
            if ($this->exists($field)) {
                $data[$field] = Ui::ascii($this->input($field));
            }
        }
        if ($this->exists('board_structure') && is_array($this->input('board_structure'))) {
            $rows = [];
            $valid = true;
            $boards = $candidates = 0;
            foreach ($this->input('board_structure') as $row) {
                if (!is_array($row)) { $rows[] = $row; $valid = false; continue; }
                $a = Ui::ascii($row['candidates_per_board'] ?? null);
                $b = Ui::ascii($row['boards'] ?? null);
                if (($a === null || $a === '') && ($b === null || $b === '')) { continue; }
                $rows[] = array_replace($row, ['candidates_per_board' => $a, 'boards' => $b]);
                if (!is_scalar($a) || !is_scalar($b) || !ctype_digit((string) $a) || !ctype_digit((string) $b) || (int) $a < 1 || (int) $a > 10000000 || (int) $b < 1 || (int) $b > 100000) { $valid = false; continue; }
                $boards += (int) $b;
                $candidates += (int) $a * (int) $b;
            }
            $data['board_structure'] = $rows ?: null;
            if ($this->input('exam_type') === 'viva' && $rows && $valid && count($rows) <= 50) {
                foreach (['board_count' => $boards, 'candidate_count' => $candidates] as $field => $value) {
                    if (!$this->filled($field)) { $data[$field] = $value; }
                }
            }
        }
        $this->merge($data);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('edit-schedules') ?? false;
    }

    public function attributes(): array
    {
        return ['post_name' => __('Post Name'), 'post_grade' => __('Post Grade'), 'ministry' => __('Ministry/Organization')];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) { return; }
            $boards = $candidates = 0;
            foreach ($this->input('board_structure') ?? [] as $row) {
                $boards += (int) $row['boards'];
                $candidates += (int) $row['boards'] * (int) $row['candidates_per_board'];
            }
            if ($boards > 100000 || $candidates > 10000000) {
                $validator->errors()->add('board_structure', __('Board structure totals exceed the allowed limits.'));
            }
        });
    }

    public function rules(): array
    {
        $viva = (bool) config('scheduler.types.'.$this->input('exam_type').'.viva', false);

        return [
            'vacant_posts' => 'nullable|integer|min:0|max:10000000',
            'board_structure' => [$viva ? 'nullable' : 'prohibited', 'nullable', 'array', 'max:50'],
            'board_structure.*' => 'required|array:candidates_per_board,boards',
            'board_structure.*.candidates_per_board' => 'required|integer|min:1|max:10000000',
            'board_structure.*.boards' => 'required|integer|min:1|max:100000',
            'post_name' => 'required|string|max:200', 'post_grade' => 'nullable|integer|min:1|max:65535', 'ministry' => 'required|string|max:200',
            'title' => 'nullable|string|max:200', 'reference' => 'nullable|string|max:100',
            'advertisement_number' => 'nullable|string|max:100', 'advertisement_year' => 'nullable|integer|min:1900|max:9999',
            'exam_type' => ['required', Rule::in(array_keys(config('scheduler.types')))],
            'unit' => ['required', Rule::in(config('scheduler.units'))],
            'exam_date' => 'required|date_format:Y-m-d',
            'start_time' => 'nullable|date_format:H:i', 'end_time' => [$viva ? 'prohibited' : 'nullable', 'nullable', 'date_format:H:i', Rule::when($this->filled('start_time'), 'after:start_time')],
            'candidate_count' => 'nullable|integer|min:0|max:10000000',
            'center_count' => [$viva ? 'prohibited' : 'nullable', 'nullable', 'integer', 'min:0', 'max:100000'],
            'board_count' => [$viva ? 'required' : 'prohibited', 'nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::in(array_keys(config('scheduler.statuses')))], 'notes' => 'nullable|string|max:4000',
            'version' => [$this->isMethod('PUT') || $this->isMethod('PATCH') ? 'required' : 'prohibited', 'integer', 'min:1'],
        ];
    }
}
