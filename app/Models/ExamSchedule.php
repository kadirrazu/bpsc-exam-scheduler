<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExamSchedule extends Model
{
    use SoftDeletes;

    protected $attributes = ['post_grade' => null, 'center_count' => null, 'board_count' => null, 'candidate_count' => null, 'ministry' => null, 'reference' => null, 'notes' => null];

    protected $fillable = ['title', 'post_name', 'post_grade', 'ministry', 'reference', 'advertisement_number', 'advertisement_year', 'exam_type', 'unit', 'exam_date', 'start_time', 'end_time', 'candidate_count', 'center_count', 'board_count', 'status', 'notes'];

    protected function casts(): array
    {
        return ['post_grade' => 'integer', 'exam_date' => 'date:Y-m-d', 'candidate_count' => 'integer', 'center_count' => 'integer', 'board_count' => 'integer', 'version' => 'integer', 'advertisement_year' => 'integer'];
    }

    protected function examDate(): Attribute
    {
        return Attribute::make(set: fn ($value) => CarbonImmutable::parse($value)->toDateString());
    }

    public function isViva(): bool
    {
        return (bool) config('scheduler.types.'.$this->exam_type.'.viva', false);
    }

    public function typeLabel(): string
    {
        return __(config('scheduler.types.'.$this->exam_type.'.label', $this->exam_type));
    }
}
