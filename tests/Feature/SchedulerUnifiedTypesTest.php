<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ExamSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchedulerUnifiedTypesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function data(array $changes = []): array
    {
        return array_replace(['post_name' => 'Assistant Director', 'ministry' => 'BPSC', 'unit' => 'Unit 01',
            'exam_type' => 'written', 'exam_date' => today()->toDateString(), 'status' => 'scheduled'], $changes);
    }

    private function staff(): User
    {
        $user = User::factory()->create(['role' => UserRole::Editor, 'preferred_locale' => 'en']);
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);

        return $user;
    }

    public function test_five_types_are_the_only_form_api_and_validation_options(): void
    {
        $user = $this->staff();
        $labels = ['Preliminary (MCQ Type)', 'Written', 'Viva', 'Departmental', 'Senior Scale'];
        $this->assertSame($labels, array_column(config('scheduler.types'), 'label'));
        $form = $this->get('/schedules/create')->assertOk()->assertDontSee('NC Written')->assertDontSee('BCS Viva');
        foreach ($labels as $label) {
            $form->assertSee($label);
        }
        foreach (array_keys(config('scheduler.types')) as $type) {
            $this->post('/schedules', $this->data(['exam_type' => $type] + ($type === 'viva' ? ['board_count' => 2] : [])))->assertRedirect();
        }
        $this->post('/schedules', $this->data(['exam_type' => 'viva']))->assertSessionHasErrors('board_count');
        $this->post('/schedules', $this->data(['exam_type' => 'written', 'board_count' => 2]))->assertSessionHasErrors('board_count');
        $this->post('/schedules', $this->data(['exam_type' => 'nc_written']))->assertSessionHasErrors('exam_type');
        $token = $user->createToken('Test')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/options')->assertOk()
            ->assertJsonCount(5, 'exam_types')->assertJsonPath('exam_types.preliminary.label', $labels[0])
            ->assertJsonPath('exam_types.viva.label', 'Viva')->assertJsonMissingPath('exam_types.bcs_viva');
    }

    public function test_cards_count_distinct_matching_values_across_all_pages(): void
    {
        for ($i = 0; $i < 26; $i++) {
            ExamSchedule::create($this->data(['title' => 'Written Post', 'post_grade' => 9]));
        }
        ExamSchedule::create($this->data(['title' => 'Viva Post', 'unit' => 'Unit 02', 'exam_type' => 'viva', 'post_grade' => 10, 'board_count' => 2]));
        ExamSchedule::create($this->data(['title' => 'No Grade', 'exam_type' => 'preliminary', 'exam_date' => today()->addYear()->toDateString()]));
        $this->staff();
        $this->get('/schedules')->assertOk()->assertViewHas('summary', ['exams' => 28, 'units' => 2, 'exam_types' => 3, 'grades' => 2])
            ->assertViewHas('schedules', fn ($p) => $p->count() === 25);
        $this->get('/dashboard')->assertOk()->assertViewHas('summary', ['exams' => 27, 'units' => 2, 'exam_types' => 2, 'grades' => 2]);
        $this->get('/schedules?exam_type=written')->assertOk()->assertViewHas('summary', ['exams' => 26, 'units' => 1, 'exam_types' => 1, 'grades' => 1]);
        $this->get('/schedules?date=1900-01-01')->assertOk()->assertViewHas('summary', ['exams' => 0, 'units' => 0, 'exam_types' => 0, 'grades' => 0]);
    }

    public function test_migration_converts_legacy_types_including_deleted_rows_without_losing_data(): void
    {
        $mapping = ['nc_preliminary' => 'preliminary', 'bcs_preliminary' => 'preliminary',
            'nc_written' => 'written', 'bcs_written' => 'written', 'nc_viva' => 'viva', 'bcs_viva' => 'viva'];
        $rows = [];
        foreach ($mapping as $legacy => $canonical) {
            $schedule = ExamSchedule::create($this->data(['title' => 'Legacy '.$legacy, 'exam_type' => $legacy,
                'post_grade' => 9, 'reference' => '250027', 'candidate_count' => 15,
                'board_count' => str_ends_with($legacy, 'viva') ? 2 : null]));
            if ($legacy === 'bcs_viva') {
                DB::table('exam_schedules')->where('id', $schedule->id)->update(['deleted_at' => now()]);
            }
            $rows[] = [$schedule->id, $legacy, $canonical];
        }
        $auditCount = AuditLog::count();
        $migration = require database_path('migrations/2026_10_09_000007_unify_scheduler_exam_types.php');
        $migration->up();
        $this->assertSame(6, ExamSchedule::withTrashed()->count());
        $this->assertSame(5, ExamSchedule::count());
        $this->assertSame($auditCount + 6, AuditLog::count());
        foreach ($rows as [$id, $legacy, $canonical]) {
            $row = ExamSchedule::withTrashed()->findOrFail($id);
            $this->assertSame($canonical, $row->exam_type);
            $this->assertSame(2, $row->version);
            $this->assertSame('250027', $row->reference);
            $this->assertSame(9, $row->post_grade);
            $this->assertSame(15, $row->candidate_count);
            $audit = AuditLog::where('action', 'examschedule.type_normalized')->where('subject_id', $id)->firstOrFail();
            $this->assertSame($legacy, $audit->details['before']['exam_type']);
            $this->assertSame($canonical, $audit->details['after']['exam_type']);
            $this->assertSame('console', $audit->channel);
            $this->assertNull($audit->ip_address);
        }
        $migration->up();
        $this->assertSame($auditCount + 6, AuditLog::count());
        $this->assertSame(2, ExamSchedule::withTrashed()->first()->version);
    }
}
