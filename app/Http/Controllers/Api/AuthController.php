<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\User;
use App\Services\Scheduler\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:1024', 'device_name' => 'required|string|max:100']);
        $user = User::where('email', strtolower($d['email']))->first();
        // Always perform a password verification, including unknown account attempts.
        $valid = Hash::check($d['password'], $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (! $user || ! $user->is_active || ! $valid) {
            app(Audit::class)->record('auth.failed', actor: $user);
            throw ValidationException::withMessages(['email' => __('The supplied credentials are invalid.')]);
        }

        return DB::transaction(function () use ($d, $user) {
            $current = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->is_active && $current->password === $user->password, 401);
            $current->tokens()->where('expires_at', '<=', now())->delete();
            while ($current->tokens()->count() >= 5) {
                $current->tokens()->oldest('id')->first()->delete();
            }
            $expires = now()->addHours(config('scheduler.api_token_hours'));
            $token = $current->createToken($d['device_name'], ['*'], $expires);
            $current->forceFill(['last_login_at' => now()])->save();
            app(Audit::class)->record('auth.login', ['device_name' => $d['device_name']], actor: $current);

            return response()->json(['token' => $token->plainTextToken, 'token_type' => 'Bearer', 'expires_at' => $expires->toIso8601String(), 'user' => $current->load('designation')]);
        });
    }

    public function me(Request $r)
    {
        return response()->json(['data' => $r->user()->load('designation')]);
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()->delete();
        app(Audit::class)->record('auth.logout', actor: $r->user());

        return response()->noContent();
    }

    public function logoutAll(Request $r)
    {
        $r->user()->tokens()->delete();
        app(Audit::class)->record('auth.logout_all', actor: $r->user());

        return response()->noContent();
    }

    public function options()
    {
        return response()->json(['exam_types' => array_map(fn ($t) => array_replace($t, ['label' => __($t['label'])]), config('scheduler.types')), 'units' => config('scheduler.units'), 'user_units' => config('scheduler.user_units'), 'unit_labels' => array_combine(config('scheduler.units'), config('scheduler.units')), 'statuses' => array_map(fn ($label) => __($label), config('scheduler.statuses')), 'roles' => UserRole::options(), 'designations' => Designation::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])]);
    }
}
