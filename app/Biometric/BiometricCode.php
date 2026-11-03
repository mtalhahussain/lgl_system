<?php

namespace App\Biometric;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Assigns the machine Employee No. (users.device_employee_no): numeric and unique, at most 9 digits.
 * Prefers the user's own id and falls back to the highest numeric number + 1 when that id is already taken.
 * Adapted from the gym project (no gym scope here; users are deactivated, never deleted, so numbers are not reused).
 */
class BiometricCode
{
    public const MAX = 999_999_999;

    /** Existing number, or a new one. */
    public function ensure(User $user): string
    {
        if ($user->device_employee_no !== null && $user->device_employee_no !== '') {
            return $user->device_employee_no;
        }
        $preferred = (string) $user->id;
        for ($attempt = 1; ; $attempt++) {
            $code = $this->nextFree($preferred);
            try {
                // Quietly: the caller queues the machine sync once afterwards.
                $user->forceFill(['device_employee_no' => $code])->saveQuietly();

                return $code;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= 3) {
                    throw $exception;
                }
                $preferred = null;
            }
        }
    }

    private function nextFree(?string $preferred): string
    {
        $taken = User::whereNotNull('device_employee_no')->pluck('device_employee_no')->flip();
        if ($preferred !== null && (int) $preferred <= self::MAX && ! isset($taken[$preferred])) {
            return $preferred;
        }
        $max = $taken->keys()->filter(fn ($code) => ctype_digit((string) $code))->map(fn ($code) => (int) $code)->max() ?? 0;
        if ($max + 1 > self::MAX) {
            throw new \RuntimeException('No free machine employee number left.');
        }

        return (string) ($max + 1);
    }
}
