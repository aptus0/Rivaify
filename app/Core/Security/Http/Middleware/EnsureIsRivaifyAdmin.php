<?php

namespace App\Core\Security\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates every admin.rivaify.com route. Deliberately separate from
 * store.context — an admin reviewing a verification request is not
 * "inside" any merchant's store, and must never accidentally inherit a
 * CurrentStore binding.
 */
class EnsureIsRivaifyAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->is_rivaify_admin) {
            abort(403);
        }

        return $next($request);
    }
}
