<?php

namespace App\Biometric;

use App\Models\BiometricDevice;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\BiometricAttendanceService;
use Illuminate\Support\Str;

/** Turns a PunchLog from any brand into attendance for the device's gym. */
class BiometricPunchProcessor
{
    public function __construct(
        private AttendanceService $attendance,
        private BiometricAttendanceService $biometricAttendance,
    ) {}

    public function process(PunchLog $log, BiometricDevice $device): bool
    {
        $employeeId = trim($log->employeeId);
        if ($employeeId === '') {
            return false;
        }

        $user = $this->resolveOrCreateUser($employeeId, $device);

        if (! $user) {
            return false;
        }

        $time = $log->time;

        if ($this->biometricAttendance->isDuplicate($user->id, $device->gym_id, $time)) {
            return true;
        }

        $open = $this->attendance->findOpenSession($user->id, $device->gym_id);

        // Machine said "in" but member is already inside, or "out" with nothing open: ignore.
        if (($log->type === PunchLog::IN && $open) || ($log->type === PunchLog::OUT && ! $open)) {
            return true;
        }

        // null type (and consistent in/out) → toggle, exactly as ZKTeco always worked
        $this->attendance->processLog(
            user:         $user,
            gymId:        $device->gym_id,
            time:         $time,
            source:       'biometric',
            deviceUserId: $employeeId,
        );

        return true;
    }

    private function resolveOrCreateUser(string $employeeId, BiometricDevice $device): ?User
    {
        $gymId = $device->gym_id;

        $byCode = User::where('gym_id', $gymId)
            ->where('biometric_code', $employeeId)
            ->first();

        if ($byCode) {
            return $byCode;
        }

        $byId = User::where('gym_id', $gymId)
            ->where('id', $employeeId)
            ->first();

        if ($byId) {
            if (empty($byId->biometric_code)) {
                $byId->update(['biometric_code' => $employeeId]);
            }

            return $byId;
        }

        // Phone numbers are not matched: 11 digits don't fit a machine PIN.
        return $this->autoCreateMemberFromDevice($employeeId, $device);
    }

    private function autoCreateMemberFromDevice(string $employeeId, BiometricDevice $device): ?User
    {
        $gymId = $device->gym_id;
        $email = sprintf(
            'device-%s-%s@local.member',
            preg_replace('/[^A-Za-z0-9]/', '', $employeeId) ?: 'member',
            Str::lower(Str::random(8))
        );

        $user = User::create([
            'gym_id'         => $gymId,
            'name'           => 'Device Member ' . $employeeId,
            'email'          => $email,
            'password'       => Str::random(32),
            'status'         => 'active',
            'biometric_code' => $employeeId,
        ]);

        try {
            if (! $user->hasRole('member')) {
                $user->assignRole('member');
            }
        } catch (\Throwable $e) {
            // Continue without interrupting biometric processing.
        }

        return $user;
    }
}
