<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Designation;
use App\Models\ExamSchedule;
use App\Models\User;
use App\Services\Scheduler\SchedulePdf;
use App\Services\Scheduler\ScheduleWriter;
use Database\Seeders\DesignationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SchedulerLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DesignationSeeder::class);
    }

    private function user(UserRole $role = UserRole::Admin, string $locale = 'bn'): User
    {
        return User::factory()->create(['role' => $role, 'designation_id' => Designation::first()->id, 'preferred_locale' => $locale]);
    }

    private function staff(User $u): static
    {
        return $this->actingAs($u)->withSession(['auth_version' => $u->auth_version]);
    }

    private function data(array $more = []): array
    {
        $data = array_replace(['ministry' => 'BPSC', 'title' => '৪৭তম বিসিএস লিখিত — বাংলা', 'reference' => '৪৭ বিসিএস', 'exam_type' => 'written', 'unit' => 'Unit 01', 'exam_date' => today()->toDateString(), 'candidate_count' => '৫০০', 'center_count' => '৫', 'status' => 'scheduled', 'advertisement_number' => 'বিজ্ঞপ্তি-০০১/২০২৬', 'advertisement_year' => '২০২৬', 'notes' => 'বাংলা এবং English দুটোই লেখা যায়।'], $more);
        $data['post_name'] = $data['title'];

        return $data;
    }

    public function test_bengali_default_login_and_guest_english_switch(): void
    {
        $this->get('/login')->assertOk()->assertSee('পাসওয়ার্ড')->assertSee('বাংলাদেশ সরকারী কর্ম কমিশন')->assertSee('lang="bn"', false);
        $this->post('/language', ['locale' => 'en', 'return_to' => '/login'])->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Password')->assertSee('lang="en"', false);
        $this->post('/language', ['locale' => 'fr'])->assertSessionHasErrors('locale');
        $this->post('/language', ['locale' => 'bn', 'return_to' => '//evil.example'])->assertSessionHasErrors('return_to');
    }

    public function test_authenticated_language_preference_persists_without_changing_role_or_credentials(): void
    {
        $u = $this->user(UserRole::Viewer);
        $token = $u->createToken('phone')->plainTextToken;
        $this->staff($u);
        $this->get('/dashboard')->assertOk()->assertSee('পরীক্ষার সূচি');
        $this->post('/language', ['locale' => 'en', 'return_to' => '/dashboard', 'role' => 'admin'])->assertRedirect('/dashboard');
        $this->assertSame('en', $u->fresh()->preferred_locale);
        $this->assertSame(UserRole::Viewer, $u->fresh()->role);
        $this->assertSame(1, $u->fresh()->auth_version);
        $this->assertSame(1, $u->tokens()->count());
        $this->get('/dashboard')->assertOk()->assertSee("This Week&#039;s Schedule", false);
        $this->post('/logout');
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertRedirect();
        $this->get('/dashboard')->assertOk()->assertSee('lang="en"', false);
    }

    public function test_bengali_text_advertisement_and_digits_round_trip_through_web_api_and_audit(): void
    {
        $u = $this->user(UserRole::Editor);
        $this->staff($u);
        $this->post('/schedules', $this->data())->assertRedirect();
        $s = ExamSchedule::firstOrFail();
        $this->assertSame('বিজ্ঞপ্তি-০০১/২০২৬', $s->advertisement_number);
        $this->assertSame(2026, $s->advertisement_year);
        $this->assertSame(500, $s->candidate_count);
        $this->assertSame(5, $s->center_count);
        $this->get('/schedules/'.$s->id)->assertOk()->assertSee('বিজ্ঞপ্তির সাল')->assertSee('২০২৬')->assertSee($s->title);
        $this->get('/schedules?search='.urlencode('বিজ্ঞপ্তি-০০১'))->assertOk()->assertSee($s->title);
        $log = AuditLog::where('action', 'examschedule.created')->where('subject_id', $s->id)->firstOrFail();
        $this->assertSame(2026, (int) $log->details['after']['advertisement_year']);
        $this->assertSame($s->advertisement_number, $log->details['after']['advertisement_number']);
        $token = $u->createToken('Android')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/schedules/'.$s->id)->assertOk()->assertJsonPath('data.advertisement_year', 2026)->assertJsonPath('data.title', $s->title);
    }

    public function test_optional_advertisement_fields_and_invalid_year(): void
    {
        $this->staff($this->user());
        $this->post('/schedules', $this->data(['advertisement_number' => null, 'advertisement_year' => null]))->assertRedirect();
        $this->assertNull(ExamSchedule::first()->advertisement_year);
        $this->post('/schedules', $this->data(['advertisement_year' => '২০এ৬']))->assertSessionHasErrors('advertisement_year');
        $this->post('/schedules', $this->data(['advertisement_year' => '১৮৯৯']))->assertSessionHasErrors('advertisement_year');
    }

    public function test_bengali_validation_messages_and_entry_forms(): void
    {
        $this->staff($this->user());
        $this->post('/schedules', [])->assertSessionHasErrors(['post_name', 'exam_date']);
        $this->assertStringContainsString('পদের নাম', session('errors')->first('post_name'));
        foreach (['/schedules/create', '/users/create', '/designations/create', '/profile/password', '/audit-logs'] as $url) {
            $this->get($url)->assertOk()->assertSee('lang="bn"', false);
        }
        $this->get('/schedules/create')->assertSee('বিজ্ঞপ্তি নম্বর (ঐচ্ছিক)')->assertSee('বিজ্ঞপ্তির সাল (ঐচ্ছিক)');
    }

    public function test_language_switch_does_not_translate_saved_text_or_unit_codes(): void
    {
        $u = $this->user();
        $this->staff($u);
        $this->post('/schedules', $this->data());
        $s = ExamSchedule::firstOrFail();
        $this->post('/language', ['locale' => 'en']);
        $this->get('/schedules/'.$s->id)->assertOk()->assertSee('Advertisement Number')->assertSee($s->title);
        $this->assertSame('Unit 01', $s->fresh()->unit);
        $this->assertSame('৪৭তম বিসিএস লিখিত — বাংলা', $s->fresh()->title);
    }

    public function test_report_generation_and_view_audits_include_actor_filters_and_row_count(): void
    {
        $u = $this->user(UserRole::Viewer);
        app(ScheduleWriter::class)->create($this->data(['candidate_count' => 500, 'center_count' => 5, 'advertisement_year' => 2026]), $u);
        $this->staff($u);
        $this->get('/schedules?date='.today()->toDateString())->assertOk();
        $view = AuditLog::where('action', 'report.viewed')->latest('id')->firstOrFail();
        $this->assertSame($u->id, $view->actor_id);
        $this->assertSame('exam_schedule_list', $view->details['report']);
        $this->assertSame(today()->toDateString(), $view->details['filters']['date']);
        foreach (['pdf', 'xlsx'] as $format) {
            $response = $this->get('/schedules/export/'.$format.'?date='.today()->toDateString())->assertOk();
            $log = AuditLog::where('action', 'report.generated')->latest('id')->firstOrFail();
            $this->assertSame($u->id, $log->actor_id);
            $this->assertSame($format, $log->details['format']);
            $this->assertSame(1, $log->details['row_count']);
            $this->assertSame('bn', $log->details['locale']);
            $this->assertStringEndsWith('.'.$format, $log->details['filename']);
            if ($format === 'xlsx') {
                $tmp = tempnam(sys_get_temp_dir(), 'bn-xlsx');
                file_put_contents($tmp, $response->getContent());
                $book = IOFactory::load($tmp);
                unlink($tmp);
                $this->assertSame('বিজ্ঞপ্তি নম্বর', $book->getActiveSheet()->getCell('F4')->getValue());
                $this->assertSame('বিজ্ঞপ্তি-০০১/২০২৬', $book->getActiveSheet()->getCell('F5')->getValue());
                $this->assertSame(2026, $book->getActiveSheet()->getCell('G5')->getValue());
            }
        }
    }

    public function test_failed_generation_has_no_success_audit(): void
    {
        $this->staff($this->user());
        $this->mock(SchedulePdf::class, fn ($m) => $m->shouldReceive('render')->once()->andThrow(new \RuntimeException('Generation failed')));
        $before = AuditLog::where('action', 'report.generated')->count();
        $this->get('/schedules/export/pdf')->assertStatus(500);
        $this->assertSame($before, AuditLog::where('action', 'report.generated')->count());
    }

    public function test_print_view_and_signed_print_request_are_audited(): void
    {
        $u = $this->user(UserRole::Viewer);
        $this->staff($u);
        $response = $this->get('/schedules/export/print')->assertOk()->assertSee('সূচি প্রিন্ট করুন');
        preg_match('/data-report-token="([^"]+)"/', $response->getContent(), $match);
        $token = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
        $this->postJson('/schedules/print-audit', ['report_token' => $token])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.print_requested', 'actor_id' => $u->id]);
        $this->postJson('/schedules/print-audit', ['report_token' => 'forged'])->assertUnprocessable();
        $this->staff($this->user(UserRole::Viewer))->postJson('/schedules/print-audit', ['report_token' => $token])->assertUnprocessable();
    }

    public function test_api_language_header_and_preference_preserve_machine_codes(): void
    {
        $u = $this->user(UserRole::Viewer);
        $token = $u->createToken('android')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->withHeader('Accept-Language', 'en')->getJson('/api/v1/options')->assertOk()->assertJsonPath('exam_types.written.label', 'Written')->assertJsonPath('units.0', 'Unit 01');
        $this->app['auth']->forgetGuards();
        $this->withHeader('Accept-Language', 'bn-BD')->getJson('/api/v1/options')->assertOk()->assertJsonPath('exam_types.written.label', 'Written')->assertJsonPath('unit_labels.Unit 01', 'Unit 01');
        $this->app['auth']->forgetGuards();
        $this->putJson('/api/v1/profile', ['name' => $u->name, 'email' => $u->email, 'designation_id' => $u->designation_id, 'preferred_locale' => 'en'])->assertOk();
        $this->assertSame('en', $u->fresh()->preferred_locale);
    }
}
