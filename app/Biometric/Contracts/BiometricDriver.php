<?php

namespace App\Biometric\Contracts;

use App\Biometric\PunchLog;
use App\Models\BiometricDevice;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface BiometricDriver
{
    /** Registry key, stored in biometric_devices.brand. */
    public function key(): string;

    /** Shown in the brand dropdown. */
    public function label(): string;

    /** 'serial' = fixed URL, device found by SN. 'token' = per-device /api/biometric/hook/{token}. */
    public function identifiesBy(): string;

    /** @return PunchLog[] */
    public function parse(Request $request, BiometricDevice $device): array;

    /** Reply the machine expects after a push. */
    public function acknowledge(): Response;

    /** @return string[] ordered, human-readable setup steps */
    public function setupSteps(BiometricDevice $device, string $baseUrl): array;

    /** Validation rules for keys inside biometric_devices.settings, e.g. ['settings.timezone' => [...]]. */
    public function settingsRules(): array;
}
