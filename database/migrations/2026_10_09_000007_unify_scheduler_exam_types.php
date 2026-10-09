<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $mapping = [
            'nc_preliminary' => 'preliminary', 'bcs_preliminary' => 'preliminary',
            'nc_written' => 'written', 'bcs_written' => 'written',
            'nc_viva' => 'viva', 'bcs_viva' => 'viva',
        ];

        // Include soft-deleted rows; preserve every other schedule field and historical audit.
        DB::transaction(function () use ($mapping) {
            DB::table('exam_schedules')->whereIn('exam_type', array_keys($mapping))
                ->orderBy('id')->chunkById(200, function ($rows) use ($mapping) {
                    foreach ($rows as $row) {
                        $type = $mapping[$row->exam_type];
                        $version = (int) $row->version + 1;
                        $changed = DB::table('exam_schedules')->where('id', $row->id)
                            ->where('exam_type', $row->exam_type)->where('version', $row->version)
                            ->update(['exam_type' => $type, 'version' => $version, 'updated_at' => now()]);
                        if ($changed !== 1) {
                            throw new RuntimeException('A schedule changed during exam type migration. Please retry.');
                        }
                        DB::table('audit_logs')->insert([
                            'actor_id' => null, 'actor_name' => 'System migration',
                            'action' => 'examschedule.type_normalized', 'subject_type' => 'ExamSchedule',
                            'subject_id' => $row->id, 'channel' => 'console', 'ip_address' => null,
                            'user_agent' => '', 'created_at' => now(),
                            'details' => json_encode([
                                'migration' => '2026_10_09_000007_unify_scheduler_exam_types',
                                'before' => ['exam_type' => $row->exam_type, 'version' => (int) $row->version],
                                'after' => ['exam_type' => $type, 'version' => $version],
                            ], JSON_THROW_ON_ERROR),
                        ]);
                    }
                });
        });
    }

    public function down(): void
    {
        // A unified type cannot identify the original BCS/NC classification of new entries.
        throw new RuntimeException('Exam type unification cannot be automatically rolled back. Restore the pre-update database backup with the matching previous code.');
    }
};
