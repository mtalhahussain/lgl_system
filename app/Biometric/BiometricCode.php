<?php

namespace App\Biometric;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Assigns the machine PIN (users.biometric_code): numeric, unique per gym, at most 9 digits.
 * Prefers the user's own id — members enrolled with their User ID before codes existed keep working —
 * and falls back to the gym's highest numeric code + 1 when that id is already someone else's PIN.
 */
class BiometricCode
{
    public const MAX = 999_999_999;

    /** Existing code, or a new one. Null for users without a gym (super admin). */
    public function ensure(User $user): ?string
    {
        if ($user->biometric_code !== null && $user->biometric_code !== '') {
            return $user->biometric_code;
        }

        return $this->assign($user, (string) $user->id);
    }

    /** Always a new code, different from the current one. */
    public function regenerate(User $user): ?string
    {
        return $this->assign($user, null);
    }

    private function assign(User $user, ?string $preferred): ?string
    {
        if ($user->gym_id === null) {
            return null;
        }

        for ($attempt = 1; ; $attempt++) {
            $code = $this->nextFree($user->gym_id, $preferred);

            try {
                $user->forceFill(['biometric_code' => $code])->saveQuietly();
                return $code;
            } catch (UniqueConstraintViolationException $e) {
                // Another request took it in the meantime.
                if ($attempt >= 3) {
                    throw $e;
                }
                $preferred = null;
            }
        }
    }

    private function nextFree(int $gymId, ?string $preferred): string
    {
        // Deleted users keep their code (the unique index covers them), so it is never handed out again.
        $taken = User::withTrashed()->where('gym_id', $gymId)->whereNotNull('biometric_code')->pluck('biometric_code')->flip();

        if ($preferred !== null && (int) $preferred <= self::MAX && ! isset($taken[$preferred])) {
            return $preferred;
        }

        $max  = $taken->keys()->filter(fn ($c) => ctype_digit((string) $c))->map(fn ($c) => (int) $c)->max() ?? 0;
        $next = $max + 1;

        if ($next > self::MAX) {
            throw new \RuntimeException("No free biometric code left in gym {$gymId}.");
        }

        return (string) $next;
    }
}
