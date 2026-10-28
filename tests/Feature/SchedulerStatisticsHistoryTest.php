<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ExamSchedule;
use App\Models\ExamType;
use App\Models\Unit;
use App\Models\User;
use App\Services\Scheduler\Audit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerStatisticsHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function staff(UserRole $role = UserRole::Viewer, string $name = 'Statistics User'): User
    {
        $u = User::factory()->create(['role' => $role, 'name' => $name, 'preferred_locale' => 'en']);
        $this->actingAs($u)->withSession(['auth_version' => $u->auth_version]);

        return $u;
    }

    private function data(array $extra = []): array
    {
        return array_replace(['post_name' => 'Assistant Director', 'ministry' => 'BPSC', 'exam_type' => 'written', 'unit' => 'Unit 01', 'exam_date' => '2026-10-15', 'status' => 'scheduled'], $extra);
    }

    private function row(array $extra = []): ExamSchedule
    {
        return ExamSchedule::create($this->data(['title' => 'Statistics row'] + $extra));
    }

    public function test_monthly_statistics_include_all_statuses_through_today_by_default(): void
    {
        $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Asia/Dhaka'));
        $this->staff();
        foreach (['proposed', 'scheduled', 'completed', 'postponed', 'cancelled'] as $status) {
            $this->row(['status' => $status]);
        }
        $this->row(['exam_type' => 'viva', 'exam_date' => '2026-10-29', 'board_count' => 2]);
        $this->row(['exam_date' => '2026-10-30', 'status' => 'completed']);
        $this->row(['exam_date' => '2026-09-30']);
        $gone = $this->row();
        $gone->delete();
        $this->get('/statistics')->assertOk()->assertViewHas('summary', fn ($s) => $s['exams'] === 6 && $s['units'] === 1 && $s['statuses']['postponed'] === 1 && $s['statuses']['cancelled'] === 1)
            ->assertViewHas('filters', fn ($f) => $f['period'] === 'month' && $f['year'] === 2026 && $f['month'] === 10)
            ->assertViewHas('types', fn ($t) => collect($t)->firstWhere('code', 'written')['exams'] === 5 && collect($t)->firstWhere('code', 'viva')['exams'] === 1)
            ->assertSee('Postponed')->assertSee('Cancelled');
        $this->get('/statistics?period=month&year=2026&month=11')->assertOk()->assertViewHas('summary', fn ($s) => $s['exams'] === 0 && $s['units'] === 0);
        $this->travelBack();
    }

    public function test_year_and_unit_filters_use_current_catalog_names_and_leap_dates(): void
    {
        $this->travelTo(Carbon::parse('2028-12-20 12:00', 'Asia/Dhaka'));
        $this->staff();
        $this->row(['exam_date' => '2028-02-29', 'unit' => 'Unit 04', 'exam_type' => 'practical_test']);
        $this->row(['exam_date' => '2028-10-01', 'unit' => 'Unit 04', 'exam_type' => 'typing_test', 'status' => 'completed']);
        $this->row(['exam_date' => '2028-05-01', 'unit' => 'Unit 01']);
        $this->row(['exam_date' => '2027-12-31', 'unit' => 'Unit 04']);
        $this->row(['exam_date' => '2028-12-21', 'unit' => 'Unit 04']);
        $this->row(['exam_date' => '2028-10-01', 'unit' => 'Unit 04', 'status' => 'cancelled']);
        Unit::where('code', 'Unit 04')->update(['name' => 'Unit Four Current']);
        $this->get('/statistics?period=year&year=2028&unit=Unit%2004')->assertOk()->assertViewHas('summary', fn ($s) => $s['exams'] === 3 && $s['units'] === 1)->assertSee('Unit Four Current')
            ->assertViewHas('types', fn ($t) => collect($t)->firstWhere('code', 'practical_test')['exams'] === 1 && collect($t)->firstWhere('code', 'typing_test')['exams'] === 1);
        $this->get('/statistics?period=month&year=2028&month=2&unit=Unit%2004')->assertOk()->assertViewHas('summary', fn ($s) => $s['exams'] === 1 && $s['units'] === 1);
        $this->travelBack();
    }

    public function test_statistics_api_auth_validation_timezone_dynamic_types_and_report_audit(): void
    {
        $this->getJson('/api/v1/statistics')->assertUnauthorized();
        $u = $this->staff(UserRole::Viewer);
        $this->travelTo(Carbon::parse('2026-10-31 18:30:00', 'UTC'));
        $this->app['auth']->forgetGuards();
        $this->withToken($u->createToken('statistics')->plainTextToken);
        $type = ExamType::create(['code' => 'custom_stats', 'name' => 'New Assessment', 'sort_order' => 30, 'is_viva' => false]);
        $this->row(['exam_type' => $type->code, 'exam_date' => '2026-11-01', 'status' => 'proposed']);
        $this->getJson('/api/v1/statistics')->assertOk()->assertJsonPath('filters.month', 11)->assertJsonPath('summary.exams', 1)
            ->assertJsonPath('as_of', '2026-11-01');
        $this->getJson('/api/v1/statistics?period=year&year=২০২৬')->assertOk()->assertJsonPath('filters.year', 2026)->assertJsonPath('summary.exams', 1);
        foreach (['period' => 'invalid', 'year' => '1899', 'month' => '13', 'unit' => 'IT Section'] as $key => $value) {
            $this->getJson('/api/v1/statistics?'.$key.'='.urlencode($value))->assertUnprocessable();
        }
        $log = AuditLog::where('action', 'report.viewed')->get()->first(fn ($l) => ($l->details['report'] ?? '') === 'exam_statistics');
        $this->assertNotNull($log);
        $this->assertSame($u->id, $log->actor_id);
        $this->assertSame('127.0.0.1', $log->ip_address);
        $this->travelBack();
    }

    public function test_statistics_is_readable_for_all_roles_without_administration_access(): void
    {
        foreach ([UserRole::Admin, UserRole::Editor, UserRole::Viewer] as $role) {
            $this->staff($role);
            $this->get('/statistics')->assertOk();
            $this->get('/dashboard')->assertOk()->assertSee('Statistics');
        }
    }

    public function test_schedule_history_is_only_on_authorized_web_details_and_preserves_actor_snapshots(): void
    {
        $creator = $this->staff(UserRole::Admin, 'Original Schedule Creator');
        $this->post('/schedules', $this->data())->assertRedirect();
        $schedule = ExamSchedule::firstOrFail();
        $editor = $this->staff(UserRole::Editor, 'Original Schedule Editor');
        $this->put('/schedules/'.$schedule->id, $this->data(['version' => 1, 'notes' => 'Edited note']))->assertRedirect();
        $creator->update(['name' => 'Creator renamed']);
        $creator->delete();
        $editor->update(['name' => 'Editor renamed']);
        $other = $this->row();
        app(Audit::class)->record('examschedule.updated', ['changed' => ['notes']], $other, User::factory()->create(['name' => 'Other record secret actor']));
        $this->get('/schedules/'.$schedule->id)->assertOk()->assertSee('Creation and edit history')->assertSee('Original Schedule Creator')->assertSee('Original Schedule Editor')->assertDontSee('Other record secret actor')
            ->assertViewHas('history', fn ($p) => $p->total() === 2 && $p->getCollection()->first()['fields'] === [__('Notes/Remarks')]);
        foreach (['/dashboard', '/calendar?month=2026-10', '/schedules', '/schedules/export/print'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('Original Schedule Creator')->assertDontSee('Original Schedule Editor');
        }
        $this->get('/audit-logs')->assertForbidden();
        $this->staff(UserRole::Viewer, 'History Viewer');
        $this->get('/schedules/'.$schedule->id)->assertOk()->assertDontSee('Creation and edit history')->assertDontSee('Original Schedule Creator')->assertDontSee('Original Schedule Editor')->assertViewHas('history', null);
        $this->staff(UserRole::Admin, 'Another Admin');
        $this->get('/schedules/'.$schedule->id)->assertOk()->assertSee('Original Schedule Creator')->assertSee('Original Schedule Editor');
    }

    public function test_api_does_not_expose_private_history_or_author_ids_for_any_role(): void
    {
        $u = $this->staff(UserRole::Admin);
        $this->post('/schedules', $this->data())->assertRedirect();
        $s = ExamSchedule::firstOrFail();
        foreach ([UserRole::Admin, UserRole::Editor, UserRole::Viewer] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->app['auth']->forgetGuards();
            $this->withToken($u->createToken('private history')->plainTextToken);
            $this->getJson('/api/v1/schedules/'.$s->id)->assertOk()->assertJsonMissingPath('data.created_by')->assertJsonMissingPath('data.updated_by')->assertJsonMissingPath('history');
            $this->getJson('/api/v1/schedules')->assertOk()->assertJsonMissingPath('schedules.data.0.created_by')->assertJsonMissingPath('schedules.data.0.updated_by');
        }
        $this->assertNotNull($s->fresh()->created_by);
    }

    public function test_history_is_paginated_and_invalid_history_pages_are_rejected(): void
    {
        $this->staff(UserRole::Editor);
        $this->post('/schedules', $this->data())->assertRedirect();
        $s = ExamSchedule::firstOrFail();
        for ($i = 0; $i < 26; $i++) {
            app(Audit::class)->record('examschedule.updated', ['changed' => ['notes']], $s);
        }
        $this->get('/schedules/'.$s->id.'?history_page=2')->assertOk()->assertViewHas('history', fn ($p) => $p->total() === 27 && $p->count() === 2);
        $this->get('/schedules/'.$s->id.'?history_page=0')->assertSessionHasErrors('history_page');
    }

    public function test_custom_range_contains_all_statuses_and_can_include_future_schedules(): void
    {
        $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Asia/Dhaka'));
        $u = $this->staff();
        foreach (['proposed', 'scheduled', 'completed', 'postponed', 'cancelled'] as $status) {
            $this->row(['status' => $status, 'exam_date' => '2026-10-29']);
        }
        $this->row(['exam_date' => '2026-11-02']);
        $this->row(['exam_date' => '2026-10-28']);
        $this->get('/statistics?period=range&from=2026-10-29&to=2026-11-02')->assertOk()->assertViewHas('summary', fn ($s) => $s['exams'] === 5 && array_sum($s['statuses']) === 5);
        $this->get('/statistics?period=range&from=2026-10-29&to=2026-11-02&through_today=0')->assertOk()->assertViewHas('summary', fn ($s) => $s['exams'] === 6 && $s['statuses']['scheduled'] === 2);
        $this->get('/statistics?period=range&from=2026-11-02&to=2026-10-29')->assertSessionHasErrors('to');
        $this->get('/statistics?period=range')->assertSessionHasErrors(['from', 'to']);
        $this->app['auth']->forgetGuards();
        $this->withToken($u->createToken('range stats')->plainTextToken);
        $this->getJson('/api/v1/statistics?period=range&from=2026-10-29&to=2026-11-02&through_today=0')->assertOk()->assertJsonPath('summary.exams',6)->assertJsonPath('summary.statuses.scheduled',2)->assertJsonPath('summary.statuses.cancelled',1)->assertJsonPath('filters.through_today',false);
        $this->travelBack();
    }
}
