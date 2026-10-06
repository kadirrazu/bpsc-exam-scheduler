<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Designation;
use App\Models\ExamSchedule;
use App\Models\User;
use App\Observers\SchedulerObserver;
use App\Services\Scheduler\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Paginator::defaultView('shared.pagination');
        Gate::define('manage-system', fn (User $u) => $u->is_active && $u->role === UserRole::Admin);
        Gate::define('edit-schedules', fn (User $u) => $u->is_active && in_array($u->role, [UserRole::Admin, UserRole::Editor], true));
        foreach ([User::class, Designation::class, ExamSchedule::class] as $model) {
            $model::observe(SchedulerObserver::class);
        }
        Event::listen(Login::class, function (Login $e) {
            $e->user->forceFill(['last_login_at' => now()])->save();
            request()->session()->put('auth_version', $e->user->auth_version);
            app(Audit::class)->record('auth.login', actor: $e->user);
        });
        Event::listen(Logout::class, fn (Logout $e) => app(Audit::class)->record('auth.logout', actor: $e->user));
        Event::listen(Failed::class, fn (Failed $e) => app(Audit::class)->record('auth.failed', actor: $e->user));
        RateLimiter::for('api-login', fn (Request $r) => [Limit::perMinute(20)->by($r->ip()), Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip())]);
        RateLimiter::for('scheduler-api', fn (Request $r) => Limit::perMinute(120)->by((string) ($r->user()?->id ?? $r->ip())));
        RateLimiter::for('scheduler-export', fn (Request $r) => Limit::perMinute(10)->by((string) $r->user()?->id));
    }
}
