<?php

namespace App\Biometric;

use App\Biometric\Contracts\BiometricDriver;

class DriverRegistry
{
    /** @return array<string, string> key => label, in config order */
    public function options(): array
    {
        return collect(config('biometric.drivers'))
            ->map(fn ($class, $key) => $this->get($key)->label())
            ->all();
    }

    public function keys(): array
    {
        return array_keys(config('biometric.drivers'));
    }

    public function get(string $key): BiometricDriver
    {
        $class = config("biometric.drivers.{$key}");

        if (! $class) {
            throw new \InvalidArgumentException("Unknown biometric brand [{$key}].");
        }

        return app($class);
    }
}
