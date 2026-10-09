<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExamSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-09 12:00:00', 'Asia/Dhaka'));
    }

    private function staff(UserRole $role = UserRole::Viewer, string $locale = 'en'): User
    {
        $user = User::factory()->create(['role' => $role, 'preferred_locale' => $locale]);
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);

        return $user;
    }

    private function schedule(string $date, array $extra = []): ExamSchedule
    {
        return ExamSchedule::create(array_replace(['title' => 'Exam '.$date, 'post_name' => 'Post '.$date,
            'ministry' => 'BPSC', 'unit' => 'Unit 01', 'exam_type' => 'written',
            'exam_date' => $date, 'status' => 'scheduled'], $extra));
    }

    public function test_dashboard_week_and_unrestricted_list_have_distinct_order_and_ranges(): void
    {
        $past = $this->schedule('2020-01-01');
        $today = $this->schedule('2026-10-09');
        $seventh = $this->schedule('2026-10-15');
        $later = $this->schedule('2026-10-16');
        $newer = $this->schedule('2026-10-16');
        $this->staff();

        $this->get('/dashboard')->assertOk()->assertViewHas('schedules', fn ($p) => $p->pluck('id')->all() === [$today->id, $seventh->id])
            ->assertViewHas('filters', fn ($f) => $f['from'] === '2026-10-09' && $f['to'] === '2026-10-15');
        $this->get('/schedules')->assertOk()->assertViewHas('schedules', fn ($p) => $p->pluck('id')->all() === [$newer->id, $later->id, $seventh->id, $today->id, $past->id])
            ->assertViewHas('filters', fn ($f) => $f['from'] === null && $f['to'] === null);
        $this->get('/dashboard?date=2020-01-01')->assertOk()->assertViewHas('schedules', fn ($p) => $p->pluck('id')->all() === [$past->id]);
        $this->get('/schedules?from=2020-01-01&to=2026-10-09')->assertOk()->assertViewHas('schedules', fn ($p) => $p->pluck('id')->all() === [$today->id, $past->id]);
    }

    public function test_all_dates_list_is_paginated_and_filters_preserve_pagination(): void
    {
        for ($i = 0; $i < 27; $i++) {
            $this->schedule('2028-01-01', ['reference' => '250027', 'post_name' => 'Assistant Director']);
        }
        $this->schedule('2029-01-01', ['post_name' => 'Different Post']);
        $this->staff();
        $this->get('/schedules?search=250027&status=scheduled')->assertOk()
            ->assertViewHas('schedules', fn ($p) => $p->total() === 27 && $p->count() === 25 && str_contains($p->url(2), 'search=250027'))
            ->assertSee('Showing 1–25 of 27 matching records');
        $this->get('/schedules?search=250027&status=scheduled&page=2')->assertOk()
            ->assertViewHas('schedules', fn ($p) => $p->count() === 2 && $p->firstItem() === 26);
    }

    public function test_bengali_digits_are_display_only_and_search_matches_both_scripts(): void
    {
        $ascii = $this->schedule('2020-01-01', ['reference' => '250027', 'post_grade' => 9, 'advertisement_number' => '12/2020', 'notes' => 'Room 9']);
        $bangla = $this->schedule('2020-01-02', ['reference' => '২৫০০২৭']);
        $this->staff(locale: 'bn');
        $this->get('/schedules?search=২৫০০২৭')->assertOk()->assertSee('২৫০০২৭')->assertSee('৯')->assertSee('১২/২০২০')
            ->assertViewHas('schedules', fn ($p) => $p->total() === 2);
        $this->get('/schedules/'.$ascii->id)->assertOk()->assertSee('Room ৯');
        $this->assertSame('250027', $ascii->fresh()->reference);
        $this->assertSame(9, $ascii->fresh()->post_grade);
        $this->assertSame('Room 9', $ascii->fresh()->notes);
        $this->staff(locale: 'en');
        $this->get('/schedules?search=250027')->assertOk()->assertSee('250027')->assertSee('২৫০০২৭')
            ->assertViewHas('schedules', fn ($p) => $p->total() === 2);
        $this->get('/schedules/'.$ascii->id)->assertOk()->assertSee('Room 9');
        $this->assertSame('২৫০০২৭', $bangla->fresh()->reference);
    }

    public function test_list_actions_follow_roles_and_delete_still_requires_confirmation(): void
    {
        $schedule = $this->schedule('2020-01-01');
        foreach ([UserRole::Viewer, UserRole::Editor, UserRole::Admin] as $role) {
            $this->staff($role);
            $response = $this->get('/schedules')->assertOk()->assertSee(route('schedules.show', $schedule));
            if ($role === UserRole::Viewer) {
                $response->assertDontSee(route('schedules.edit', $schedule));
            } else {
                $response->assertSee(route('schedules.edit', $schedule));
            }
            if ($role === UserRole::Admin) {
                $response->assertSee(route('schedules.show', $schedule).'#delete-schedule');
                $this->delete('/schedules/'.$schedule->id, ['version' => 1])->assertSessionHasErrors('confirmation');
            } else {
                $response->assertDontSee('#delete-schedule');
                $this->delete('/schedules/'.$schedule->id, ['version' => 1, 'confirmation' => 'DELETE'])->assertForbidden();
            }
        }
        $this->assertFalse($schedule->fresh()->trashed());
    }

    public function test_exports_follow_selected_scope_and_api_keeps_raw_values(): void
    {
        $past = $this->schedule('2020-01-01', ['post_name' => 'Historic Post', 'reference' => '250027']);
        $this->schedule('2026-10-09', ['post_name' => 'Weekly Post']);
        $user = $this->staff();
        $this->get('/schedules/export/print?scope=all')->assertOk()->assertSee('Historic Post')->assertSee('Weekly Post')->assertSee('All dates');
        $this->get('/schedules/export/print?scope=week')->assertOk()->assertDontSee('Historic Post')->assertSee('Weekly Post');
        $this->get('/schedules/export/xlsx?scope=all')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $token = $user->createToken('Test')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->withHeader('Accept-Language', 'bn')->getJson('/api/v1/schedules')->assertOk()
            ->assertJsonPath('schedules.total', 2)->assertJsonPath('schedules.data.1.reference', '250027');
        $this->assertSame('250027', $past->fresh()->reference);
    }
}
