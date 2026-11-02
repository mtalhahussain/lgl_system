<?php

namespace App\Biometric\Drivers;

use App\Biometric\Contracts\BiometricDriver;
use App\Biometric\PunchLog;
use App\Biometric\WallClock;
use App\Models\BiometricDevice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Any machine or vendor cloud (Suprema BioStar 2, Anviz CrossChex, …) that POSTs JSON.
 * The owner maps fields with dot-paths in the device settings.
 */
class GenericWebhookDriver implements BiometricDriver
{
    public function key(): string          { return 'generic'; }
    public function label(): string        { return 'Other brand (Generic Webhook — Suprema, Anviz, …)'; }
    public function identifiesBy(): string { return 'token'; }

    public function acknowledge(): Response
    {
        return response()->json(['ok' => true]);
    }

    public function settingsRules(): array
    {
        return [
            'settings.records_path'   => ['nullable', 'string', 'max:100'],
            'settings.employee_field' => ['required', 'string', 'max:100'],
            'settings.time_field'     => ['required', 'string', 'max:100'],
            'settings.time_format'    => ['required', 'in:auto,unix,unix_ms,iso'],
            'settings.type_field'     => ['nullable', 'string', 'max:100'],
            'settings.type_in_value'  => ['nullable', 'string', 'max:50'],
            'settings.type_out_value' => ['nullable', 'string', 'max:50'],
            'settings.secret_header'  => ['nullable', 'string', 'max:100', 'required_with:settings.secret_value', 'regex:/^[A-Za-z0-9-]+$/'],
            'settings.secret_value'   => ['nullable', 'string', 'max:200', 'required_with:settings.secret_header'],
            'settings.timezone'       => ['nullable', 'timezone'],
        ];
    }

    public function setupSteps(BiometricDevice $device, string $baseUrl): array
    {
        $url   = rtrim($baseUrl, '/') . '/api/biometric/hook/' . $device->webhook_token;
        $steps = [
            "In the machine's (or vendor software's) webhook / HTTP push settings, set the URL to: {$url}",
            'Method: POST, body: JSON.',
        ];

        if (! empty($device->settings['secret_header'])) {
            $steps[] = "Add header {$device->settings['secret_header']} with the secret saved on this device.";
        }

        $steps[] = 'Make one test punch, then open "Last received data" below and check the field mapping matches.';
        $steps[] = "Set each member's user/employee ID on the machine to their Biometric Code (or User ID).";

        return $steps;
    }

    public function secretMatches(Request $request, BiometricDevice $device): bool
    {
        $header = $device->settings['secret_header'] ?? null;
        if (! $header) {
            return true;
        }

        $expected = (string) ($device->settings['secret_value'] ?? '');
        if ($expected === '') {
            // A header is configured but no secret is saved: fail closed rather than
            // matching an empty request header.
            return false;
        }

        return hash_equals($expected, (string) $request->header($header, ''));
    }

    public function parse(Request $request, BiometricDevice $device): array
    {
        $s    = $device->settings ?? [];
        $body = json_decode($request->getContent(), true);

        if (! is_array($body)) {
            // Some vendor clouds post form-encoded fields instead of JSON. Only the POST
            // body counts here — query-string params must never be able to create punches.
            $fallback = $request->request->all();
            $body     = ! empty($fallback) ? $fallback : null;
        }

        if (! is_array($body)) {
            return [];
        }

        $records = empty($s['records_path']) ? $body : data_get($body, $s['records_path']);
        if (! is_array($records)) {
            return [];
        }
        if (! array_is_list($records)) {
            $records = [$records]; // single object
        }

        $logs = [];
        foreach ($records as $rec) {
            $employee = data_get($rec, $s['employee_field'] ?? '');
            $rawTime  = data_get($rec, $s['time_field'] ?? '');

            if (! is_scalar($employee) || ! is_scalar($rawTime) || is_bool($employee)) {
                continue;
            }
            if (trim((string) $employee) === '' || $rawTime === '') {
                continue;
            }

            $time = $this->toTime($rawTime, $s['time_format'] ?? 'auto', $device->timezone());
            if (! $time) {
                continue;
            }

            $logs[] = new PunchLog((string) $employee, $time, $this->toType($rec, $s));
        }

        return $logs;
    }

    private function toTime(mixed $raw, string $format, string $tz): ?Carbon
    {
        try {
            $time = match (true) {
                $format === 'unix'    => is_numeric($raw) ? WallClock::fromUnix((float) $raw, $tz) : null,
                $format === 'unix_ms' => is_numeric($raw) ? WallClock::fromUnix(((float) $raw) / 1000, $tz) : null,
                // Exactly 14 digits: a YmdHis date string (already wall-clock, like any naive string).
                $format === 'auto' && preg_match('/^\d{14}$/', (string) $raw) === 1
                    => Carbon::createFromFormat('YmdHis', (string) $raw, config('app.timezone')) ?: null,
                $format === 'auto' && is_numeric($raw)
                    => WallClock::fromUnix(strlen((string) (int) $raw) > 10 ? ((float) $raw) / 1000 : (float) $raw, $tz),
                default => WallClock::parse((string) $raw, $tz),
            };
        } catch (\Throwable) {
            return null;
        }

        // Sanity floor: a bad/garbage numeric value (0, tiny numbers, …) parses to some time
        // near the Unix epoch rather than throwing. Reject anything obviously not a real punch.
        if (! $time instanceof Carbon || $time->lt(Carbon::create(2000, 1, 1))) {
            return null;
        }

        return $time;
    }

    private function toType(mixed $rec, array $s): ?string
    {
        if (empty($s['type_field'])) {
            return null;
        }

        $value = data_get($rec, $s['type_field']);
        if ($value === null || $value === '' || ! is_scalar($value) || is_bool($value)) {
            return null;
        }
        $value = (string) $value;

        return match (true) {
            isset($s['type_in_value'])  && $value === (string) $s['type_in_value']  => PunchLog::IN,
            isset($s['type_out_value']) && $value === (string) $s['type_out_value'] => PunchLog::OUT,
            default => null,
        };
    }
}
