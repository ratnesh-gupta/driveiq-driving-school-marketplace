<?php

namespace App\Http\Middleware;

use App\Models\Instructor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // BSEC-04: a terminated/inactive trainer keeps no access even with a live token.
        if ($user->role === 'instructor' && Instructor::isOffboarded((int) $user->id)) {
            return response()->json(['message' => 'Trainer access has ended'], 403);
        }

        return $next($request);
    }
}
