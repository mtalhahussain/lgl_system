<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Admin-managed, role-wide permissions (config/modules.php), WordPress-style.
 * Teacher/Student: one "view" switch per portal module, shown unless Admin hid it.
 * Staff: view/create/edit/delete per admin module, denied unless Admin ticked it.
 */
class RolePermission extends Model
{
    public const ACTIONS = ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'];

    /** Roles whose modules are denied unless explicitly granted. */
    private const DENY_BY_DEFAULT = ['staff'];

    protected $fillable = ['role', 'module', 'action', 'allowed'];

    protected function casts(): array
    {
        return ['allowed' => 'boolean'];
    }

    /** @var array<string, bool>|null Per-request cache of "role.module.action" => allowed. */
    private static ?array $cache = null;

    protected static function booted(): void
    {
        static::saved(fn () => self::$cache = null);
        static::deleted(fn () => self::$cache = null);
    }

    /** Roles whose modules Admin can manage, in display order. */
    public static function managedRoles(): array
    {
        return array_values(array_intersect(['teacher', 'student', 'staff'], array_keys(config('modules', []))));
    }

    public static function modulesFor(string $role): array
    {
        return config("modules.{$role}", []);
    }

    /** Action => route patterns for a module; Teacher/Student modules have a single "view" action. */
    public static function actionsFor(string $role, string $module): array
    {
        $meta = self::modulesFor($role)[$module] ?? null;

        return $meta === null ? [] : ($meta['actions'] ?? ['view' => $meta['routes'] ?? []]);
    }

    /**
     * Admin always. Staff only for ticked actions of its modules (anything else is admin-only).
     * Teacher/Student unless Admin hid the module; modules outside their config are not restricted here.
     */
    public static function allows(?User $user, string $module, string $action = 'view'): bool
    {
        if (! $user || $user->isAdmin()) {
            return (bool) $user;
        }
        $denyByDefault = in_array($user->role, self::DENY_BY_DEFAULT, true);
        if (! array_key_exists($action, self::actionsFor($user->role, $module))) {
            return ! $denyByDefault && ! array_key_exists($module, self::modulesFor($user->role));
        }
        self::$cache ??= self::query()->get(['role', 'module', 'action', 'allowed'])
            ->mapWithKeys(fn ($row) => [$row->role.'.'.$row->module.'.'.$row->action => (bool) $row->allowed])->all();

        return self::$cache[$user->role.'.'.$module.'.'.$action] ?? ! $denyByDefault;
    }

    /** [module, action] (for the role) that covers a route name, if any. */
    public static function permissionForRoute(string $role, ?string $routeName): ?array
    {
        if (! $routeName) {
            return null;
        }
        foreach (self::modulesFor($role) as $module => $meta) {
            foreach (self::actionsFor($role, $module) as $action => $patterns) {
                if (Str::is($patterns, $routeName)) {
                    return [$module, $action];
                }
            }
        }

        return null;
    }

    /** The module (for the user's role) that covers a route name, if any. */
    public static function moduleForRoute(string $role, ?string $routeName): ?string
    {
        return self::permissionForRoute($role, $routeName)[0] ?? null;
    }

    /** Staff may use an admin route only when it maps to an action Admin granted to Staff. */
    public static function staffAllowsRoute(User $user, ?string $routeName): bool
    {
        $permission = self::permissionForRoute('staff', $routeName);

        return $permission !== null && self::allows($user, ...$permission);
    }

    /**
     * Whether the user may open a named route: the same role rules as RoleMiddleware plus module permissions.
     * Used to show or hide menu items and action buttons.
     */
    public static function canRoute(?User $user, string $routeName): bool
    {
        $route = Route::getRoutes()->getByName($routeName);
        if (! $user || ! $route) {
            return false;
        }
        // Every role middleware on the route (route + controller) must pass, exactly as RoleMiddleware runs them.
        $guards = collect($route->gatherMiddleware())
            ->filter(fn ($m) => is_string($m) && str_starts_with($m, 'role:'))
            ->map(fn ($m) => explode(',', substr($m, 5)));
        $staffGranted = $user->isStaff() && self::staffAllowsRoute($user, $routeName);
        foreach ($guards as $roles) {
            if (! in_array($user->role, $roles, true) && ! ($staffGranted && in_array('admin', $roles, true))) {
                return false;
            }
        }
        $permission = $user->isAdmin() ? null : self::permissionForRoute($user->role, $routeName);

        return $permission === null || self::allows($user, ...$permission);
    }

    public static function flushCache(): void
    {
        self::$cache = null;
    }
}
