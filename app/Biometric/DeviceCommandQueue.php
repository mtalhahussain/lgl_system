<?php

namespace App\Biometric;

use App\Models\BiometricDevice;
use App\Models\DeviceCommand;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Portal-first enrollment: members/trainers are created in the portal, their user record is
 * queued to every ZKTeco machine of their gym, and the machine picks it up on GET /iclock/getrequest.
 * Fingerprints are then enrolled on the machine against that PIN.
 */
class DeviceCommandQueue
{
    public function __construct(private BiometricCode $codes) {}

    /** Give the user a PIN (if missing) and queue them to their gym's machines. */
    public function enroll(User $user): int
    {
        return $this->codes->ensure($user) === null ? 0 : $this->pushUser($user);
    }

    /** Queue DATA UPDATE USERINFO to every active ZKTeco machine of the user's gym. */
    public function pushUser(User $user): int
    {
        if (! $user->biometric_code) {
            return 0;
        }

        $queued = 0;
        foreach ($this->machinesOf($user->gym_id) as $device) {
            $queued += (int) $this->queueEnrollment($device, $user);
        }

        return $queued;
    }

    /** Queue DATA DELETE USERINFO for a PIN (removes the user and their fingerprints from the machine). */
    public function removePin(?int $gymId, string $pin, ?int $userId = null): int
    {
        $queued = 0;
        foreach ($this->machinesOf($gymId) as $device) {
            if ($device->acc_push_state) {
                $this->queue($device, app(AccPush::class)->authorizationCommand($pin, false), $userId);
            }
            $queued += (int) $this->queue($device, 'DATA DELETE USERINFO PIN=' . $pin, $userId);
        }

        return $queued;
    }

    /** Queue every member and trainer of the device's gym to this one machine (e.g. a newly added machine). */
    public function pushAllUsers(BiometricDevice $device): int
    {
        $queued = 0;

        User::where('gym_id', $device->gym_id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['member', 'trainer']))
            ->orderBy('id')
            ->each(function (User $user) use ($device, &$queued) {
                if ($this->codes->ensure($user) !== null) {
                    $queued += (int) $this->queueEnrollment($device, $user);
                }
            });

        return $queued;
    }

    /**
     * Commands to hand the machine on this poll, marked sent. Also re-sends commands that were
     * sent but never answered (machine rebooted mid-way); USERINFO updates/deletes are idempotent.
     *
     * @return Collection<int, DeviceCommand>
     */
    public function nextBatch(BiometricDevice $device): Collection
    {
        $resendBefore = now()->subMinutes(config('biometric.adms.resend_after_minutes', 10));

        return DB::transaction(function () use ($device, $resendBefore) {
            $commands = DeviceCommand::where('device_serial_number', $device->serial_number)
                ->whereNotNull('cmd_id')
                ->where(fn ($q) => $q->where('status', DeviceCommand::PENDING)
                    ->orWhere(fn ($q) => $q->where('status', DeviceCommand::SENT)->where('sent_at', '<', $resendBefore)))
                ->orderBy('id')
                ->limit(max(1, config('biometric.adms.commands_per_poll', 10)))
                ->lockForUpdate()
                ->get();

            if ($commands->isNotEmpty()) {
                DeviceCommand::whereIn('id', $commands->pluck('id'))
                    ->update(['status' => DeviceCommand::SENT, 'sent_at' => now()]);
            }

            return $commands;
        });
    }

    /**
     * POST /iclock/devicecmd body: one result per line, "ID=<cmd_id>&Return=<code>&CMD=<type>".
     * Return >= 0 is success; negative codes are machine errors. Only this machine's commands are touched.
     */
    public function recordResults(BiometricDevice $device, string $raw): int
    {
        $updated = 0;

        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            parse_str(trim($line), $fields);
            $cmdId  = $fields['ID'] ?? null;
            $return = $fields['Return'] ?? null;

            if (! is_string($cmdId) || ! ctype_digit($cmdId) || ! is_string($return) || ! is_numeric($return)) {
                continue;
            }

            $updated += DeviceCommand::where('device_serial_number', $device->serial_number)
                ->where('cmd_id', (int) $cmdId)
                ->update([
                    'status'       => (int) $return >= 0 ? DeviceCommand::DONE : DeviceCommand::FAILED,
                    'result'       => mb_substr(trim($line), 0, 1000),
                    'completed_at' => now(),
                ]);
        }

        return $updated;
    }

    /** DATA UPDATE USERINFO PIN=..\tName=..\tPri=0\tPasswd=\tCard=\tGrp=1 */
    public static function userInfoCommand(User $user): string
    {
        // Tabs/newlines would break the command; machines store at most 24 characters of name.
        $name = mb_substr(trim(preg_replace('/[\t\r\n]+/', ' ', (string) $user->name)), 0, 24);

        return implode("\t", [
            'DATA UPDATE USERINFO PIN=' . $user->biometric_code,
            'Name=' . $name,
            'Pri=0',
            'Passwd=',
            'Card=',
            'Grp=1',
        ]);
    }

    /** Counts users/machines as before, while authorization commands have separate results. */
    private function queueEnrollment(BiometricDevice $device, User $user): bool
    {
        return DB::transaction(function () use ($device, $user) {
            if ($device->acc_push_state) {
                $this->queue($device, app(AccPush::class)->timezoneCommand(), null);
            }
            $queued = $this->queue($device, self::userInfoCommand($user), $user->id);
            if ($device->acc_push_state) {
                $authorization = app(AccPush::class)->authorizationCommand(
                    $user->biometric_code, $user->status === 'active');
                $queued = $this->queue($device, $authorization, $user->id) || $queued;
            }
            return $queued;
        });
    }

    /** @return Collection<int, BiometricDevice> active ZKTeco machines of a gym */
    private function machinesOf(?int $gymId): Collection
    {
        if ($gymId === null) {
            return collect();
        }

        return BiometricDevice::where('gym_id', $gymId)
            ->where('brand', 'zkteco')
            ->where('is_active', true)
            ->whereNotNull('serial_number')
            ->get();
    }

    /** False when the same command is already waiting for this machine (repeat clicks, re-syncs). */
    private function queue(BiometricDevice $device, string $command, ?int $userId): bool
    {
        $exists = DeviceCommand::where('device_serial_number', $device->serial_number)
            ->whereIn('status', [DeviceCommand::PENDING, DeviceCommand::SENT])
            ->where('command', $command)
            ->exists();

        if ($exists) {
            return false;
        }

        DeviceCommand::create([
            'device_serial_number' => $device->serial_number,
            'user_id'              => $userId,
            'command'              => $command,
            'status'               => DeviceCommand::PENDING,
        ]);

        return true;
    }
}
