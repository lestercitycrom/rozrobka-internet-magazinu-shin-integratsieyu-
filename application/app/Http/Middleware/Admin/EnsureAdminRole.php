<?php

namespace App\Http\Middleware\Admin;

use App\Modules\Identity\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdminRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        abort_unless($user !== null && $user->is_active, 403);

        $allowed = array_values(array_intersect($roles, UserRole::adminValues()));
        abort_unless($allowed !== [] && in_array($user->role->value, $allowed, true), 403);

        return $next($request);
    }
}
