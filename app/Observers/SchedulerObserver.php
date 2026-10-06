<?php

namespace App\Observers;

use App\Models\Designation;
use App\Models\User;
use App\Services\Scheduler\Audit;
use Illuminate\Database\Eloquent\Model;

class SchedulerObserver
{
    private function fields(Model $m): array
    {
        return $m instanceof User ? ['name', 'email', 'designation_id', 'unit', 'role', 'is_active', 'preferred_locale', 'deleted_at'] :
            ($m instanceof Designation ? ['name', 'slug', 'sort_order', 'is_active'] : ['title', 'post_name', 'ministry', 'reference', 'advertisement_number', 'advertisement_year', 'exam_type', 'unit', 'exam_date', 'start_time', 'end_time', 'candidate_count', 'center_count', 'board_count', 'status', 'notes', 'version', 'deleted_at']);
    }

    private function snapshot(Model $m, bool $old = false): array
    {
        return array_intersect_key($old ? $m->getRawOriginal() : $m->getAttributes(), array_flip($this->fields($m)));
    }

    public function created(Model $m): void
    {
        app(Audit::class)->record(strtolower(class_basename($m)).'.created', ['after' => $this->snapshot($m)], $m);
    }

    public function updated(Model $m): void
    {
        $keys = array_intersect(array_keys($m->getChanges()), $this->fields($m));
        if ($m instanceof User && $m->wasChanged('password')) {
            $keys[] = 'password_changed';
        }
        if (! $keys) {
            return;
        }
        app(Audit::class)->record(strtolower(class_basename($m)).'.updated', ['changed' => $keys, 'before' => $this->snapshot($m, true), 'after' => $this->snapshot($m)], $m);
    }

    public function deleted(Model $m): void
    {
        app(Audit::class)->record(strtolower(class_basename($m)).'.deleted', ['before' => $this->snapshot($m)], $m);
    }
}
