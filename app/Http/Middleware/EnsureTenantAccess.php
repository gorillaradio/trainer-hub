<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = tenant();

        if (! $tenant || $tenant->owner_id !== $request->user()->id) {
            abort(403);
        }

        return $next($request);
    }
}
