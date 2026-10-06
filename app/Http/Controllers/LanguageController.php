<?php

namespace App\Http\Controllers;

use App\Services\Scheduler\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LanguageController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate(['locale' => 'required|in:bn,en', 'return_to' => ['nullable', 'string', 'max:2048', function ($attribute, $value, $fail) {
            // Redirects stay on this origin. Referrer/absolute URLs are never trusted.
            $decoded = rawurldecode($value);
            if (! str_starts_with($decoded, '/') || str_starts_with($decoded, '//') || preg_match('/[\\\\\x00-\x1f]/', $decoded)) {
                $fail(__('Invalid return address.'));
            }
        }]]);
        if ($user = $request->user()) {
            DB::transaction(fn () => $user->forceFill(['preferred_locale' => $data['locale']])->save());
        }
        $request->session()->put('locale', $data['locale']);
        app()->setLocale($data['locale']);
        app(Audit::class)->record('language.changed', ['locale' => $data['locale']]);
        $target = isset($data['return_to']) ? $request->getSchemeAndHttpHost().$data['return_to'] : route($request->user() ? 'dashboard' : 'login');

        return redirect($target);
    }
}
