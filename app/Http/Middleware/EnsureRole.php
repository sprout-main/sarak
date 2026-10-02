<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $allowed = array_map(fn (string $role) => UserRole::from($role), $roles);

        foreach ($allowed as $role) {
            if ($user->role->isAtLeast($role)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this area.');
    }
}