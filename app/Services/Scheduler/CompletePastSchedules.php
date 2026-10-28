<?php

namespace App\Services\Scheduler;

use App\Models\AuditLog;
use App\Models\ExamSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CompletePastSchedules
{
    /** @return array{today:string, eligible:int, completed:int} */
    public function run(bool $dryRun = false): array
    {
        // Freeze the Bangladesh date for the whole run, including across midnight.
        $today = CarbonImmutable::today('Asia/Dhaka')->toDateString();
        $query = ExamSchedule::where('status', 'scheduled')->where('exam_date', '<', $today);
        $eligible = (clone $query)->count();
        $completed = 0;

        if (! $dryRun) {
            $query->select('id')->chunkById(200, function ($rows) use ($today, &$completed) {
                foreach ($rows as $row) {
                    $changed = DB::transaction(function () use ($row, $today) {
                        // Recheck eligibility under the same row lock used by manual edits/deletes.
                        $schedule = ExamSchedule::whereKey($row->id)->where('status', 'scheduled')
                            ->where('exam_date', '<', $today)->lockForUpdate()->first();
                        if (! $schedule) {
                            return false;
                        }
                        $fields = array_flip(['exam_date', 'status', 'version', 'updated_by', 'updated_at']);
                        $before = array_intersect_key($schedule->getRawOriginal(), $fields);
                        $schedule->status = 'completed';
                        $schedule->version++;
                        $schedule->updated_by = null;
                        // One explicit system audit replaces the normal human-action observer.
                        $schedule->saveQuietly();
                        AuditLog::create([
                            'actor_id' => null, 'actor_name' => 'System (Automatic completion)',
                            'action' => 'examschedule.auto_completed',
                            'subject_type' => 'ExamSchedule', 'subject_id' => $schedule->id,
                            'channel' => 'console', 'ip_address' => null, 'user_agent' => null,
                            'details' => [
                                'command' => 'scheduler:complete-past-exams',
                                'timezone' => 'Asia/Dhaka', 'cutoff_date' => $today,
                                'rule' => 'status=scheduled AND exam_date<cutoff_date',
                                'changed' => ['status', 'version', 'updated_by', 'updated_at'],
                                'before' => $before,
                                'after' => array_intersect_key($schedule->getAttributes(), $fields),
                            ],
                            'created_at' => now(),
                        ]);

                        return true;
                    }, 3);
                    if ($changed) {
                        $completed++;
                    }
                }
            });
        }

        return ['today' => $today, 'eligible' => $eligible, 'completed' => $completed];
    }
}
