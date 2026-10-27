<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ExamSchedule;
use App\Models\ExamType;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ExamTypeSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchedulerCatalogCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function staff(UserRole $role = UserRole::Admin): User
    {
        $u = User::factory()->create(['role' => $role, 'preferred_locale' => 'en', 'unit' => 'IT Section']);
        $this->actingAs($u)->withSession(['auth_version' => $u->auth_version]);

        return $u;
    }

    private function schedule(array $data = []): array
    {
        return array_replace(['unit' => 'Unit 01', 'exam_type' => 'written', 'post_name' => 'Director', 'ministry' => 'BPSC', 'exam_date' => '2026-10-27', 'status' => 'scheduled'], $data);
    }

    public function test_seed_defaults_are_ordered_repeatable_and_preserve_admin_edits(): void
    {
        $this->assertSame(8, ExamType::count());
        $this->assertSame(27, Unit::count());
        $this->assertSame(['Aptitude Test', 'Practical Test', 'Typing Test'], ExamType::orderBy('sort_order')->get()->slice(-3)->pluck('name')->all());
        ExamType::where('code', 'written')->update(['name' => 'Written Updated', 'sort_order' => 50]);
        Unit::where('code', 'Unit 01')->update(['name' => 'Examination Unit One']);
        $this->seed([ExamTypeSeeder::class, UnitSeeder::class]);
        $this->seed([ExamTypeSeeder::class, UnitSeeder::class]);
        $this->assertSame(8, ExamType::count());
        $this->assertSame(27, Unit::count());
        $this->assertSame('Written Updated', ExamType::where('code', 'written')->first()->name);
        $this->assertSame('Examination Unit One', Unit::where('code', 'Unit 01')->first()->name);
        ExamType::where('code', 'aptitude_test')->delete();
        ExamType::create(['code' => 'custom_aptitude', 'name' => 'Aptitude Test', 'sort_order' => 6, 'is_viva' => false]);
        Unit::where('code', 'Unit 20')->delete();
        Unit::create(['code' => 'custom_unit_twenty', 'name' => 'Unit 20', 'sort_order' => 20, 'for_exams' => true, 'for_users' => true]);
        $this->seed([ExamTypeSeeder::class, UnitSeeder::class]);
        $this->assertSame(8, ExamType::count());
        $this->assertSame(27, Unit::count());
        $this->assertSame('custom_aptitude', ExamType::where('name', 'Aptitude Test')->first()->code);
        $this->assertSame('custom_unit_twenty', Unit::where('name', 'Unit 20')->first()->code);
    }

    public function test_crud_is_admin_only_on_web_and_api_and_calendar_is_readable(): void
    {
        foreach ([UserRole::Viewer, UserRole::Editor] as $role) {
            $u = $this->staff($role);
            foreach (['/exam-types', '/units'] as $path) {
                $this->get($path)->assertForbidden();
                $this->get($path.'/create')->assertForbidden();
                $this->post($path, [])->assertForbidden();
                $this->put($path.'/1', [])->assertForbidden();
                $this->delete($path.'/1', [])->assertForbidden();
            }
            $this->get('/calendar?month=2026-10')->assertOk();
            $this->app['auth']->forgetGuards();
            $this->withToken($u->createToken('catalog permissions')->plainTextToken);
            foreach (['/exam-types', '/units'] as $path) {
                $this->getJson('/api/v1'.$path)->assertForbidden();
                $this->postJson('/api/v1'.$path, [])->assertForbidden();
                $this->putJson('/api/v1'.$path.'/1', [])->assertForbidden();
                $this->deleteJson('/api/v1'.$path.'/1', [])->assertForbidden();
            }
            $this->getJson('/api/v1/calendar?month=2026-10')->assertOk();
            $this->withHeaders(['Authorization' => '']);
        }
    }

    public function test_type_crud_populates_forms_filters_api_and_dynamic_viva_workflow(): void
    {
        $this->staff();
        $this->get('/exam-types')->assertOk();
        $this->get('/exam-types/create')->assertOk();
        $this->post('/exam-types', ['name' => 'Special Board Interview', 'sort_order' => 20, 'is_viva' => 1])->assertRedirect();
        $t = ExamType::where('name', 'Special Board Interview')->firstOrFail();
        $this->get('/exam-types/'.$t->id.'/edit')->assertOk();
        $this->get('/schedules/create')->assertOk()->assertSee('Special Board Interview')->assertSee('data-viva="1"', false);
        $this->post('/schedules', $this->schedule(['exam_type' => $t->code, 'board_structure' => [['candidates_per_board' => 15, 'boards' => 3]]]))->assertRedirect();
        $s = ExamSchedule::firstOrFail();
        $this->assertSame(3, $s->board_count);
        $this->assertSame(45, $s->candidate_count);
        $this->assertTrue($s->isViva());
        $this->get('/schedules/'.$s->id.'/edit')->assertOk()->assertSee('data-end-time  hidden', false);
        $this->get('/schedules?exam_type='.$t->code)->assertViewHas('summary', fn ($summary) => $summary['exams'] === 1);
        $this->put('/exam-types/'.$t->id, ['name' => 'Interview Updated', 'sort_order' => 21, 'is_viva' => 1, 'version' => 1])->assertRedirect();
        $this->get('/schedules?search=Interview%20Updated')->assertOk()->assertSee('Interview Updated')->assertViewHas('summary', fn ($summary) => $summary['exams'] === 1);
        $this->assertSame($t->code, $s->fresh()->exam_type);
        $this->put('/exam-types/'.$t->id, ['name' => 'Wrong workflow', 'sort_order' => 21, 'is_viva' => 0, 'version' => 2])->assertSessionHasErrors('entry');
        $this->delete('/exam-types/'.$t->id, ['confirmation' => 'DELETE', 'version' => 2])->assertSessionHasErrors('entry');
        $this->put('/exam-types/'.$t->id, ['name' => 'Stale', 'sort_order' => 21, 'is_viva' => 1, 'version' => 1])->assertStatus(409);
        $this->assertSame('Interview Updated', $t->fresh()->name);
        $this->assertNotNull(AuditLog::where('action', 'examtype.updated')->where('subject_id', $t->id)->first());
    }

    public function test_unit_rename_propagates_without_rewriting_users_or_schedules_and_usage_is_protected(): void
    {
        $this->staff();
        $this->post('/units', ['name' => 'New Unit', 'sort_order' => 40, 'for_exams' => 1, 'for_users' => 1])->assertRedirect();
        $u = Unit::where('name', 'New Unit')->firstOrFail();
        $this->get('/units/'.$u->id.'/edit')->assertOk();
        $this->post('/schedules', $this->schedule(['unit' => $u->code, 'exam_type' => 'typing_test']))->assertRedirect();
        $s = ExamSchedule::firstOrFail();
        $user = User::factory()->create(['unit' => $u->code, 'role' => UserRole::Viewer]);
        $this->put('/units/'.$u->id, ['name' => 'Renamed Unit', 'sort_order' => 41, 'for_exams' => 1, 'for_users' => 1, 'version' => 1])->assertRedirect();
        $this->get('/schedules?search=Renamed%20Unit')->assertOk()->assertSee('Renamed Unit')->assertViewHas('summary', fn ($summary) => $summary['exams'] === 1);
        $this->get('/users?search=Renamed%20Unit')->assertOk()->assertSee($user->email)->assertSee('Renamed Unit');
        $this->get('/schedules/create')->assertOk()->assertSee('Renamed Unit');
        $this->get('/users/create')->assertOk()->assertSee('Renamed Unit');
        $this->assertSame($u->code, $s->fresh()->unit);
        $this->assertSame($u->code, $user->fresh()->unit);
        $this->put('/units/'.$u->id, ['name' => 'Renamed Unit', 'sort_order' => 41, 'for_exams' => 0, 'for_users' => 1, 'version' => 2])->assertSessionHasErrors('entry');
        $this->put('/units/'.$u->id, ['name' => 'Renamed Unit', 'sort_order' => 41, 'for_exams' => 1, 'for_users' => 0, 'version' => 2])->assertSessionHasErrors('entry');
        $s->delete();
        $user->delete();
        $this->delete('/units/'.$u->id, ['confirmation' => 'DELETE', 'version' => 2])->assertSessionHasErrors('entry');
        $this->assertNotNull($u->fresh());
    }

    public function test_api_catalog_crud_and_options_have_canonical_codes_and_current_labels(): void
    {
        $u = $this->staff();
        $this->app['auth']->forgetGuards();
        $this->withToken($u->createToken('catalog writes')->plainTextToken);
        $t = $this->postJson('/api/v1/exam-types', ['name' => 'API Practical', 'sort_order' => 30, 'is_viva' => false])->assertCreated()->json('data');
        $unit = $this->postJson('/api/v1/units', ['name' => 'API Unit', 'sort_order' => 30, 'for_exams' => true, 'for_users' => false])->assertCreated()->json('data');
        $this->getJson('/api/v1/options')->assertOk()->assertJsonPath('exam_types.'.$t['code'].'.label', 'API Practical')->assertJsonPath('unit_labels.'.$unit['code'], 'API Unit');
        $this->postJson('/api/v1/schedules', $this->schedule(['unit' => $unit['code'], 'exam_type' => $t['code']]))->assertCreated();
        $this->patchJson('/api/v1/exam-types/'.$t['id'], ['name' => 'API Practical Changed', 'sort_order' => 30, 'is_viva' => false, 'version' => 1])->assertOk()->assertJsonPath('data.version', 2);
        $this->getJson('/api/v1/options')->assertJsonPath('exam_types.'.$t['code'].'.label', 'API Practical Changed');
        $this->deleteJson('/api/v1/units/'.$unit['id'], ['confirmation' => 'DELETE', 'version' => 1])->assertUnprocessable();
        $unused = $this->postJson('/api/v1/exam-types', ['name' => 'Unused', 'sort_order' => 30, 'is_viva' => false])->json('data');
        $this->deleteJson('/api/v1/exam-types/'.$unused['id'], ['confirmation' => 'DELETE', 'version' => 1])->assertNoContent();
        $this->getJson('/api/v1/options')->assertJsonMissingPath('exam_types.'.$unused['code']);
        $empty = $this->postJson('/api/v1/units', ['name' => 'Empty Unit', 'sort_order' => 30, 'for_exams' => true, 'for_users' => true])->json('data');
        $this->deleteJson('/api/v1/units/'.$empty['id'], ['confirmation' => 'DELETE', 'version' => 1])->assertNoContent();
        $this->postJson('/api/v1/schedules', $this->schedule(['unit' => $empty['code']]))->assertUnprocessable();
    }

    public function test_invalid_duplicate_catalog_and_unit_scope_changes_are_rejected(): void
    {
        $this->staff();
        $this->post('/exam-types', ['name' => 'Written', 'sort_order' => 1, 'is_viva' => 0])->assertSessionHasErrors('name');
        $this->post('/exam-types', ['name' => 'New', 'sort_order' => 1, 'is_viva' => 0, 'code' => 'written'])->assertSessionHasErrors('code');
        $this->post('/units', ['name' => 'Empty', 'sort_order' => 1, 'for_exams' => 0, 'for_users' => 0])->assertSessionHasErrors('for_exams');
        $this->post('/schedules', $this->schedule(['unit' => 'IT Section']))->assertSessionHasErrors('unit');
        $t = ExamType::where('code', 'written')->first();
        $this->delete('/exam-types/'.$t->id, ['version' => 1])->assertSessionHasErrors('confirmation');
        $this->delete('/exam-types/'.$t->id, ['version' => 2, 'confirmation' => 'DELETE'])->assertStatus(409);
    }

    public function test_calendar_month_counts_exclude_deleted_and_open_the_same_table_for_selected_day(): void
    {
        $this->staff(UserRole::Viewer);
        foreach (['written', 'written', 'viva', 'aptitude_test'] as $type) {
            ExamSchedule::create($this->schedule(['exam_type' => $type, 'title' => 'Calendar '.$type, 'post_name' => 'Calendar '.$type]));
        }
        $gone = ExamSchedule::create($this->schedule(['title' => 'Deleted row', 'post_name' => 'Deleted row']));
        $gone->delete();
        ExamSchedule::create($this->schedule(['title' => 'Other month', 'post_name' => 'Other month', 'exam_date' => '2026-11-01']));
        $this->get('/calendar?month=2026-10')->assertOk()->assertSee('role="tooltip"', false)
            ->assertViewHas('cells', fn ($cells) => $cells[0]['date']->dayOfWeek === 0 && $cells[array_key_last($cells)]['date']->dayOfWeek === 6 && count($cells) % 7 === 0)
            ->assertViewHas('monthTotal', 4)->assertViewHas('days', fn ($days) => $days['2026-10-27']['total'] === 4 && count($days['2026-10-27']['types']) === 3);
        $this->get('/calendar?month=2026-10&date=2026-10-27')->assertOk()->assertSee('Calendar written')->assertDontSee('Deleted row')->assertDontSee('Other month')->assertDontSee('Edit</a>', false)
            ->assertViewHas('schedules', fn ($p) => $p->total() === 4);
        $this->get('/calendar?month=2026-10&exam_type=aptitude_test&date=2026-10-27')->assertOk()->assertViewHas('monthTotal', 1)->assertViewHas('schedules', fn ($p) => $p->total() === 1);
        $this->get('/calendar?month=2026-10&date=2026-10-28')->assertOk()->assertViewHas('schedules', fn ($p) => $p->total() === 0);
        $this->get('/calendar?month=2026-10&date=2026-11-01')->assertSessionHasErrors('date');
        $this->get('/calendar?month=2026-13')->assertSessionHasErrors('month');
        $this->get('/calendar?month=2026-10&unit=not-existing')->assertSessionHasErrors('unit');
        $log = AuditLog::where('action', 'report.viewed')->get()->first(fn ($l) => ($l->details['report'] ?? '') === 'exam_calendar');
        $this->assertSame('127.0.0.1', $log->ip_address);
        $this->assertNotNull($log->actor_id);
    }

    public function test_calendar_api_timezone_leap_month_and_day_pagination(): void
    {
        $u = $this->staff(UserRole::Viewer);
        $this->travelTo(Carbon::parse('2026-10-31 18:30:00', 'UTC'));
        $this->get('/calendar')->assertOk()->assertViewHas('month', fn ($month) => $month->format('Y-m') === '2026-11');
        $this->travelBack();
        for ($i = 0; $i < 27; $i++) {
            ExamSchedule::create($this->schedule(['title' => 'Leap '.$i, 'exam_date' => '2028-02-29']));
        }
        $this->app['auth']->forgetGuards();
        $this->withToken($u->createToken('calendar')->plainTextToken);
        $this->getJson('/api/v1/calendar?month=2028-02&date=2028-02-29&page=2')->assertOk()->assertJsonPath('total', 27)->assertJsonPath('days.2028-02-29.total', 27)->assertJsonPath('schedules.total', 27)->assertJsonCount(2, 'schedules.data');
        $this->getJson('/api/v1/calendar?month=2027-02&date=2027-02-29')->assertUnprocessable();
    }

    public function test_catalog_migration_preserves_historical_codes_and_versions(): void
    {
        $s = ExamSchedule::create($this->schedule(['title' => 'Historical', 'unit' => 'Historical Unit', 'exam_type' => 'historical_custom']));
        User::factory()->create(['unit' => 'Historical Staff']);
        Schema::drop('units');
        Schema::drop('exam_types');
        $migration = require database_path('migrations/2026_10_27_000009_create_schedule_catalogs.php');
        $migration->up();
        $this->assertDatabaseHas('exam_types',['code' => 'historical_custom']);
        $this->assertDatabaseHas('units',['code' => 'Historical Unit', 'for_exams' => 1]);
        $this->assertDatabaseHas('units',['code' => 'Historical Staff', 'for_users' => 1]);
        $this->assertSame('Historical Unit',$s->fresh()->unit);
        $this->assertSame(1,$s->fresh()->version);
    }
}
