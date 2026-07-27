<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    // Accepts one or more keys — e.g. 'permission:purchasing.view,purchasing.purchase'
    // — and passes if the user has ANY of them. Lets a route stay reachable to
    // someone who only holds an action permission (like "Purchase") without also
    // requiring the section's "view" permission as a prerequisite.
    public function handle(Request $request, Closure $next, string ...$keys)
    {
        $hasAny = Auth::check() && collect($keys)->contains(fn ($key) => Auth::user()->hasPermission($key));

        abort_unless($hasAny, 403, 'You do not have permission to perform this action.');

        return $next($request);
    }
}
