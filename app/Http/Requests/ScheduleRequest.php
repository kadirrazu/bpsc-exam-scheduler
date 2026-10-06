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
        foreach (['advertisement_year', 'candidate_count', 'center_count', 'board_count', 'version'] as $field) {
            if ($this->exists($field)) {
                $data[$field] = Ui::ascii($this->input($field));
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
        return ['post_name' => __('Post Name'), 'ministry' => __('Ministry/Organization')];
    }

    public function rules(): array
    {
        $viva = (bool) config('scheduler.types.'.$this->input('exam_type').'.viva', false);

        return [
            'post_name' => 'required|string|max:200', 'ministry' => 'required|string|max:200',
            'title' => 'nullable|string|max:200', 'reference' => 'nullable|string|max:100',
            'advertisement_number' => 'nullable|string|max:100', 'advertisement_year' => 'nullable|integer|min:1900|max:9999',
            'exam_type' => ['required', Rule::in(array_keys(config('scheduler.types')))],
            'unit' => ['required', Rule::in(config('scheduler.units'))],
            'exam_date' => 'required|date_format:Y-m-d',
            'start_time' => 'nullable|date_format:H:i', 'end_time' => ['nullable', 'date_format:H:i', Rule::when($this->filled('start_time'), 'after:start_time')],
            'candidate_count' => 'nullable|integer|min:0|max:10000000',
            'center_count' => [$viva ? 'prohibited' : 'nullable', 'nullable', 'integer', 'min:0', 'max:100000'],
            'board_count' => [$viva ? 'required' : 'prohibited', 'nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::in(array_keys(config('scheduler.statuses')))], 'notes' => 'nullable|string|max:4000',
            'version' => [$this->isMethod('PUT') || $this->isMethod('PATCH') ? 'required' : 'prohibited', 'integer', 'min:1'],
        ];
    }
}
