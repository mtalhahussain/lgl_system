<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BiometricSyncRequest;
use App\Services\BiometricAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles inbound sync requests from ZKTeco (and compatible) biometric devices.
 *
 * Authentication: Devices authenticate via a dedicated Sanctum token
 * with the 'biometric-device' ability. Generate one per device:
 *   $device->createToken('zkDevice-01', ['biometric-device'])->plainTextToken
 */
class BiometricSyncController extends Controller
{
    public function __construct(private BiometricAttendanceService $biometricService) {}

    /**
     * POST /api/biometric/sync
     *
     * Accepts a batch of punch logs from a biometric device.
     *
     * Expected payload:
     * {
     *   "logs": [
     *     { "device_user_id": "42", "punch_time": "2026-05-06 09:01:00", "punch_type": 0 },
     *     { "device_user_id": "42", "punch_time": "2026-05-06 17:30:00", "punch_type": 1 }
     *   ]
     * }
     */
    public function sync(BiometricSyncRequest $request): JsonResponse
    {
        $gymId = $request->user()->gym_id;

        $result = $this->biometricService->syncBatch(
            logs:  $request->validated('logs'),
            gymId: $gymId
        );

        $status = $result['failed'] > 0 ? 207 : 200; // 207 Multi-Status if partial failures

        return response()->json([
            'message'   => 'Sync complete.',
            'processed' => $result['processed'],
            'skipped'   => $result['skipped'],
            'failed'    => $result['failed'],
            'errors'    => $result['errors'],
        ], $status);
    }

    /**
     * POST /api/biometric/punch
     *
     * Single real-time punch from a device (push mode).
     */
    public function punch(Request $request): JsonResponse
    {
        $request->validate([
            'device_user_id' => ['required', 'string'],
            'punch_time'     => ['required', 'date'],
            'punch_type'     => ['nullable', 'integer', 'in:0,1'],
        ]);

        $gymId = $request->user()->gym_id;

        try {
            $attendance = $this->biometricService->processLog(
                log:   $request->only(['device_user_id', 'punch_time', 'punch_type']),
                gymId: $gymId
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($attendance === null) {
            return response()->json(['message' => 'Duplicate punch skipped.'], 200);
        }

        return response()->json([
            'message'    => 'Punch recorded.',
            'attendance' => [
                'id'             => $attendance->id,
                'user_id'        => $attendance->user_id,
                'check_in_time'  => $attendance->check_in_time?->toDateTimeString(),
                'check_out_time' => $attendance->check_out_time?->toDateTimeString(),
                'status'         => $attendance->isOpen() ? 'checked_in' : 'checked_out',
            ],
        ], 201);
    }
}
