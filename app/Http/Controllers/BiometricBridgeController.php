<?php

namespace App\Http\Controllers;

use App\Models\BiometricDevice;
use App\Models\BiometricDeviceUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API for the institute-PC bridge (scripts/hikvision-bridge.ps1). The bridge fetches pending machine users,
 * applies them to the Hikvision terminal on the LAN over ISAPI, then reports each result.
 */
class BiometricBridgeController extends Controller
{
    private const BATCH = 50;

    // Failed rows are retried after this delay, up to MAX_ATTEMPTS times.
    private const RETRY_MINUTES = 10;

    private const MAX_ATTEMPTS = 20;

    public function pending(Request $request, string $token)
    {
        $device = $this->device($token);
        $rows = BiometricDeviceUser::where('biometric_device_id', $device->id)
            ->where(fn ($query) => $query->where('status', 'pending')
                ->orWhere(fn ($failed) => $failed->where('status', 'failed')->where('attempts', '<', self::MAX_ATTEMPTS)
                    ->where('updated_at', '<=', now()->subMinutes(self::RETRY_MINUTES))))
            ->orderBy('id')->limit(self::BATCH)->get(['id', 'version', 'action', 'employee_no', 'name']);

        return response()->json(['device' => $device->name, 'users' => $rows]);
    }

    public function results(Request $request, string $token)
    {
        $device = $this->device($token);
        $data = $request->validate(['results' => 'present|array|max:'.self::BATCH, 'results.*.id' => 'required|integer',
            'results.*.version' => 'required|integer', 'results.*.ok' => 'required|boolean', 'results.*.error' => 'nullable|string']);
        $applied = 0;
        foreach ($data['results'] as $result) {
            $applied += DB::transaction(function () use ($device, $result) {
                $row = BiometricDeviceUser::where('biometric_device_id', $device->id)->lockForUpdate()->find($result['id']);
                // Changed again while the bridge was working: keep it pending for the next run.
                if (! $row || $row->version !== (int) $result['version'] || ! in_array($row->status, ['pending', 'failed'], true)) {
                    return 0;
                }
                $row->forceFill($result['ok']
                    ? ['status' => 'synced', 'synced_at' => now(), 'last_error' => null]
                    : ['status' => 'failed', 'attempts' => $row->attempts + 1, 'last_error' => mb_substr((string) ($result['error'] ?? 'Unknown error'), 0, 500)])->save();

                return 1;
            });
        }

        return response()->json(['applied' => $applied]);
    }

    private function device(string $token): BiometricDevice
    {
        $device = BiometricDevice::where('bridge_token', $token)->first();
        abort_unless($device && hash_equals($device->bridge_token, $token) && $device->is_active, 404);
        $device->forceFill(['bridge_seen_at' => now()])->saveQuietly();

        return $device;
    }
}
