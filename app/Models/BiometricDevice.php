<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BiometricDevice extends Model
{
    protected $fillable = ['name', 'timezone', 'is_active'];

    protected $hidden = ['webhook_token', 'bridge_token'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_seen_at' => 'datetime', 'bridge_seen_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $device) {
            $device->webhook_token ??= self::newToken();
            $device->bridge_token ??= self::newToken();
        });
        static::deleting(fn () => throw new \LogicException('Deactivate a device instead of deleting it; its punch history is retained.'));
    }

    public static function newToken(): string
    {
        return Str::random(48);
    }

    public function punches()
    {
        return $this->hasMany(BiometricPunch::class);
    }

    public function machineUsers()
    {
        return $this->hasMany(BiometricDeviceUser::class);
    }

    public function timezone(): string
    {
        return $this->timezone ?: config('app.timezone');
    }

    public function hookPath(): string
    {
        return '/biometric/hook/'.$this->webhook_token;
    }
}
