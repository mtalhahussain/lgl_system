<?php

namespace App\Biometric;

use App\Models\BiometricDevice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Security PUSH in legacy 3.0.1 mode over HTTPS (no SDK message encryption). */
class AccPush
{
    /** Dedicated all-day schedule for portal members; never set the door to stay open. */
    public function timezoneCommand(): string
    {
        $fields = ['DATA UPDATE timezone TimezoneId=' . config('biometric.acc.timezone_id', 1)];
        foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Hol1', 'Hol2', 'Hol3'] as $day) {
            // PUSH uses HHMM(start) << 16 | HHMM(end); 00:00–23:59 is 2359.
            $fields[] = $day . 'Time1=2359';
            $fields[] = $day . 'Time2=0';
            $fields[] = $day . 'Time3=0';
        }
        return implode("\t", $fields);
    }

    public function authorizationCommand(string $pin, bool $active = true): string
    {
        if (! $active) {
            return 'DATA DELETE userauthorize Pin=' . $pin;
        }
        return implode("\t", [
            'DATA UPDATE userauthorize Pin=' . $pin,
            'AuthorizeTimezoneId=' . config('biometric.acc.timezone_id', 1),
            'AuthorizeDoorId=1',
        ]);
    }

    public function register(BiometricDevice $device): void
    {
        DB::transaction(function () use ($device) {
            $locked = BiometricDevice::whereKey($device->id)->lockForUpdate()->firstOrFail();
            if (! $locked->acc_push_state) {
                $locked->forceFill(['acc_push_state' => [
                    'registry_code' => Str::random(24),
                    'session_id' => Str::random(32),
                ]])->saveQuietly();
            }
            $device->setAttribute('acc_push_state', $locked->acc_push_state);
        });
    }

    public function options(BiometricDevice $device, bool $handshake = false): string
    {
        $state = $device->acc_push_state;
        $lines = $handshake ? ['registry=ok', 'RegistryCode=' . $state['registry_code']] : [];

        return implode("\n", array_merge($lines, [
            'ServerVersion=3.0.1', 'ServerName=FitnessStudio', 'PushProtVer=3.0.1',
            'ErrorDelay=30', 'RequestDelay=10', 'TransTimes=00:00;14:00',
            'TransInterval=1', 'TransTables=User Transaction', 'Realtime=1',
            'SessionID=' . $state['session_id'], 'TimeoutSec=10', 'CmdFormat=0',
        ])) . "\n";
    }

    /** Translate at delivery time so commands queued before ACC detection also work. */
    public function command(string $command): string
    {
        if (str_starts_with($command, 'DATA DELETE USERINFO PIN=')) {
            return str_replace('DATA DELETE USERINFO PIN=', 'DATA DELETE user Pin=', $command);
        }
        if (! str_starts_with($command, 'DATA UPDATE USERINFO PIN=')) {
            return $command;
        }
        $fields = $this->fields(substr($command, strlen('DATA UPDATE USERINFO ')));

        return implode("\t", [
            'DATA UPDATE user Pin=' . $fields['pin'], 'Name=' . ($fields['name'] ?? ''),
            'CardNo=' . ($fields['card'] ?? ''), 'Password=' . ($fields['passwd'] ?? ''),
            'Group=1', 'StartTime=0', 'EndTime=0', 'Privilege=0',
        ]);
    }

    public function fields(string $line): array
    {
        $fields = [];
        $line = preg_replace('/^transaction\s+/i', '', trim($line));
        foreach (explode("\t", $line) as $field) {
            if (str_contains($field, '=')) {
                [$key, $value] = explode('=', $field, 2);
                $fields[strtolower(trim($key))] = trim($value);
            }
        }
        return $fields;
    }

    public function parse(string $raw, BiometricDevice $device): array
    {
        $logs = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $fields = $this->fields($line);
            $event = $fields['event'] ?? $fields['eventtype'] ?? null;
            if ($event === null || ! ctype_digit($event)
                || ! in_array((int) $event, config('biometric.acc.pass_events'), true)) {

                continue;
            }
            $pin = $fields['pin'] ?? '';
            $time = $fields['time'] ?? $fields['time_second'] ?? '';
            if ($pin === '' || $pin === '0' || $time === '') {
                continue;
            }
            try {
                if (ctype_digit($time)) {
                    $seconds = (int) $time;
                    $days = intdiv($seconds, 86400);
                    $time = sprintf('%04d-%02d-%02d %02d:%02d:%02d',
                        2000 + intdiv($days, 372), intdiv($days % 372, 31) + 1,
                        $days % 31 + 1, intdiv($seconds % 86400, 3600),
                        intdiv($seconds % 3600, 60), $seconds % 60);
                }
                $status = $fields['inoutstatus'] ?? $fields['inoutstate'] ?? null;
                $type = $device->punchMode() === BiometricDevice::PUNCH_MODE_TOGGLE ? null : match ($status) {
                    '0' => PunchLog::IN, '1' => PunchLog::OUT, default => null,
                };
                if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $time)
                    || ! checkdate((int) substr($time, 5, 2), (int) substr($time, 8, 2), (int) substr($time, 0, 4))) {
                    throw new \InvalidArgumentException('Invalid ACC wall-clock timestamp');
                }
                $logs[] = new PunchLog($pin, WallClock::parse($time, $device->timezone()), $type);
            } catch (\Throwable $e) {
                // Continue without interrupting biometric processing.
            }
        }
        return $logs;
    }
}
