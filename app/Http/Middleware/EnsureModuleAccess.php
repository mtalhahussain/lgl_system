<?php

namespace App\Http\Middleware;

use App\Models\RolePermission;
use Closure;
use Illuminate\Http\Request;

/** Blocks routes of a module Admin has hidden for the signed-in user's role (Users > Role permissions). */
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next)
    {
        RolePermission::flushCache(); // fresh permissions for every request (long-running workers, tests)
        $user = $request->user();
        if ($user && ! $user->isAdmin()) {
            $module = RolePermission::moduleForRoute($user->role, $request->route()?->getName());
            abort_if($module && ! RolePermission::allows($user, $module), 403, 'This section has been turned off for your account type.');
        }

        return $next($request);
    }
}
