<?php

namespace App\Biometric\Drivers;

use App\Biometric\PunchLog;
use App\Biometric\WallClock;
use App\Models\BiometricDevice;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hikvision access-control terminals (DS-K1T… face + fingerprint) using "HTTP Listening" event push.
 * Body is JSON, either raw or in a multipart part named event_log / AccessControllerEvent
 * (face snapshots come as extra file parts and are ignored, never stored).
 */
class HikvisionDriver
{
    private const MAJOR_EVENT_ACCESS = 5;

    // Always 200: otherwise the terminal re-sends the same event in a loop.
    public function acknowledge(): Response
    {
        return response('OK', 200);
    }

    /** @return string[] ordered setup steps shown to the Admin */
    public function setupSteps(BiometricDevice $device, string $baseUrl): array
    {
        $host = parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl;
        $port = parse_url($baseUrl, PHP_URL_PORT) ?: (str_starts_with($baseUrl, 'https') ? 443 : 80);

        return [
            "Open the terminal's web page (or iVMS-4200) → Configuration → Network → Advanced Settings → HTTP Listening.",
            "Destination IP / domain: {$host}   Port: {$port}",
            'URL: '.$device->hookPath(),
            '(Full URL, if the screen asks for one: '.rtrim($baseUrl, '/').$device->hookPath().')',
            'Protocol: '.(str_starts_with($baseUrl, 'https') ? 'HTTPS (if the firmware supports it, otherwise ask your host for an HTTP URL)' : 'HTTP').'. Choose JSON as the event format. Save.',
            'Under Event / Linkage, make sure Access Control events are uploaded to the listening host.',
            "Set each student's and teacher's Employee No. on the terminal to the Machine Employee No. on their profile here, then enroll their face and fingerprint on the terminal.",
            "Check the terminal's date, time and time zone. Make a test punch and look for it in the punch log below.",
        ];
    }

    /** @return PunchLog[] */
    public function parse(Request $request, BiometricDevice $device): array
    {
        $event = $this->extractEvent($request);
        $ace = is_array($event['AccessControllerEvent'] ?? null) ? $event['AccessControllerEvent'] : [];
        if ((int) ($ace['majorEventType'] ?? 0) !== self::MAJOR_EVENT_ACCESS) {
            return [];
        }
        // majorEventType 5 covers granted AND denied access (failed face/fingerprint, no permission, …).
        if (! in_array((int) ($ace['subEventType'] ?? 0), config('biometric.hikvision.pass_sub_events', []), true)) {
            return [];
        }
        $employee = $ace['employeeNoString'] ?? null;
        if (! is_scalar($employee) || trim((string) $employee) === '') {
            $employee = $ace['employeeNo'] ?? null;
        }
        $time = $event['dateTime'] ?? null;
        if (! is_scalar($employee) || trim((string) $employee) === '' || strlen(trim((string) $employee)) > 64 || ! is_string($time) || $time === '') {
            return [];
        }
        $type = match ($ace['attendanceStatus'] ?? null) {
            'checkIn', 'breakIn', 'overtimeIn' => PunchLog::IN,
            'checkOut', 'breakOut', 'overtimeOut' => PunchLog::OUT,
            default => null,
        };
        try {
            $wallClock = WallClock::parse($time, $device->timezone());
        } catch (\Throwable) {
            return [];
        }

        return [new PunchLog(trim((string) $employee), $wallClock, $type)];
    }

    private function extractEvent(Request $request): ?array
    {
        foreach (['event_log', 'AccessControllerEvent'] as $part) {
            $value = $request->input($part);
            if (is_string($value) && is_array($decoded = json_decode($value, true))) {
                return $decoded;
            }
            // Some firmware sends the JSON part as an uploaded file instead of a form field.
            $file = $request->file($part);
            if ($file instanceof UploadedFile && $file->getSize() <= 65536 && is_array($decoded = json_decode($file->get(), true))) {
                return $decoded;
            }
        }
        $decoded = json_decode($request->getContent(), true);

        return is_array($decoded) ? $decoded : null;
    }
}
