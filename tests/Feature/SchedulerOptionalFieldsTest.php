<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Designation;
use App\Models\ExamSchedule;
use App\Models\User;
use Database\Seeders\DesignationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SchedulerOptionalFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DesignationSeeder::class);
    }

    private function staff(): User
    {
        $user = User::factory()->create(['role' => UserRole::Editor, 'designation_id' => Designation::first()->id, 'unit' => 'IT Section']);
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);

        return $user;
    }

    private function data(array $extra = []): array
    {
        return array_replace(['ministry' => 'BPSC', 'post_name' => 'সহকারী পরিচালক', 'exam_type' => 'nc_written', 'unit' => 'Unit 01', 'exam_date' => today()->toDateString(), 'center_count' => 2, 'status' => 'proposed'], $extra);
    }

    public function test_entry_has_no_title_and_accepts_omitted_optional_fields(): void
    {
        $this->staff();
        $this->get('/schedules/create')->assertOk()->assertDontSee('name="title"', false)->assertSee('পদের কোড (ঐচ্ছিক)')->assertSee('পরীক্ষার্থীর সংখ্যা (ঐচ্ছিক)')->assertSee('মন্তব্য (ঐচ্ছিক)')->assertSee('Proposed');
        $this->post('/schedules', $this->data())->assertRedirect();
        $schedule = ExamSchedule::firstOrFail();
        $this->assertNull($schedule->candidate_count);
        $this->assertNull($schedule->reference);
        $this->assertNull($schedule->notes);
        $this->assertSame('proposed', $schedule->status);
        $this->assertStringContainsString('NC Written', $schedule->title);
        $this->assertStringContainsString('Unit 01', $schedule->title);
        $this->get('/schedules?status=proposed')->assertOk()->assertSee('status-proposed')->assertSee('Proposed')->assertSee('পরীক্ষার্থী সংখ্যা দেওয়া হয়নি');
        $this->get('/schedules/export/print')->assertOk()->assertSee('background-color:#FFF4CC', false)->assertSee('—');
        $log = AuditLog::where('action', 'examschedule.created')->firstOrFail();
        $this->assertNull($log->details['after']['candidate_count'] ?? null);
    }

    public function test_clear_count_preserves_null_and_supplied_invalid_counts_are_rejected(): void
    {
        $this->staff();
        $this->post('/schedules', $this->data(['candidate_count' => '৫০', 'reference' => 'পদ-০০১', 'notes' => 'প্রস্তাবিত সময়']))->assertRedirect();
        $schedule = ExamSchedule::firstOrFail();
        $this->put('/schedules/'.$schedule->id, $this->data(['version' => 1, 'candidate_count' => '', 'reference' => '', 'notes' => '']))->assertRedirect();
        $this->assertNull($schedule->fresh()->candidate_count);
        $this->assertNull($schedule->fresh()->reference);
        $this->assertNull($schedule->fresh()->notes);
        foreach ([-1, '১.৫', 'abc', 10000001] as $invalid) {
            $this->post('/schedules', $this->data(['candidate_count' => $invalid]))->assertSessionHasErrors('candidate_count');
        }
        $this->post('/schedules', $this->data(['status' => 'unknown']))->assertSessionHasErrors('status');
    }

    public function test_api_optional_count_and_summary_export_status_colors_roundtrip(): void
    {
        $user = $this->staff();
        $token = $user->createToken('Android')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->withHeader('Accept-Language', 'en');
        $this->postJson('/api/v1/schedules', $this->data())->assertCreated()->assertJsonPath('data.candidate_count', null)->assertJsonPath('data.status', 'proposed');
        $this->postJson('/api/v1/schedules', $this->data(['candidate_count' => 0, 'status' => 'scheduled']))->assertCreated()->assertJsonPath('data.candidate_count', 0);
        $this->postJson('/api/v1/schedules', $this->data(['candidate_count' => 50, 'status' => 'completed']))->assertCreated();
        $this->getJson('/api/v1/schedules')->assertOk()->assertJsonPath('summary.candidates_unspecified', 1)->assertJsonPath('summary.candidates', 50);
        $this->getJson('/api/v1/options')->assertOk()->assertJsonPath('statuses.proposed', 'Proposed');
        $bytes = $this->get('/api/v1/schedules/export/xlsx')->assertOk()->getContent();
        $tmp = tempnam(sys_get_temp_dir(), 'scheduler-optional');
        try {
            file_put_contents($tmp, $bytes);
            $book = IOFactory::load($tmp);
            $sheet = $book->getActiveSheet();
            $this->assertContains($sheet->getCell('K5')->getValue(), [null, '']);
            $this->assertSame(0, $sheet->getCell('K6')->getValue());
            $this->assertSame('Post Code', $sheet->getCell('E4')->getValue());
            $this->assertSame('Notes/Remarks', $sheet->getCell('O4')->getValue());
            foreach ([5 => 'FFF4CC', 6 => 'E6F0FF', 7 => 'E0F2E5'] as $row => $color) {
                $this->assertSame($color, $sheet->getStyle('N'.$row)->getFill()->getStartColor()->getRGB());
            }
            $book->disconnectWorksheets();
        } finally {
            unlink($tmp);
        }
        $this->get('/api/v1/schedules/export/pdf')->assertOk();
    }

    public function test_post_name_is_mandatory_and_ministry_roundtrips_search_exports_and_audit(): void
    {
        $user = $this->staff();
        $this->post('/schedules', $this->data(['ministry' => 'BPSC', 'post_name' => '']))->assertSessionHasErrors('post_name');
        $this->post('/schedules', $this->data(['ministry' => 'BPSC', 'post_name' => str_repeat('x', 201)]))->assertSessionHasErrors('post_name');
        $this->post('/schedules', $this->data(['ministry' => str_repeat('x', 201)]))->assertSessionHasErrors('ministry');
        $data = $this->data(['ministry' => 'BPSC', 'post_name' => 'সহকারী পরিচালক', 'reference' => '০০১', 'ministry' => 'জনপ্রশাসন মন্ত্রণালয় / Organization']);
        $this->post('/schedules', $data)->assertRedirect();
        $schedule = ExamSchedule::firstOrFail();
        $this->assertSame($data['post_name'], $schedule->post_name);
        $this->assertSame($data['ministry'], $schedule->ministry);
        $this->get('/schedules?search='.urlencode('জনপ্রশাসন'))->assertOk()->assertSee($data['post_name'])->assertSee($data['ministry']);
        $this->get('/schedules/'.$schedule->id)->assertOk()->assertSee('মন্ত্রণালয়/সংস্থা')->assertSee($data['ministry']);
        $log = AuditLog::where('action', 'examschedule.created')->firstOrFail();
        $this->assertSame($data['post_name'], $log->details['after']['post_name']);
        $this->assertSame($data['ministry'], $log->details['after']['ministry']);
        $token = $user->createToken('phone')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->withHeader('Accept-Language', 'en')->getJson('/api/v1/schedules/'.$schedule->id)->assertOk()->assertJsonPath('data.post_name', $data['post_name'])->assertJsonPath('data.ministry', $data['ministry']);
        $bytes = $this->get('/api/v1/schedules/export/xlsx')->assertOk()->getContent();
        $tmp = tempnam(sys_get_temp_dir(), 'scheduler-ministry');
        try {
            file_put_contents($tmp, $bytes);
            $book = IOFactory::load($tmp);
            $sheet = $book->getActiveSheet();
            $this->assertSame('Post Name', $sheet->getCell('D4')->getValue());
            $this->assertSame($data['post_name'], $sheet->getCell('D5')->getValue());
            $this->assertSame('Ministry/Organization', $sheet->getCell('P4')->getValue());
            $this->assertSame($data['ministry'], $sheet->getCell('P5')->getValue());
            $book->disconnectWorksheets();
        } finally {
            unlink($tmp);
        }
    }
}
