<?php

namespace App\Services\Scheduler;

use App\Models\AuditLog;
use App\Models\ExamSchedule;
use Illuminate\Pagination\LengthAwarePaginator;

class ScheduleHistory
{
    private const FIELDS = ['post_name' => 'Post Name', 'post_grade' => 'Post Grade', 'vacant_posts' => 'Number of Vacant Posts', 'ministry' => 'Ministry/Organization', 'reference' => 'Post Code', 'advertisement_number' => 'Advertisement Number', 'advertisement_year' => 'Advertisement Year', 'exam_type' => 'Exam Type', 'unit' => 'Unit', 'exam_date' => 'Exam Date', 'start_time' => 'Start Time', 'end_time' => 'End Time', 'candidate_count' => 'Number of Candidates', 'center_count' => 'Centers', 'board_count' => 'Number of Boards', 'board_structure' => 'Board Structure', 'status' => 'Status', 'notes' => 'Notes/Remarks'];

    public function forSchedule(ExamSchedule $schedule): LengthAwarePaginator
    {
        $logs = AuditLog::where('subject_type', 'ExamSchedule')->where('subject_id', $schedule->id)
            ->whereIn('action', ['examschedule.created', 'examschedule.updated'])->orderByDesc('id')
            ->paginate(25, ['id', 'actor_name', 'action', 'created_at', 'details'], 'history_page')->withQueryString()->fragment('schedule-history');
        $logs->setCollection($logs->getCollection()->map(function ($log) {
            $fields = [];
            foreach (($log->details['changed'] ?? []) as $key) {
                if (isset(self::FIELDS[$key])) {
                    $fields[] = __(self::FIELDS[$key]);
                }
            }

            // Only these display fields reach the editor-facing view; no raw snapshots/IP/other audits.
            return ['action' => $log->action === 'examschedule.created' ? 'Created' : 'Edited', 'actor' => $log->actor_name ?: __('Not recorded'), 'at' => $log->created_at, 'fields' => array_values(array_unique($fields))];
        }));

        return $logs;
    }
}
