<?php

namespace App\Models;

use App\Services\Scheduler\Catalog;
use App\Support\Ui;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExamSchedule extends Model
{
    use SoftDeletes;

    protected $attributes = ['vacant_posts' => null, 'board_structure' => null, 'post_grade' => null, 'center_count' => null, 'board_count' => null, 'candidate_count' => null, 'ministry' => null, 'reference' => null, 'notes' => null];

    protected $hidden = ['created_by', 'updated_by'];

    protected $fillable = ['title', 'post_name', 'post_grade', 'vacant_posts', 'board_structure', 'ministry', 'reference', 'advertisement_number', 'advertisement_year', 'exam_type', 'unit', 'exam_date', 'start_time', 'end_time', 'candidate_count', 'center_count', 'board_count', 'status', 'notes'];

    protected function casts(): array
    {
        return ['vacant_posts' => 'integer', 'board_structure' => 'array', 'post_grade' => 'integer', 'exam_date' => 'date:Y-m-d', 'candidate_count' => 'integer', 'center_count' => 'integer', 'board_count' => 'integer', 'version' => 'integer', 'advertisement_year' => 'integer'];
    }

    protected function examDate(): Attribute
    {
        return Attribute::make(set: fn ($value) => CarbonImmutable::parse($value)->toDateString());
    }

    public function timeLabel(): string
    {
        $start = $this->start_time ? Ui::date($this->start_time, 'h:i A') : '—';
        if ($this->isViva()) {
            return $start;
        }
        $end = $this->end_time ? Ui::date($this->end_time, 'h:i A') : '—';

        return ! $this->start_time && ! $this->end_time ? '—' : $start.' - '.$end;
    }

    public function structureLabel(): string
    {
        return implode(' + ', $this->structureLines());
    }

    public function structureLines(): array
    {
        return array_map(fn ($row) => Ui::digits($row['candidates_per_board']).' × '.Ui::digits($row['boards']).' '.__('boards'), $this->board_structure ?? []);
    }

    public function boardsLabel(): string
    {
        if (! $this->isViva()) {
            return '—';
        }
        $lines = $this->structureLines();
        $total = Ui::number($this->board_count);

        return $lines ? implode("\n", $lines)."\n".__('Total Boards').': '.$total : $total;
    }

    public function unitLabel(): string
    {
        return app(Catalog::class)->unitLabel($this->unit);
    }

    public function isViva(): bool
    {
        return app(Catalog::class)->isViva($this->exam_type);
    }

    public function typeLabel(): string
    {
        return app(Catalog::class)->typeLabel($this->exam_type);
    }
}
