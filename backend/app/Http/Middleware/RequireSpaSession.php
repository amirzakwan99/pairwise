<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireSpaSession
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->hasSession(), 403, 'Open the app on its configured origin and initialize the CSRF session.');

        return $next($request);
    }
}
