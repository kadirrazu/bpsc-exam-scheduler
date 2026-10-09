<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Designation;
use App\Models\ExamSchedule;
use App\Models\User;
use App\Services\Scheduler\Audit;
use Carbon\Carbon;
use Database\Seeders\DesignationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerAuditIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DesignationSeeder::class);
    }

    private function user(UserRole $role = UserRole::Admin): User
    {
        return User::factory()->create(['name' => 'Audit Officer', 'role' => $role, 'unit' => 'IT Section', 'designation_id' => Designation::where('name', 'Secretary')->firstOrFail()->id]);
    }

    private function data(array $changes = []): array
    {
        return array_replace(['ministry' => 'BPSC', 'post_name' => 'Assistant Director', 'exam_type' => 'written', 'unit' => 'Unit 01', 'exam_date' => today()->toDateString(), 'center_count' => 2, 'status' => 'proposed'], $changes);
    }

    public function test_bengali_ui_translates_unit_heading_and_keeps_reference_values_in_english(): void
    {
        $user = $this->user();
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
        $this->get('/users/create')->assertOk()->assertSee('Designation')->assertSee('Role')->assertSee('ইউনিট')->assertSee('Secretary')->assertSee('Administrator')->assertSee('Editor')->assertSee('Viewer')->assertSee('IT Section')->assertDontSee('সচিব')->assertDontSee('সম্পাদক')->assertDontSee('দর্শক');
        $this->get('/users')->assertOk()->assertSee('Status')->assertSee('Active')->assertSee('Secretary');
        $this->get('/schedules/create')->assertOk()->assertSee('ইউনিট')->assertSee('Status')->assertSee('Proposed')->assertSee('Scheduled')->assertSee('Completed');
        $this->assertSame('Administrator', UserRole::Admin->label());
        $token = $user->createToken('phone')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->withHeader('Accept-Language', 'bn')->getJson('/api/v1/options')->assertOk()->assertJsonPath('roles.0.label', 'Administrator')->assertJsonPath('roles.1.label', 'Editor')->assertJsonPath('roles.2.label', 'Viewer')->assertJsonPath('statuses.proposed', 'Proposed');
    }

    public function test_web_actions_record_actor_action_time_and_source_ip_without_trusting_spoofed_headers(): void
    {
        Carbon::setTestNow('2026-10-07 09:30:00');
        try {
            $user = $this->user();
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.27'])->withHeader('X-Forwarded-For', '198.51.100.90');
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
            $this->post('/schedules', $this->data())->assertRedirect();
            $schedule = ExamSchedule::firstOrFail();
            $this->put('/schedules/'.$schedule->id, $this->data(['version' => 1, 'status' => 'scheduled']))->assertRedirect();
            $this->get('/schedules/'.$schedule->id)->assertOk();
            $this->get('/schedules/export/xlsx')->assertOk();
            $this->delete('/schedules/'.$schedule->id, ['confirmation' => 'DELETE', 'version' => 2])->assertRedirect();
            $this->post('/logout')->assertRedirect();
            foreach (['auth.login', 'examschedule.created', 'examschedule.updated', 'report.viewed', 'report.generated', 'examschedule.deleted', 'auth.logout'] as $action) {
                $log = AuditLog::where('action', $action)->latest('id')->firstOrFail();
                $this->assertSame($user->id, $log->actor_id, $action);
                $this->assertSame('Audit Officer', $log->actor_name, $action);
                $this->assertSame($action, $log->action);
                $this->assertSame('203.0.113.27', $log->ip_address, $action);
                $this->assertSame('2026-10-07 09:30:00', $log->created_at->format('Y-m-d H:i:s'), $action);
                $this->assertSame('web', $log->channel, $action);
            }
            $this->assertStringNotContainsString('password', AuditLog::where('action', 'auth.login')->first()->details ? json_encode(AuditLog::where('action', 'auth.login')->first()->details) : '');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_api_ipv6_actor_audit_and_admin_ip_filter_are_enforced(): void
    {
        $user = $this->user();
        $token = $user->createToken('phone')->plainTextToken;
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::27'])->withToken($token);
        $id = $this->postJson('/api/v1/schedules', $this->data())->assertCreated()->json('data.id');
        $log = AuditLog::where('action', 'examschedule.created')->where('subject_id', $id)->firstOrFail();
        $this->assertSame($user->id, $log->actor_id);
        $this->assertSame('2001:db8::27', $log->ip_address);
        $this->assertSame('api', $log->channel);
        $this->assertNotNull($log->created_at);
        $response = $this->getJson('/api/v1/audit-logs?ip_address=2001%3Adb8%3A%3A27')->assertOk();
        $this->assertNotEmpty($response->json('data'));
        foreach ($response->json('data') as $record) {
            $this->assertSame('2001:db8::27', $record['ip_address']);
        }
        $this->getJson('/api/v1/audit-logs?ip_address=not-an-ip')->assertUnprocessable()->assertJsonValidationErrors('ip_address');
        $viewer = $this->user(UserRole::Viewer);
        $this->app['auth']->forgetGuards();
        $this->withToken($viewer->createToken('viewer')->plainTextToken)->getJson('/api/v1/audit-logs?ip_address=2001%3Adb8%3A%3A27')->assertForbidden();
    }

    public function test_admin_web_filter_shows_who_what_when_ip_and_console_has_no_fake_ip(): void
    {
        $user = $this->user();
        app(Audit::class)->record('console.test', actor: $user);
        $this->assertNull(AuditLog::where('action', 'console.test')->firstOrFail()->ip_address);
        foreach (['203.0.113.10', '203.0.113.11'] as $ip) {
            AuditLog::create(['actor_id' => $user->id, 'actor_name' => 'Audit Officer', 'action' => 'request.view', 'channel' => 'web', 'ip_address' => $ip, 'details' => [], 'created_at' => now()]);
        }
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
        $this->get('/audit-logs?ip_address=203.0.113.10')->assertOk()->assertSee('Audit Officer')->assertSee('request.view')->assertSee('203.0.113.10')->assertDontSee('203.0.113.11')->assertSee('IP Address')->assertSee('ব্যবহারকারী আইডি');
    }
}
