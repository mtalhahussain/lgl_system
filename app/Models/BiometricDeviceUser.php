<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What one machine should hold for one employee number; the bridge applies pending rows. */
class BiometricDeviceUser extends Model
{
    protected $fillable = ['biometric_device_id', 'employee_no', 'user_id', 'name', 'action', 'status', 'attempts', 'last_error'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'attempts' => 'integer', 'user_id' => 'integer', 'synced_at' => 'datetime'];
    }

    public function device()
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
