<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Ensure the authenticated user holds at least one of the given role slugs.
     * Usage: ->middleware('role:super_admin,admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user() ?? Auth::user();

        if ($user === null) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if (!method_exists($user, 'hasRole') || !$user->hasRole(...$roles)) {
            return response()->json(['success' => false, 'message' => 'Forbidden: insufficient role.'], 403);
        }

        return $next($request);
    }
}
