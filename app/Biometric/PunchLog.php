<?php

namespace App\Biometric;

use Carbon\Carbon;

/** One punch read from a machine, already normalised to wall-clock time. */
final class PunchLog
{
    public const IN  = 'in';
    public const OUT = 'out';

    public function __construct(
        public readonly string $employeeId,
        public readonly Carbon $time,
        public readonly ?string $type = null, // in | out | null (toggle)
    ) {}
}
