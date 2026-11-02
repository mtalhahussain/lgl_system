<?php

namespace App\Biometric\Drivers;

use App\Biometric\Contracts\BiometricDriver;
use App\Biometric\PunchLog;
use App\Biometric\AccPush;
use App\Biometric\WallClock;
use App\Models\BiometricDevice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** ZKTeco ADMS / iClock push. Also eSSL and ZK-based Realtime machines. */
class ZKTecoDriver implements BiometricDriver
{
    private const ATTLOG_TIME = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';

    public function key(): string        { return 'zkteco'; }
    public function label(): string      { return 'ZKTeco / eSSL / Realtime'; }
    public function identifiesBy(): string { return 'serial'; }

    public function acknowledge(): Response
    {
        return response('OK', 200);
    }

    public function settingsRules(): array
    {
        return [
            'settings.punch_mode' => ['nullable', Rule::in([BiometricDevice::PUNCH_MODE_STATUS, BiometricDevice::PUNCH_MODE_TOGGLE])],
        ];
    }

    public function setupSteps(BiometricDevice $device, string $baseUrl): array
    {
        $host = parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl;
        $port = parse_url($baseUrl, PHP_URL_PORT) ?: (str_starts_with($baseUrl, 'https') ? 443 : 80);

        $portLine = $port === 443
            ? "Server port: {$port} (if the machine won't connect over HTTPS, try port 80 / HTTP)."
            : "Server port: {$port}";

        return [
            'On the machine open COMM → Cloud Server Setting (ADMS).',
            "Server address: {$host}",
            $portLine,
            'Leave the URL path / server path empty if the machine shows one — it calls /iclock/cdata on its own. (Only firmware that insists on a path: use /api/biometric/push.)',
            "The machine's serial number must be exactly: " . ($device->serial_number ?: '—'),
            'Members and trainers are sent to the machine automatically when added in the portal (or with "Sync users"). On the machine open User Mgt → find the user by their Machine PIN → enroll the finger.',
            $device->punchMode() === BiometricDevice::PUNCH_MODE_STATUS
                ? 'Punch mode is "Use machine in/out status": members must press the Check-In / Check-Out key (or set the machine to auto-switch status by time). If every punch arrives as check-in, switch this device to "Alternate in/out".'
                : 'Punch mode is "Alternate in/out": each punch flips the member between checked in and checked out.',
        ];
    }

    public function parse(Request $request, BiometricDevice $device): array
    {
        $contentType = $request->header('Content-Type', '');
        $raw         = $request->getContent();

        if ($device->acc_push_state && preg_match('/\bevent(?:type)?=/i', $raw)) {
            return app(AccPush::class)->parse($raw, $device);
        }
        $table       = is_string($request->query('table')) ? $request->query('table') : null;

        if ($table !== null && strtoupper($table) !== 'ATTLOG') {
            return []; // OPERLOG, USERINFO etc. carry no punches
        }

        if ($table !== null || $this->looksLikeAttlog($raw)) {
            return $this->parseAttlog($raw, $device);
        }

        if (str_contains($contentType, 'json') || str_starts_with(trim($raw), '{') || str_starts_with(trim($raw), '[')) {
            $rows = $this->parseJson($request);
        } elseif (str_contains($contentType, 'xml') || str_starts_with(trim($raw), '<')) {
            $rows = $this->parseXml($raw);
        } else {
            $rows = [];
        }

        return $this->toPunchLogs($rows, $device);
    }

    /**
     * ADMS ATTLOG body: POST /iclock/cdata?SN=..&table=ATTLOG&Stamp=..
     * One punch per line, tab-separated: PIN, "YYYY-MM-DD HH:MM:SS", status, verify, workcode, ...
     * Status: 0 check-in, 1 check-out, 2 break-out, 3 break-in, 4 OT-in, 5 OT-out.
     *
     * @return PunchLog[]
     */
    public function parseAttlog(string $raw, BiometricDevice $device): array
    {
        $useStatus = $device->punchMode() === BiometricDevice::PUNCH_MODE_STATUS;
        $rows      = [];

        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $fields = explode("\t", trim($line, "\r\n"));
            $pin    = trim($fields[0] ?? '');
            $time   = trim($fields[1] ?? '');

            if ($pin === '' || ! preg_match(self::ATTLOG_TIME, $time)) {

                continue;
            }

            $rows[] = [
                'employee_id' => $pin,
                'time'        => $time,
                'type'        => $useStatus ? $this->statusToType(trim($fields[2] ?? '')) : null,
            ];
        }

        return $this->toPunchLogs($rows, $device);
    }

    /** An ATTLOG body without the table param (e.g. replayed from last_payload in the Setup panel). */
    private function looksLikeAttlog(string $raw): bool
    {
        $first  = strtok(ltrim($raw), "\r\n");
        $fields = $first === false ? [] : explode("\t", $first);

        return count($fields) >= 2 && preg_match(self::ATTLOG_TIME, trim($fields[1])) === 1;
    }

    private function statusToType(string $status): ?string
    {
        return match ($status) {
            '0', '3', '4' => PunchLog::IN,
            '1', '2', '5' => PunchLog::OUT,
            default       => null,
        };
    }

    /** @return PunchLog[] */
    private function toPunchLogs(array $rows, BiometricDevice $device): array
    {
        $tz   = $device->timezone();
        $logs = [];

        foreach ($rows as $r) {
            try {
                $logs[] = new PunchLog((string) $r['employee_id'], WallClock::parse((string) $r['time'], $tz), $r['type'] ?? null);
            } catch (\Throwable $e) {
                // Continue without interrupting biometric processing.
            }
        }

        return $logs;
    }

    /**
     * JSON format — PUSH SDK v3 (G3, SpeedFace series etc.)
     * { "records": [{ "employee_id": "5", "time": "2026-05-12 09:00:00", "type": 0 }] }
     */
    private function parseJson(Request $request): array
    {
        $logs = [];

        $records = $request->input('records')
            ?? $request->input('attendance_log')
            ?? (is_array($request->all()) ? $request->all() : []);

        foreach ((array) $records as $rec) {
            $logs[] = [
                'employee_id' => $rec['employee_id'] ?? $rec['EnrollNumber'] ?? $rec['user_id'] ?? null,
                'time'        => $rec['time'] ?? $rec['LogTime'] ?? $rec['timestamp'] ?? null,
            ];
        }

        return array_filter($logs, fn ($l) => $l['employee_id'] && $l['time']);
    }

    /**
     * XML format — iClock protocol (F18, K40, MA300, UA860 etc.)
     * <Log><row pin="5" time="2026-05-12 09:00:00" status="0" /></Log>
     */
    private function parseXml(string $raw): array
    {
        $logs = [];

        try {
            $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOERROR);
            if (! $xml) return [];

            $rows = $xml->row ?? $xml->record ?? $xml->Log->row ?? [];

            foreach ($rows as $row) {
                $attrs = (array) $row->attributes();
                $attr  = $attrs['@attributes'] ?? [];

                $logs[] = [
                    'employee_id' => $attr['pin'] ?? $attr['uid'] ?? $attr['EnrollNumber'] ?? null,
                    'time'        => $attr['time'] ?? $attr['LogTime'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            // Continue without interrupting biometric processing.
        }

        return array_filter($logs, fn ($l) => $l['employee_id'] && $l['time']);
    }
}
