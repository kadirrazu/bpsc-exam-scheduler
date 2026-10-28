<?php

namespace App\Console\Commands;

use App\Services\Scheduler\CompletePastSchedules;
use Illuminate\Console\Command;

class CompletePastExams extends Command
{
    protected $signature = 'scheduler:complete-past-exams
        {--dry-run : Count eligible exams without changing them}
        {--scheduled : Respect the automatic-completion setting when called by a host timer}';

    protected $description = 'Complete Scheduled exams dated before today in Asia/Dhaka';

    public function handle(CompletePastSchedules $completion): int
    {
        if ($this->option('scheduled') && ! config('scheduler.auto_complete_past_exams')) {
            $this->info('Automatic completion is disabled.');

            return self::SUCCESS;
        }
        $dryRun = (bool) $this->option('dry-run');
        $result = $completion->run($dryRun);
        $this->line('Bangladesh date: '.$result['today']);
        $this->line('Eligible Scheduled exams at start: '.$result['eligible']);
        if ($dryRun) {
            $this->info('Dry run: no schedules were changed.');
        } else {
            $this->info('Completed exams: '.$result['completed']);
        }

        return self::SUCCESS;
    }
}
