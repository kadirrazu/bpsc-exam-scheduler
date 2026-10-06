<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActiveApiAccount
{
    public function handle(Request $r, Closure $next)
    {
        abort_unless($r->user()?->is_active, 403, __('Account is inactive.'));

        return $next($r);
    }
}
