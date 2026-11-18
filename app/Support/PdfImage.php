<?php

namespace App\Support;

/** Embed local images in dompdf output (remote loading stays disabled). */
class PdfImage
{
    private const MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];

    public static function dataUri(?string $absolutePath): ?string
    {
        if (! $absolutePath || ! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }
        $mime = self::MIME[strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION))] ?? null;

        return $mime ? 'data:'.$mime.';base64,'.base64_encode(file_get_contents($absolutePath)) : null;
    }

    /** Institute logo from Settings (public disk), or null. */
    public static function instituteLogo(): ?string
    {
        $path = setting('institute_logo');

        return $path ? self::dataUri(\Illuminate\Support\Facades\Storage::disk('public')->path($path)) : null;
    }
}
