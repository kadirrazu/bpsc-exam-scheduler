<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ExamSchedule;
use App\Models\User;
use App\Services\Scheduler\CompletePastSchedules;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SchedulerAutoCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-29 00:05:00', 'Asia/Dhaka'));
    }

    private function exam(array $changes = []): ExamSchedule
    {
        return ExamSchedule::create(array_replace([
            'title' => 'Automatic completion test', 'post_name' => 'Director', 'ministry' => 'BPSC',
            'unit' => 'Unit 01', 'exam_type' => 'written', 'exam_date' => '2026-10-28',
            'status' => 'scheduled', 'start_time' => '10:00', 'end_time' => '13:00',
            'candidate_count' => 93, 'notes' => 'Preserve this note',
        ], $changes));
    }

    public function test_only_past_scheduled_non_deleted_exams_complete_including_older_backlog(): void
    {
        $past = $this->exam();
        $old = $this->exam(['exam_date' => '2026-01-01', 'exam_type' => 'viva', 'end_time' => null, 'board_count' => 7,
            'board_structure' => [['candidates_per_board' => 15, 'boards' => 3], ['candidates_per_board' => 12, 'boards' => 4]]]);
        $untouched = [];
        foreach (['proposed', 'completed', 'postponed', 'cancelled'] as $status) {
            $untouched[] = $this->exam(['status' => $status]);
        }
        $today = $this->exam(['exam_date' => '2026-10-29', 'end_time' => '00:01']);
        $future = $this->exam(['exam_date' => '2026-10-30']);
        $deleted = $this->exam();
        $deleted->delete();
        $result = app(CompletePastSchedules::class)->run();
        $this->assertSame(['today' => '2026-10-29', 'eligible' => 2, 'completed' => 2], $result);
        $this->assertSame('completed', $past->fresh()->status);
        $this->assertSame(2, $past->fresh()->version);
        $this->assertSame('completed', $old->fresh()->status);
        $this->assertSame(7, $old->fresh()->board_count);
        $this->assertSame(93, $old->fresh()->candidate_count);
        $this->assertCount(2, $old->fresh()->board_structure);
        $this->assertSame('Preserve this note', $past->fresh()->notes);
        foreach ($untouched as $row) {
            $this->assertSame($row->status, $row->fresh()->status);
            $this->assertSame(1, $row->fresh()->version);
        }
        $this->assertSame('scheduled', $today->fresh()->status);
        $this->assertSame('scheduled', $future->fresh()->status);
        $this->assertSame('scheduled', ExamSchedule::withTrashed()->find($deleted->id)->status);
    }

    public function test_audit_is_atomic_explicitly_system_owned_and_repeat_runs_are_idempotent(): void
    {
        $user = User::factory()->create(['role' => UserRole::Editor]);
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
        $this->get('/dashboard')->assertOk();
        $s = $this->exam();
        $s->forceFill(['created_by' => $user->id, 'updated_by' => $user->id])->saveQuietly();
        $completion = app(CompletePastSchedules::class);
        $completion->run();
        $log = AuditLog::where('action', 'examschedule.auto_completed')->sole();
        $this->assertNull($log->actor_id);
        $this->assertSame('System (Automatic completion)', $log->actor_name);
        $this->assertSame('console', $log->channel);
        $this->assertNull($log->ip_address);
        $this->assertSame($s->id, $log->subject_id);
        $this->assertSame('scheduled', $log->details['before']['status']);
        $this->assertSame('completed', $log->details['after']['status']);
        $this->assertSame(1, $log->details['before']['version']);
        $this->assertSame(2, $log->details['after']['version']);
        $this->assertSame('2026-10-29', $log->details['cutoff_date']);
        $this->assertSame('Asia/Dhaka', $log->details['timezone']);
        $this->assertSame($user->id, $s->fresh()->created_by);
        $this->assertNull($s->fresh()->updated_by);
        $this->assertSame(0, $completion->run()['completed']);
        $this->assertSame(1, AuditLog::where('action', 'examschedule.auto_completed')->count());
        $this->assertSame(2, $s->fresh()->version);
    }

    public function test_bangladesh_midnight_defines_eligibility_independent_of_host_timezone(): void
    {
        config(['app.timezone' => 'UTC']);
        $s = $this->exam();
        $this->travelTo(CarbonImmutable::parse('2026-10-28 17:59:59', 'UTC'));
        $this->assertSame(0, app(CompletePastSchedules::class)->run()['completed']);
        $this->assertSame('scheduled', $s->fresh()->status);
        $this->travelTo(CarbonImmutable::parse('2026-10-28 18:00:00', 'UTC'));
        $this->assertSame(1, app(CompletePastSchedules::class)->run()['completed']);
        $this->assertSame('completed', $s->fresh()->status);
    }

    public function test_command_supports_read_only_preview_and_manual_catch_up(): void
    {
        $s = $this->exam();
        $this->artisan('scheduler:complete-past-exams', ['--dry-run' => true])
            ->expectsOutputToContain('Bangladesh date: 2026-10-29')
            ->expectsOutputToContain('Eligible Scheduled exams at start: 1')
            ->expectsOutputToContain('Dry run: no schedules were changed.')->assertSuccessful();
        $this->assertSame('scheduled', $s->fresh()->status);
        $this->assertSame(0, AuditLog::where('action', 'examschedule.auto_completed')->count());
        $this->artisan('scheduler:complete-past-exams')->expectsOutputToContain('Completed exams: 1')->assertSuccessful();
        $this->assertSame('completed', $s->fresh()->status);
    }

    public function test_audit_failure_rolls_back_the_status_and_version(): void
    {
        $s = $this->exam();
        $event = 'eloquent.creating: '.AuditLog::class;
        Event::listen($event, fn () => throw new \RuntimeException('Audit unavailable'));
        try {
            app(CompletePastSchedules::class)->run();
            $this->fail('Expected an audit error.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Audit unavailable', $e->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame('scheduled', $s->fresh()->status);
        $this->assertSame(1, $s->fresh()->version);
        $this->assertSame(0, AuditLog::where('action', 'examschedule.auto_completed')->count());
    }

    public function test_automatic_version_increment_rejects_a_stale_manual_edit(): void
    {
        $user = User::factory()->create(['role' => UserRole::Editor]);
        $s = $this->exam();
        app(CompletePastSchedules::class)->run();
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
        $this->put('/schedules/'.$s->id, ['version' => 1, 'post_name' => 'Changed', 'ministry' => 'BPSC',
            'unit' => 'Unit 01', 'exam_type' => 'written', 'exam_date' => '2026-10-28', 'status' => 'scheduled'])
            ->assertStatus(409);
        $this->assertSame('completed', $s->fresh()->status);
        $this->assertSame('Director', $s->fresh()->post_name);
    }

    public function test_scheduler_runs_at_eight_pm_dhaka_and_supports_disabling_automatic_runs(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', 'scheduler:complete-past-exams'));
        $this->assertNotNull($event);
        $this->assertSame('0 20 * * *', $event->expression);
        $this->assertSame('Asia/Dhaka', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(30, $event->expiresAt);
        config(['scheduler.auto_complete_past_exams' => false]);
        $this->assertFalse($event->filtersPass(app()));
        $s = $this->exam();
        $this->artisan('scheduler:complete-past-exams', ['--scheduled' => true])
            ->expectsOutputToContain('Automatic completion is disabled.')->assertSuccessful();
        $this->assertSame('scheduled', $s->fresh()->status);
        $this->artisan('scheduler:complete-past-exams')->assertSuccessful();
        $this->assertSame('completed', $s->fresh()->status);
    }
}
