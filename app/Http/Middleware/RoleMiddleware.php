<?php

namespace App\Http\Middleware;

use App\Models\RolePermission;
use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        // Staff may use an admin route only when Admin granted Staff the matching module action (Users > Role permissions).
        $staffGranted = $user->isStaff() && in_array('admin', $roles, true)
            && RolePermission::staffAllowsRoute($user, $request->route()?->getName());

        if (!in_array($user->role, $roles) && !$staffGranted) {
            abort(403, 'Unauthorized. You do not have permission to access this page.');
        }

        return $next($request);
    }
}
