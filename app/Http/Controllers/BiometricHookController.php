<?php

namespace App\Http\Controllers;

use App\Biometric\ClassPunchRecorder;
use App\Biometric\Drivers\HikvisionDriver;
use App\Models\BiometricDevice;
use Illuminate\Http\Request;

/** Receives Hikvision "HTTP Listening" pushes. No session, CSRF or login: the secret token identifies the device. */
class BiometricHookController extends Controller
{
    public function receive(Request $request, string $token, HikvisionDriver $driver, ClassPunchRecorder $recorder)
    {
        $device = strlen($token) >= 32 ? BiometricDevice::where('webhook_token', $token)->first() : null;
        if (! $device || ! hash_equals($device->webhook_token, $token)) {
            return response('Not found', 404);
        }
        // Seen even when inactive, so the Admin can tell the machine is still trying to connect.
        $device->forceFill(['last_seen_at' => now()])->saveQuietly();
        if (! $device->is_active || ! $request->isMethod('post')) {
            return $driver->acknowledge();
        }
        foreach ($driver->parse($request, $device) as $log) {
            try {
                $recorder->record($log, $device);
            } catch (\Throwable $exception) {
                // Keep acknowledging; a failed punch must not make the machine resend forever.
                report($exception);
            }
        }

        return $driver->acknowledge();
    }
}
