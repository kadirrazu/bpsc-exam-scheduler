<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Designation;
use App\Models\ExamSchedule;
use App\Models\User;
use App\Services\Scheduler\SchedulePdf;
use Carbon\Carbon;
use Database\Seeders\DesignationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withSession(['locale' => 'en']);
        $this->seed(DesignationSeeder::class);
    }

    private function user(UserRole $role = UserRole::Admin): User
    {
        return User::factory()->create(['role' => $role, 'designation_id' => Designation::first()->id, 'preferred_locale' => 'en']);
    }

    private function staff(User $u): static
    {
        return $this->actingAs($u)->withSession(['auth_version' => $u->auth_version]);
    }

    private function payload(array $extra = []): array
    {
        $data = array_replace(['ministry' => 'BPSC', 'title' => '47th Written — English', 'exam_type' => 'written', 'reference' => '47 BCS', 'unit' => 'Unit 01', 'exam_date' => today()->toDateString(), 'start_time' => '10:00', 'end_time' => '13:00', 'candidate_count' => 500, 'center_count' => 5, 'status' => 'scheduled'], $extra);
        $data['post_name'] = $data['title'];
        if ($data['exam_type'] === 'viva') { $data['end_time'] = null; }

        return $data;
    }

    private function schedule(array $extra = []): ExamSchedule
    {
        return ExamSchedule::create($this->payload($extra));
    }

    public function test_guests_cannot_access_backend_and_public_choice_routes_are_gone(): void
    {
        $this->get('/')->assertRedirect('/login');
        foreach (['/dashboard', '/schedules', '/users', '/designations', '/audit-logs', '/schedules/export/xlsx'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->get('/candidate/events/1/sign-in')->assertNotFound();
        $this->get('/choice-events')->assertNotFound();
        $this->get('/register')->assertNotFound();
        $this->getJson('/api/v1/schedules')->assertUnauthorized();
    }

    public function test_each_role_can_login_and_read_schedules(): void
    {
        $s = $this->schedule();
        foreach (UserRole::cases() as $role) {
            $u = $this->user($role);
            $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertRedirect('/dashboard');
            $this->get('/dashboard')->assertOk()->assertSee($s->title);
            $this->get('/schedules/'.$s->id)->assertOk();
            $this->assertNotNull($u->fresh()->last_login_at);
            $this->post('/logout')->assertRedirect();
        }
        $this->assertDatabaseCount('users', 3);
        $this->assertSame(3, AuditLog::where('action', 'auth.login')->count());
    }

    public function test_editor_can_create_update_but_not_delete_or_manage_system(): void
    {
        $u = $this->user(UserRole::Editor);
        $this->staff($u);
        $this->post('/schedules', $this->payload())->assertRedirect();
        $s = ExamSchedule::firstOrFail();
        $this->put('/schedules/'.$s->id, $this->payload(['ministry' => 'BPSC', 'title' => 'Revised Schedule', 'version' => 1]))->assertRedirect();
        $this->assertSame(2, $s->fresh()->version);
        $this->delete('/schedules/'.$s->id, ['confirmation' => 'DELETE', 'version' => 2])->assertForbidden();
        foreach (['/users', '/designations', '/audit-logs'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/designations', ['name' => 'Bypass', 'sort_order' => 1, 'is_active' => 1])->assertForbidden();
        $this->post('/users', [])->assertForbidden();
    }

    public function test_viewer_cannot_mutate_even_with_direct_requests(): void
    {
        $s = $this->schedule();
        $this->staff($this->user(UserRole::Viewer));
        $this->get('/schedules/create')->assertForbidden();
        $this->get('/schedules/'.$s->id.'/edit')->assertForbidden();
        $this->post('/schedules', $this->payload())->assertForbidden();
        $this->put('/schedules/'.$s->id, $this->payload(['version' => 1]))->assertForbidden();
        $this->delete('/schedules/'.$s->id, ['confirmation' => 'DELETE', 'version' => 1])->assertForbidden();
        $this->assertDatabaseCount('exam_schedules', 1);
        $this->get('/schedules')->assertOk()->assertDontSee('Add Exam Schedule');
    }

    public function test_admin_delete_needs_confirmation_preserves_history_and_hides_row(): void
    {
        $s = $this->schedule();
        $this->staff($this->user());
        $this->delete('/schedules/'.$s->id, ['version' => 1])->assertSessionHasErrors('confirmation');
        $this->delete('/schedules/'.$s->id, ['version' => 1, 'confirmation' => 'DELETE'])->assertRedirect();
        $this->assertSoftDeleted($s);
        $this->get('/dashboard')->assertOk()->assertDontSee($s->title);
        $this->assertDatabaseHas('audit_logs', ['action' => 'examschedule.deleted', 'subject_id' => $s->id]);
    }

    public function test_viva_uses_boards_and_other_types_use_centers(): void
    {
        $this->staff($this->user(UserRole::Editor));
        $this->post('/schedules', $this->payload(['exam_type' => 'viva', 'center_count' => null, 'board_count' => 4]))->assertRedirect();
        $s = ExamSchedule::firstOrFail();
        $this->assertNull($s->center_count);
        $this->assertSame(4, $s->board_count);
        $this->post('/schedules', $this->payload(['exam_type' => 'viva']))->assertSessionHasErrors(['board_count', 'center_count']);
        $this->post('/schedules', $this->payload(['board_count' => 2]))->assertSessionHasErrors('board_count');
        $this->put('/schedules/'.$s->id, $this->payload(['version' => 1]))->assertRedirect();
        $this->assertNull($s->fresh()->board_count);
        $this->assertSame(5, $s->fresh()->center_count);
    }

    public function test_optional_times_and_same_day_time_validation(): void
    {
        $this->staff($this->user());
        $this->post('/schedules', $this->payload(['start_time' => null, 'end_time' => null]))->assertRedirect();
        $this->post('/schedules', $this->payload(['start_time' => null, 'end_time' => '15:00']))->assertRedirect();
        $this->post('/schedules', $this->payload(['end_time' => '09:59']))->assertSessionHasErrors('end_time');
        $this->post('/schedules', $this->payload(['start_time' => '25:10']))->assertSessionHasErrors('start_time');
    }

    public function test_invalid_dates_counts_types_units_and_statuses_are_rejected(): void
    {
        $this->staff($this->user());
        foreach (['exam_date' => '2026-02-30', 'candidate_count' => -1, 'center_count' => -1, 'unit' => 'bogus', 'exam_type' => 'bogus', 'status' => 'bogus'] as $key => $value) {
            $this->post('/schedules', $this->payload([$key => $value]))->assertSessionHasErrors($key);
        }
        $this->assertDatabaseCount('exam_schedules', 0);
    }

    public function test_stale_updates_and_deletes_return_conflict(): void
    {
        $s = $this->schedule();
        $this->staff($this->user());
        $this->put('/schedules/'.$s->id, $this->payload(['version' => 1, 'title' => 'Latest']))->assertRedirect();
        $this->put('/schedules/'.$s->id, $this->payload(['version' => 1, 'title' => 'Stale']))->assertConflict();
        $this->delete('/schedules/'.$s->id, ['version' => 1, 'confirmation' => 'DELETE'])->assertConflict();
        $this->assertSame('Latest', $s->fresh()->title);
    }

    public function test_default_week_has_seven_days_and_single_date_overrides_range(): void
    {
        $this->travelTo(Carbon::parse('2026-10-06 23:59:00', 'Asia/Dhaka'));
        $this->schedule(['ministry' => 'BPSC', 'title' => 'Today']);
        $this->schedule(['ministry' => 'BPSC', 'title' => 'Day Seven', 'exam_date' => '2026-10-12']);
        $this->schedule(['ministry' => 'BPSC', 'title' => 'Day Eight', 'exam_date' => '2026-10-13']);
        $this->schedule(['ministry' => 'BPSC', 'title' => 'Past Day', 'exam_date' => '2026-10-05']);
        $this->staff($this->user(UserRole::Viewer))->get('/dashboard')->assertOk()->assertSee('Today')->assertSee('Day Seven')->assertDontSee('Day Eight')->assertDontSee('Past Day');
        $this->get('/schedules?date=2026-10-13&from=2026-10-06&to=2026-10-12')->assertOk()->assertSee('Day Eight')->assertDontSee('Day Seven');
        $this->travelBack();
    }

    public function test_filters_and_summary_are_for_all_matching_rows(): void
    {
        $this->schedule(['ministry' => 'BPSC', 'title' => 'NC Row', 'exam_type' => 'written', 'unit' => 'Unit 02', 'candidate_count' => 200]);
        $this->schedule(['ministry' => 'BPSC', 'title' => 'Other Row', 'status' => 'cancelled']);
        $this->staff($this->user());
        $this->get('/schedules?exam_type=written&unit=Unit%2002&status=scheduled')->assertOk()->assertSee('NC Row')->assertDontSee('Other Row')->assertViewHas('summary', fn ($s) => $s['exams'] === 1 && $s['units'] === 1 && $s['exam_types'] === 1);
        $this->get('/schedules?from=2027-01-01&to=2026-01-01')->assertSessionHasErrors('to');
        $this->get('/schedules?from=2020-01-01&to=2026-01-01')->assertOk();
    }

    public function test_empty_filtered_schedule_and_print_view_render(): void
    {
        $this->staff($this->user(UserRole::Viewer));
        $this->get('/dashboard')->assertOk()->assertSee('No exams match these filters.');
        $this->get('/schedules/export/print')->assertOk()->assertSee('Print Schedule');
    }

    public function test_exports_work_for_viewers_and_xlsx_strings_are_not_formulas(): void
    {
        $s = $this->schedule(['ministry' => 'BPSC', 'title' => '=HYPERLINK("https://example.test")']);
        $this->staff($this->user(UserRole::Viewer));
        $response = $this->get('/schedules/export/xlsx')->assertOk();
        $bytes = $response->getContent();
        $tmp = tempnam(sys_get_temp_dir(), 'scheduler');
        file_put_contents($tmp, $bytes);
        $book = IOFactory::load($tmp);
        unlink($tmp);
        $this->assertSame('s', $book->getActiveSheet()->getCell('D5')->getDataType());
        $this->assertSame($s->title, $book->getActiveSheet()->getCell('D5')->getValue());
        $this->get('/schedules/export/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/schedules/export/dbf')->assertNotFound();
    }

    public function test_designations_include_requested_posts_and_support_crud(): void
    {
        $this->assertDatabaseCount('designations', 20);
        $this->assertDatabaseHas('designations', ['name' => 'Exam Controller']);
        $this->staff($this->user())->get('/designations')->assertOk()->assertSee('Steno Typist');
        $this->post('/designations', ['name' => 'New Post', 'sort_order' => 21, 'is_active' => 1])->assertRedirect();
        $d = Designation::where('name', 'New Post')->firstOrFail();
        $this->put('/designations/'.$d->id, ['name' => 'Updated Post', 'sort_order' => 22, 'is_active' => 0])->assertRedirect();
        $this->delete('/designations/'.$d->id, ['confirmation' => 'DELETE'])->assertRedirect();
        $this->assertDatabaseMissing('designations', ['id' => $d->id]);
    }

    public function test_used_designation_cannot_be_deleted(): void
    {
        $u = $this->user();
        $this->staff($u);
        $this->delete('/designations/'.$u->designation_id, ['confirmation' => 'DELETE'])->assertSessionHasErrors('designation');
        $this->assertDatabaseHas('designations', ['id' => $u->designation_id]);
    }

    public function test_user_roles_crud_and_no_self_demotion_or_delete(): void
    {
        $u = $this->user();
        $this->staff($u);
        $data = ['name' => 'Reader', 'unit' => 'IT Section', 'email' => 'reader@example.test', 'designation_id' => $u->designation_id, 'role' => 'viewer', 'is_active' => 1, 'password' => 'Strong-Password12!', 'password_confirmation' => 'Strong-Password12!'];
        $this->post('/users', $data)->assertRedirect();
        $reader = User::where('email', $data['email'])->firstOrFail();
        $this->assertSame(UserRole::Viewer, $reader->role);
        $this->put('/users/'.$u->id, ['name' => $u->name, 'email' => $u->email, 'designation_id' => $u->designation_id, 'role' => 'editor', 'unit' => 'IT Section', 'is_active' => 1])->assertSessionHasErrors('role');
        $this->delete('/users/'.$u->id, ['confirmation' => 'DELETE'])->assertSessionHasErrors('confirmation');
        $this->delete('/users/'.$reader->id, ['confirmation' => 'DELETE'])->assertRedirect();
        $this->assertSoftDeleted($reader);
    }

    public function test_account_changes_revoke_existing_sessions_and_tokens(): void
    {
        $u = $this->user(UserRole::Editor);
        $token = $u->createToken('test', ['*'], now()->addDay())->plainTextToken;
        $this->staff($u);
        $u->forceFill(['is_active' => false])->save();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertSame(0, $u->tokens()->count());
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_login_failure_and_inactive_accounts_are_denied_and_audited(): void
    {
        $u = $this->user();
        $u->update(['is_active' => false]);
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.failed']);
    }

    public function test_audits_include_before_after_but_never_password_or_token(): void
    {
        $u = $this->user();
        $this->staff($u);
        $s = $this->schedule();
        $this->put('/schedules/'.$s->id, $this->payload(['version' => 1, 'title' => 'After']))->assertRedirect();
        $log = AuditLog::where('action', 'examschedule.updated')->latest('id')->firstOrFail();
        $this->assertSame($s->title, $log->details['before']['title']);
        $this->assertSame('After', $log->details['after']['title']);
        $this->put('/profile/password', ['current_password' => 'password', 'password' => 'Another-Strong12!', 'password_confirmation' => 'Another-Strong12!'])->assertRedirect();
        $json = AuditLog::all()->toJson();
        $this->assertStringNotContainsString('Another-Strong12!', $json);
        $this->assertStringNotContainsString($u->password, $json);
        $this->get('/audit-logs')->assertOk();
        $this->staff($this->user(UserRole::Viewer))->get('/audit-logs')->assertForbidden();
    }

    public function test_audit_records_cannot_be_updated_or_deleted_through_models(): void
    {
        $log = AuditLog::firstOrFail();
        $this->expectException(\LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_profile_cannot_escalate_role_and_strong_password_is_required(): void
    {
        $u = $this->user(UserRole::Viewer);
        $this->staff($u);
        $this->put('/profile', ['name' => 'Viewer', 'email' => $u->email, 'designation_id' => $u->designation_id, 'role' => 'admin'])->assertSessionHasErrors('role');
        $this->put('/profile/password', ['current_password' => 'password', 'password' => 'weak', 'password_confirmation' => 'weak'])->assertSessionHasErrors('password');
        $this->assertSame(UserRole::Viewer, $u->fresh()->role);
    }

    public function test_security_headers_are_present(): void
    {
        $this->get('/login')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_all_entry_and_reused_staff_forms_render_for_authorized_users(): void
    {
        $u = $this->user();
        $s = $this->schedule();
        $this->staff($u);
        foreach (['/schedules/create', '/schedules/'.$s->id.'/edit', '/designations/create', '/designations/'.$u->designation_id.'/edit', '/users/create', '/users/'.$u->id.'/edit', '/profile', '/profile/password'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_bangla_title_pdf_uses_font_shaping_and_escapes_html(): void
    {
        $this->schedule(['ministry' => 'BPSC', 'title' => 'বাংলাদেশ সরকারী কর্ম কমিশন <script>alert(1)</script>']);
        $this->staff($this->user());
        $pdf = $this->get('/schedules/export/pdf')->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $runs = app(SchedulePdf::class)->fontRuns('<p>Test বাংলাদেশ &lt;script&gt;</p>');
        $this->assertStringContainsString('font-family:nikosh', $runs);
        $this->assertStringNotContainsString('<script>', $runs);
    }
}
