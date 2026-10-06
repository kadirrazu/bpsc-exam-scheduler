<?php
namespace App\Http\Middleware;
use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
class Administrator {
    public function handle(Request $request,Closure $next) {
        abort_unless($request->user()?->is_active && $request->user()->role===UserRole::Admin,403);
        return $next($request);
    }
}
