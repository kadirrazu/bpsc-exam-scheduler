<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/*')) {
            $locale = $request->hasHeader('Accept-Language') ? $request->getPreferredLanguage(['bn', 'en']) : $request->user('sanctum')?->preferred_locale;
        } else {
            $locale = $request->user()?->preferred_locale ?? $request->session()->get('locale', 'bn');
        }
        app()->setLocale(in_array($locale, ['bn', 'en'], true) ? $locale : 'bn');

        return $next($request);
    }
}
