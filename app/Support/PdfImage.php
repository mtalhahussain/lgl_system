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

    /** Shared payslip/receipt logo from Settings (public disk), or null. */
    public static function instituteLogo(): ?string
    {
        $path = setting('document_logo');

        return $path ? self::dataUri(\Illuminate\Support\Facades\Storage::disk('public')->path($path)) : null;
    }

    /** Receipt branding prefers the document upload, then other existing institute logos. */
    public static function receiptLogo(): ?string
    {
        foreach (['document_logo', 'institute_logo', 'sidebar_logo'] as $key) {
            $path = setting($key);
            $image = $path ? self::dataUri(\Illuminate\Support\Facades\Storage::disk('public')->path($path)) : null;
            if ($image !== null) {
                return $image;
            }
        }

        return null;
    }

}
