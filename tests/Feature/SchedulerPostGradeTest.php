<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Designation;
use App\Models\ExamSchedule;
use App\Models\User;
use Database\Seeders\DesignationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SchedulerPostGradeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DesignationSeeder::class);
    }

    private function user(): User
    {
        return User::factory()->create(['role' => UserRole::Editor, 'unit' => 'IT Section', 'designation_id' => Designation::first()->id]);
    }

    private function data(array $changes = []): array
    {
        return array_replace(['post_name' => 'Assistant Director', 'ministry' => 'BPSC', 'unit' => 'Unit 01', 'exam_type' => 'nc_written', 'exam_date' => today()->toDateString(), 'status' => 'scheduled'], $changes);
    }

    public function test_grade_accepts_bengali_digits_and_is_numeric_in_api_audits_and_exports(): void
    {
        $user = $this->user();
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
        $this->post('/schedules', $this->data(['post_grade' => '৯']))->assertRedirect();
        $schedule = ExamSchedule::firstOrFail();
        $this->assertSame(9, $schedule->post_grade);
        $this->get('/schedules/'.$schedule->id)->assertOk()->assertSee('পদের গ্রেড')->assertSee('৯');
        $log = AuditLog::where('action', 'examschedule.created')->where('subject_id', $schedule->id)->firstOrFail();
        $this->assertSame(9, (int) $log->details['after']['post_grade']);
        $token = $user->createToken('phone')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->withHeader('Accept-Language', 'en')->getJson('/api/v1/schedules/'.$schedule->id)->assertOk()->assertJsonPath('data.post_grade', 9);
        $this->putJson('/api/v1/schedules/'.$schedule->id, $this->data(['post_grade' => 10, 'version' => 1]))->assertOk()->assertJsonPath('data.post_grade', 10);
        $response = $this->get('/api/v1/schedules/export/xlsx')->assertOk();
        $tmp = tempnam(sys_get_temp_dir(), 'scheduler-grade');
        try {
            file_put_contents($tmp, $response->getContent());
            $book = IOFactory::load($tmp);
            $sheet = $book->getActiveSheet();
            $this->assertSame('Post Grade', $sheet->getCell('Q4')->getValue());
            $this->assertSame(10, $sheet->getCell('Q5')->getValue());
            $this->assertSame('n', $sheet->getCell('Q5')->getDataType());
            $book->disconnectWorksheets();
        } finally {
            unlink($tmp);
        }
        $this->get('/api/v1/schedules/export/pdf')->assertOk();
    }

    public function test_grade_is_optional_and_invalid_or_noninteger_values_are_rejected(): void
    {
        $user = $this->user();
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
        $this->post('/schedules', $this->data())->assertRedirect();
        $schedule = ExamSchedule::firstOrFail();
        $this->assertNull($schedule->post_grade);
        foreach (['9.5', 'Grade 9', -1, 0, 65536] as $invalid) {
            $this->post('/schedules', $this->data(['post_grade' => $invalid]))->assertSessionHasErrors('post_grade');
        }
        $this->put('/schedules/'.$schedule->id, $this->data(['post_grade' => '১০', 'version' => 1]))->assertRedirect();
        $this->assertSame(10, $schedule->fresh()->post_grade);
        $this->put('/schedules/'.$schedule->id, $this->data(['post_grade' => '', 'version' => 2]))->assertRedirect();
        $this->assertNull($schedule->fresh()->post_grade);
    }

    public function test_console_locale_is_english_and_web_locale_still_defaults_to_bengali(): void
    {
        $this->assertSame('en', config('app.locale'));
        $this->assertSame('en', app()->getLocale());
        $this->assertStringContainsString('required', __('validation.required', ['attribute' => 'Name']));
        Artisan::call('scheduler:create-admin', ['--no-interaction' => true]);
        $output = Artisan::output();
        $this->assertStringNotContainsString('প্রয়োজন', $output);
        $this->assertMatchesRegularExpression('/[A-Za-z]/', $output);
        $this->get('/login')->assertOk()->assertSee('lang="bn"', false)->assertSee('পাসওয়ার্ড');
    }
}
