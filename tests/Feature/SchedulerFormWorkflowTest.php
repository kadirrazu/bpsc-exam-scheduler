<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Designation;
use App\Models\ExamSchedule;
use App\Models\User;
use Database\Seeders\DesignationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerFormWorkflowTest extends TestCase
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
        return User::factory()->create(['role' => UserRole::Editor, 'unit' => 'IT Section', 'preferred_locale' => 'en', 'designation_id' => Designation::first()->id]);
    }

    private function data(array $changes = []): array
    {
        return array_replace(['unit' => 'Unit 01', 'exam_type' => 'nc_written', 'post_name' => 'Assistant Director', 'ministry' => 'Ministry / Organization', 'exam_date' => today()->toDateString(), 'status' => 'scheduled'], $changes);
    }

    public function test_add_and_edit_follow_the_exact_field_order_and_mandatory_labels(): void
    {
        $user = $this->user();
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
        $this->post('/schedules', $this->data())->assertRedirect();
        $schedule = ExamSchedule::firstOrFail();
        foreach (['/schedules/create', '/schedules/'.$schedule->id.'/edit'] as $url) {
            $response = $this->get($url)->assertOk()->assertSee('Number of Exam Centers (Non-Viva) (Optional)')->assertSee('Ministry/Organization')->assertDontSee('Ministry/Organization (Optional)')->assertDontSee('name="title"', false);
            preg_match_all('/<(?:input|select|textarea)\b[^>]*\bid="([^"]+)"[^>]*>/s', $response->getContent(), $matches);
            $ids = array_values(array_filter($matches[1], fn ($id) => $id !== 'board_count'));
            $this->assertSame(['unit', 'exam_type', 'reference', 'post_name', 'ministry', 'advertisement_number', 'advertisement_year', 'exam_date', 'start_time', 'end_time', 'candidate_count', 'center_count', 'status', 'notes'], $ids);
        }
    }

    public function test_ministry_is_required_while_non_viva_centers_can_be_omitted_cleared_or_zero(): void
    {
        $user = $this->user();
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
        $this->post('/schedules', $this->data(['ministry' => '']))->assertSessionHasErrors('ministry');
        $this->post('/schedules', $this->data())->assertRedirect();
        $schedule = ExamSchedule::firstOrFail();
        $this->assertNull($schedule->center_count);
        $this->put('/schedules/'.$schedule->id, $this->data(['version' => 1, 'center_count' => 0]))->assertRedirect();
        $this->assertSame(0, $schedule->fresh()->center_count);
        $this->put('/schedules/'.$schedule->id, $this->data(['version' => 2, 'center_count' => '']))->assertRedirect();
        $this->assertNull($schedule->fresh()->center_count);
        $this->put('/schedules/'.$schedule->id, $this->data(['version' => 3, 'ministry' => '']))->assertSessionHasErrors('ministry');
        $this->assertSame('Ministry / Organization', $schedule->fresh()->ministry);
    }

    public function test_api_enforces_conditional_counts_for_every_exam_type(): void
    {
        $user = $this->user();
        $this->withToken($user->createToken('phone')->plainTextToken);
        foreach (config('scheduler.types') as $type => $config) {
            $data = $this->data(['exam_type' => $type]);
            if ($config['viva']) {
                $this->postJson('/api/v1/schedules', $data)->assertUnprocessable()->assertJsonValidationErrors('board_count');
                $this->postJson('/api/v1/schedules', $data + ['board_count' => 2])->assertCreated()->assertJsonPath('data.board_count', 2)->assertJsonPath('data.center_count', null);
                $this->postJson('/api/v1/schedules', $data + ['board_count' => 2, 'center_count' => 1])->assertUnprocessable()->assertJsonValidationErrors('center_count');
            } else {
                $this->postJson('/api/v1/schedules', $data)->assertCreated()->assertJsonPath('data.center_count', null)->assertJsonPath('data.board_count', null);
                $this->postJson('/api/v1/schedules', $data + ['board_count' => 1])->assertUnprocessable()->assertJsonValidationErrors('board_count');
            }
        }
        $this->postJson('/api/v1/schedules', $this->data(['ministry' => null]))->assertUnprocessable()->assertJsonValidationErrors('ministry');
        $this->postJson('/api/v1/schedules', $this->data(['center_count' => -1]))->assertUnprocessable()->assertJsonValidationErrors('center_count');
    }
}
