<?php

namespace App\Biometric;

use App\Models\BiometricDevice;
use App\Models\BiometricDeviceUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps biometric_device_users (what each machine should hold) in step with active Students and Teachers.
 * The institute-PC bridge reads pending rows and applies them to the machines over ISAPI.
 */
class MachineUserSync
{
    private const FIELDS = ['name', 'device_employee_no', 'is_active', 'role'];

    private static ?bool $ready = null;

    public function __construct(private BiometricCode $codes) {}

    /** Called from User created/updated events. */
    public function userChanged(User $user, bool $created): void
    {
        if (! $this->ready() || (! $created && ! $user->wasChanged(self::FIELDS))) {
            return;
        }
        $previous = $created ? null : $user->getOriginal('device_employee_no');
        if ($this->eligible($user)) {
            $this->codes->ensure($user);
        }
        DB::transaction(function () use ($user, $previous) {
            foreach (BiometricDevice::where('is_active', true)->orderBy('id')->get() as $device) {
                if ($previous && $previous !== $user->device_employee_no) {
                    $this->queue($device, $previous, $user, 'delete');
                }
                if ($user->device_employee_no) {
                    $this->queue($device, $user->device_employee_no, $user, $this->eligible($user) ? 'upsert' : 'delete');
                }
            }
        });
    }

    /** Numbers every active Student/Teacher who has none and queues them all to one machine. */
    public function queueAll(BiometricDevice $device): int
    {
        $count = 0;
        User::whereIn('role', ['student', ...User::PAID_ROLES])->where('is_active', true)->orderBy('id')
            ->each(function (User $user) use ($device, &$count) {
                $this->codes->ensure($user);
                $this->queue($device, $user->device_employee_no, $user, 'upsert');
                $count++;
            });

        return $count;
    }

    private function eligible(User $user): bool
    {
        return $user->is_active && in_array($user->role, ['student', ...User::PAID_ROLES], true);
    }

    private function queue(BiometricDevice $device, string $employeeNo, User $user, string $action): void
    {
        $row = BiometricDeviceUser::lockForUpdate()->firstOrNew(['biometric_device_id' => $device->id, 'employee_no' => $employeeNo]);
        $name = mb_substr($user->name, 0, 32);
        // Nothing to send: already removed, or the machine already has this exact user.
        if (! $row->exists && $action === 'delete') {
            return;
        }
        if ($row->exists && $row->action === $action && $row->name === $name && $row->user_id === $user->id && $row->status === 'synced') {
            return;
        }
        $row->fill(['user_id' => $user->id, 'name' => $name, 'action' => $action, 'status' => 'pending', 'attempts' => 0, 'last_error' => null]);
        $row->version = $row->exists ? $row->version + 1 : 1;
        $row->save();
    }

    private function ready(): bool
    {
        return self::$ready ??= Schema::hasTable('biometric_device_users');
    }

    /** Tests run migrations per case; forget the cached table check. */
    public static function forgetReady(): void
    {
        self::$ready = null;
    }
}
