<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Designation;
use App\Models\User;
use Database\Seeders\DesignationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withHeader('Accept-Language', 'en');
        $this->seed(DesignationSeeder::class);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'designation_id' => Designation::first()->id]);
    }

    private function token(User $u): string
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $u->email, 'password' => 'password', 'device_name' => 'Android Test'])->assertOk()->json('token');
    }

    private function data(array $more = []): array
    {
        return array_replace(['ministry' => 'BPSC', 'title' => 'Viva', 'post_name' => 'Viva', 'exam_type' => 'viva', 'unit' => 'Cadre (Exam)', 'exam_date' => today()->toDateString(), 'candidate_count' => 80, 'board_count' => 4, 'status' => 'scheduled'], $more);
    }

    private function bearer(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    public function test_api_login_token_hash_expiry_me_and_logout(): void
    {
        $u = $this->user(UserRole::Viewer);
        $token = $this->token($u);
        $this->assertNotSame($token, $u->tokens()->first()->token);
        $this->assertNotNull($u->tokens()->first()->expires_at);
        $this->bearer($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.role', 'viewer')->assertJsonMissingPath('data.password');
        $this->bearer($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->bearer($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_api_editor_crud_viewer_read_only_admin_delete(): void
    {
        $editor = $this->token($this->user(UserRole::Editor));
        $id = $this->bearer($editor)->postJson('/api/v1/schedules', $this->data())->assertCreated()->json('data.id');
        $this->bearer($editor)->putJson('/api/v1/schedules/'.$id, $this->data(['version' => 1, 'title' => 'Updated']))->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.exam_date', today()->toDateString());
        $this->bearer($editor)->deleteJson('/api/v1/schedules/'.$id, ['version' => 2, 'confirmation' => 'DELETE'])->assertForbidden();
        $viewer = $this->token($this->user(UserRole::Viewer));
        $this->bearer($viewer)->getJson('/api/v1/schedules')->assertOk()->assertJsonPath('summary.exams', 1);
        $this->bearer($viewer)->postJson('/api/v1/schedules', $this->data())->assertForbidden();
        $this->bearer($viewer)->putJson('/api/v1/schedules/'.$id, $this->data(['version' => 2]))->assertForbidden();
        $admin = $this->token($this->user(UserRole::Admin));
        $this->bearer($admin)->deleteJson('/api/v1/schedules/'.$id, ['version' => 2, 'confirmation' => 'DELETE'])->assertNoContent();
    }

    public function test_api_system_administration_is_admin_only(): void
    {
        $token = $this->token($this->user(UserRole::Editor));
        foreach (['/users', '/designations', '/audit-logs'] as $path) {
            $this->bearer($token)->getJson('/api/v1'.$path)->assertForbidden();
        }
        $admin = $this->token($this->user(UserRole::Admin));
        $this->bearer($admin)->getJson('/api/v1/users')->assertOk();
        $this->bearer($admin)->getJson('/api/v1/designations')->assertOk();
        $this->bearer($admin)->getJson('/api/v1/audit-logs')->assertOk();
    }

    public function test_api_invalid_and_expired_tokens_do_not_authenticate(): void
    {
        $u = $this->user(UserRole::Viewer);
        $token = $u->createToken('expired', ['*'], now()->subMinute())->plainTextToken;
        $this->bearer($token)->getJson('/api/v1/schedules')->assertUnauthorized();
        $this->bearer('bogus')->getJson('/api/v1/schedules')->assertUnauthorized();
    }

    public function test_role_change_password_change_and_deletion_revoke_tokens(): void
    {
        $u = $this->user(UserRole::Editor);
        $token = $this->token($u);
        $u->update(['role' => 'viewer']);
        $this->bearer($token)->getJson('/api/v1/schedules')->assertUnauthorized();
        $token = $this->token($u->fresh());
        $u->update(['password' => 'New-Strong123!']);
        $this->bearer($token)->getJson('/api/v1/schedules')->assertUnauthorized();
        $token = $u->createToken('delete', ['*'], now()->addDay())->plainTextToken;
        $u->delete();
        $this->bearer($token)->getJson('/api/v1/schedules')->assertUnauthorized();
    }

    public function test_api_profile_password_uses_token_guard_and_requires_current_password(): void
    {
        $u = $this->user(UserRole::Viewer);
        $token = $this->token($u);
        $this->bearer($token)->putJson('/api/v1/profile/password', ['current_password' => 'wrong', 'password' => 'New-Strong123!', 'password_confirmation' => 'New-Strong123!'])->assertUnprocessable();
        $this->bearer($token)->putJson('/api/v1/profile/password', ['current_password' => 'password', 'password' => 'New-Strong123!', 'password_confirmation' => 'New-Strong123!'])->assertOk();
        $this->bearer($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_login_attempts_are_throttled_without_leaking_credentials(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'bad@example.test', 'password' => 'SensitivePassword', 'device_name' => 'phone'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'bad@example.test', 'password' => 'SensitivePassword', 'device_name' => 'phone'])->assertStatus(429);
        $this->assertStringNotContainsString('SensitivePassword', AuditLog::all()->toJson());
    }

    public function test_api_options_and_exports_use_same_filters(): void
    {
        $token = $this->token($this->user(UserRole::Viewer));
        $this->bearer($token)->getJson('/api/v1/options')->assertOk()->assertJsonStructure(['exam_types', 'units', 'statuses', 'roles', 'designations']);
        $this->bearer($token)->get('/api/v1/schedules/export/pdf?date='.today()->toDateString(), ['Accept' => 'application/json'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
