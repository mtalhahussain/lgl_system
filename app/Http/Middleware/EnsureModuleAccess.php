<?php

namespace App\Http\Middleware;

use App\Models\RolePermission;
use Closure;
use Illuminate\Http\Request;

/** Blocks routes of a module action Admin has not granted to the signed-in user's role (Users > Role permissions). */
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next)
    {
        RolePermission::flushCache(); // fresh permissions for every request (long-running workers, tests)
        $user = $request->user();
        if ($user && ! $user->isAdmin()) {
            $permission = RolePermission::permissionForRoute($user->role, $request->route()?->getName());
            abort_if($permission && ! RolePermission::allows($user, ...$permission), 403, 'This section has been turned off for your account type.');
        }

        return $next($request);
    }
}
