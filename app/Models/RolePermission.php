<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Admin-managed module visibility for the Teacher and Student portals (config/modules.php). */
class RolePermission extends Model
{
    protected $fillable = ['role', 'module', 'allowed'];

    protected function casts(): array
    {
        return ['allowed' => 'boolean'];
    }

    /** @var array<string, bool>|null Per-request cache of "role.module" => allowed. */
    private static ?array $cache = null;

    protected static function booted(): void
    {
        static::saved(fn () => self::$cache = null);
        static::deleted(fn () => self::$cache = null);
    }

    /** Roles whose modules Admin can manage. */
    public static function managedRoles(): array
    {
        return array_keys(config('modules', []));
    }

    public static function modulesFor(string $role): array
    {
        return config("modules.{$role}", []);
    }

    /** Admin always; other roles unless Admin hid the module. Unknown or unconfigured modules are allowed. */
    public static function allows(?User $user, string $module): bool
    {
        if (! $user || $user->isAdmin() || ! array_key_exists($module, self::modulesFor($user->role))) {
            return true;
        }
        self::$cache ??= self::query()->get(['role', 'module', 'allowed'])
            ->mapWithKeys(fn ($row) => [$row->role.'.'.$row->module => (bool) $row->allowed])->all();

        return self::$cache[$user->role.'.'.$module] ?? true;
    }

    /** The module (for the user's role) that covers a route name, if any. */
    public static function moduleForRoute(string $role, ?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }
        foreach (self::modulesFor($role) as $key => $module) {
            if (Str::is($module['routes'], $routeName)) {
                return $key;
            }
        }

        return null;
    }

    public static function flushCache(): void
    {
        self::$cache = null;
    }
}
