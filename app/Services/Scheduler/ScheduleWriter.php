<?php

namespace App\Services\Scheduler;

use App\Models\ExamSchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScheduleWriter
{
    public function create(array $data, User $actor): ExamSchedule
    {
        return DB::transaction(function () use ($data, $actor) {
            $s = new ExamSchedule($this->data($data));
            $s->created_by = $s->updated_by = $actor->id;
            $s->save();

            return $s->refresh();
        });
    }

    public function update(ExamSchedule $schedule, array $data, User $actor): ExamSchedule
    {
        return DB::transaction(function () use ($schedule, $data, $actor) {
            $s = ExamSchedule::whereKey($schedule->id)->lockForUpdate()->firstOrFail();
            abort_unless($s->version === (int) $data['version'], 409, __('This schedule was changed by another user. Reload it before editing.'));
            $s->fill($this->data($data));
            $s->updated_by = $actor->id;
            $s->version++;
            $s->save();

            return $s;
        });
    }

    public function delete(ExamSchedule $schedule, int $version, User $actor): void
    {
        DB::transaction(function () use ($schedule, $version, $actor) {
            $s = ExamSchedule::whereKey($schedule->id)->lockForUpdate()->firstOrFail();
            abort_unless($s->version === $version, 409, __('This schedule was changed. Reload before deleting.'));
            $s->updated_by = $actor->id;
            $s->save();
            $s->delete();
        });
    }

    private function data(array $d): array
    {
        unset($d['version']);
        // The web form has no title field. Retain legacy API titles; otherwise derive
        // the internal display summary from the schedule's actual fields.
        if (empty($d['title'])) {
            $parts = [$d['post_name'], config('scheduler.types.'.$d['exam_type'].'.label', $d['exam_type']), $d['exam_date'], $d['unit']];
            foreach (['reference', 'advertisement_number', 'advertisement_year'] as $field) {
                if (isset($d[$field]) && $d[$field] !== '') {
                    $parts[] = (string) $d[$field];
                }
            }
            $d['title'] = Str::limit(implode(' · ', $parts), 200, '');
        }
        $viva = (bool) config('scheduler.types.'.$d['exam_type'].'.viva');
        $d[$viva ? 'center_count' : 'board_count'] = null;
        $d['vacant_posts'] = $d['vacant_posts'] ?? null;
        $d['board_structure'] = $viva ? ($d['board_structure'] ?? null) : null;
        if ($viva) {
            $d['end_time'] = null;
        }

        return $d;
    }
}
