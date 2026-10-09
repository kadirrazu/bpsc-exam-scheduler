<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Designation;
use App\Models\ExamSchedule;
use App\Models\User;
use Database\Seeders\DesignationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SchedulerUiSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DesignationSeeder::class);
    }

    private function staff(UserRole $role = UserRole::Admin): User
    {
        $user = User::factory()->create(['role' => $role, 'unit' => 'IT Section', 'designation_id' => Designation::first()->id]);
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);

        return $user;
    }

    private function userData(array $changes = []): array
    {
        return array_replace(['name' => 'Test Officer', 'email' => 'officer@example.test', 'unit' => 'Cadre (Confidential)', 'designation_id' => Designation::first()->id, 'role' => 'editor', 'is_active' => true, 'password' => 'Abcd12!x', 'password_confirmation' => 'Abcd12!x'], $changes);
    }

    public function test_staff_unit_is_required_validated_saved_updated_filtered_and_audited(): void
    {
        $admin = $this->staff();
        $this->post('/users', $this->userData(['unit' => null]))->assertSessionHasErrors('unit');
        $this->post('/users', $this->userData(['unit' => 'BCS']))->assertSessionHasErrors('unit');
        $this->post('/users', $this->userData())->assertRedirect();
        $user = User::where('email', 'officer@example.test')->firstOrFail();
        $this->assertSame('Cadre (Confidential)', $user->unit);
        $this->assertTrue(Hash::check('Abcd12!x', $user->password));
        $this->get('/users?unit=Cadre%20%28Confidential%29')->assertOk()->assertSee($user->email)->assertDontSee($admin->email);
        $this->put('/users/'.$user->id, $this->userData(['unit' => 'Law Wing', 'password' => '', 'password_confirmation' => '']))->assertRedirect();
        $this->assertSame('Law Wing', $user->fresh()->unit);
        $log = AuditLog::where('action', 'user.updated')->where('subject_id', $user->id)->latest('id')->firstOrFail();
        $this->assertSame('Cadre (Confidential)', $log->details['before']['unit']);
        $this->assertSame('Law Wing', $log->details['after']['unit']);
    }

    public function test_unit_is_organizational_metadata_and_does_not_grant_permission(): void
    {
        $viewer = $this->staff(UserRole::Viewer);
        $this->put('/profile', ['name' => $viewer->name, 'email' => $viewer->email, 'designation_id' => $viewer->designation_id, 'unit' => 'Administration Wing'])->assertSessionHasErrors('unit');
        $this->assertSame('IT Section', $viewer->fresh()->unit);
        $this->get('/users/create')->assertForbidden();
        $token = $viewer->createToken('phone')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.unit', 'IT Section');
        $this->putJson('/api/v1/profile', ['name' => $viewer->name, 'email' => $viewer->email, 'designation_id' => $viewer->designation_id, 'unit' => 'Law Wing'])->assertUnprocessable()->assertJsonValidationErrors('unit');
    }

    public function test_password_minimum_is_eight_and_all_complexity_rules_remain(): void
    {
        $this->staff();
        foreach (['Ab12!xy', 'abcd12!x', 'ABCD12!X', 'Abcdef!x', 'Abcd123x', '12345678'] as $invalid) {
            $this->post('/users', $this->userData(['password' => $invalid, 'password_confirmation' => $invalid]))->assertSessionHasErrors('password');
        }
        $this->post('/users', $this->userData())->assertRedirect();
        $user = User::where('email', 'officer@example.test')->firstOrFail();
        $this->put('/users/'.$user->id, $this->userData(['password' => 'Xyz!123a', 'password_confirmation' => 'Xyz!123a']))->assertRedirect();
        $this->assertTrue(Hash::check('Xyz!123a', $user->fresh()->password));
        $this->actingAs($user)->withSession(['auth_version' => $user->fresh()->auth_version]);
        $this->put('/profile/password', ['current_password' => 'Xyz!123a', 'password' => 'Qrst12!x', 'password_confirmation' => 'Qrst12!x'])->assertRedirect();
        $this->assertTrue(Hash::check('Qrst12!x', $user->fresh()->password));
        $this->assertStringNotContainsString('Qrst12!x', AuditLog::all()->toJson());
    }

    public function test_exam_units_differ_from_staff_units_and_reject_old_or_staff_only_values(): void
    {
        $this->staff(UserRole::Editor);
        $data = ['ministry' => 'BPSC', 'title' => 'Viva', 'post_name' => 'Viva', 'exam_type' => 'viva', 'unit' => 'Cadre (Exam)', 'exam_date' => today()->toDateString(), 'candidate_count' => 20, 'board_count' => 2, 'status' => 'scheduled'];
        foreach (['BCS', 'Departmental', 'Senior Scale', 'IT Section', 'Cadre (Confidential)'] as $unit) {
            $this->post('/schedules', array_replace($data, ['unit' => $unit]))->assertSessionHasErrors('unit');
        }
        $this->post('/schedules', $data)->assertRedirect();
        $this->assertDatabaseHas('exam_schedules', ['unit' => 'Cadre (Exam)']);
        $this->assertCount(22, config('scheduler.units'));
        $this->assertCount(27, config('scheduler.user_units'));
        $schedule = ExamSchedule::firstOrFail();
        $schedule->update(['unit' => 'BCS']);
        $this->get('/schedules/'.$schedule->id.'/edit')->assertOk()->assertSee('এই পুরোনো সূচি');
        $this->assertSame('BCS', $schedule->fresh()->unit);
    }

    public function test_bengali_shell_uses_english_product_type_units_and_original_footer(): void
    {
        $this->get('/login')->assertOk()->assertSee('BPSC Exam Scheduler')->assertDontSee('বিপিএসসি পরীক্ষা সূচি')->assertSee('বাংলাদেশ সরকারী কর্ম কমিশন')->assertSee('Software Developed By:')->assertSee('Software Version: v'.config('scheduler.version'))->assertSee('Bangladesh Public Service Commission (BPSC)')->assertSee('Developer: Md. Abdul Kadir [Programmer]')->assertSee('data-password-toggle', false)->assertSee('bi bi-eye', false)->assertSee('favicon-scheduler.svg');
        $this->staff();
        $this->get('/dashboard')->assertOk()->assertSee('পরীক্ষা ব্যবস্থাপনা')->assertSee('ব্যবহারকারী ব্যবস্থাপনা')->assertDontSee('Administrator');
        $this->get('/profile')->assertOk()->assertSee('Role')->assertSee('Administrator');
        $this->get('/schedules/create')->assertOk()->assertSee('Preliminary (MCQ Type)')->assertSee('Viva')->assertSee('Cadre (Exam)')->assertDontSee('value="BCS"', false)->assertDontSee('ইউনিট ০১');
        $this->get('/users/create')->assertOk()->assertSee('Cadre (Confidential)')->assertSee('Law Wing')->assertSee('data-password-toggle', false);
    }

    public function test_api_options_and_admin_unit_roundtrip_use_english_labels_in_bengali_locale(): void
    {
        $admin = $this->staff();
        $token = $admin->createToken('phone')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->withHeader('Accept-Language', 'bn')->getJson('/api/v1/options')->assertOk()->assertJsonPath('exam_types.preliminary.label', 'Preliminary (MCQ Type)')->assertJsonPath('unit_labels.Unit 01', 'Unit 01')->assertJsonPath('units.21', 'Cadre (Exam)')->assertJsonPath('user_units.26', 'Law Wing');
        $id = $this->postJson('/api/v1/users', $this->userData())->assertCreated()->assertJsonPath('data.unit', 'Cadre (Confidential)')->json('data.id');
        $this->putJson('/api/v1/users/'.$id, $this->userData(['unit' => 'Administration Wing', 'password' => '', 'password_confirmation' => '']))->assertOk()->assertJsonPath('data.unit', 'Administration Wing');
    }
}
