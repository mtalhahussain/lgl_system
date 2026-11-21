<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\StudentCertificate;
use Illuminate\Support\Facades\File;

class CertificateParagraphs
{
    public static function all(): array
    {
        $paragraphs = config('certificates.paragraphs');
        $saved = Setting::whereIn('key', array_map(self::key(...), array_keys($paragraphs)))->pluck('value', 'key');
        foreach ($paragraphs as $level => &$paragraph) {
            if (filled($saved[self::key($level)] ?? null)) {
                $paragraph = $saved[self::key($level)];
            }
        }

        return $paragraphs;
    }

    public static function key(string $level): string
    {
        return 'certificate_paragraph_'.strtolower($level);
    }

    public static function forLevel(string $level): string
    {
        return self::all()[$level];
    }

    /** Old certificates retain the wording used before configurable paragraphs. */
    public static function legacy(string $level): string
    {
        return 'Ihr Einsatz beim Erlernen der deutschen Sprache war lobenswert. Auf dem '
            .$level.'-Niveau haben Sie grundlegende sprachliche Fähigkeiten erworben '
            .'und erste einfache Kommunikationssituationen sicher gemeistert.';
    }

    /** Check real PDF layout with a longer name before accepting reusable wording. */
    public static function fits(string $level, string $paragraph): bool
    {
        File::ensureDirectoryExists(storage_path('fonts'));
        $certificate = new StudentCertificate([
            'student_name' => 'Muhammad Abdullah Ahmed Muhammad Hassan',
            'father_name' => 'Muhammad Shaheen Shah',
            'level' => $level, 'paragraph_text' => $paragraph,
            'completion_date' => '2026-11-21', 'certificate_number' => 'LAYOUT-PREVIEW',
        ]);
        $pdf = app('dompdf.wrapper')->loadView('certificates.pdf', ['cert' => $certificate])->setPaper('a4', 'landscape');
        $bottom = null;
        $right = 0;
        $pdf->getDomPDF()->setCallbacks([['event' => 'end_frame', 'f' => function ($frame) use (&$bottom, &$right) {
            $node = $frame->get_node();
            if ($node instanceof \DOMElement && $node->getAttribute('class') === 'cert-text') {
                $bottom = $frame->get_position('y') + $frame->get_margin_height();
            }
            if ($node instanceof \DOMText && $node->parentNode instanceof \DOMElement
                && $node->parentNode->getAttribute('class') === 'cert-text') {
                $right = max($right, $frame->get_position('x') + $frame->get_margin_width());
            }
        }]]);
        $pdf->output();

        return $bottom !== null && $bottom < 152 * 72 / 25.4 && $right <= 289 * 72 / 25.4
            && $pdf->getDomPDF()->getCanvas()->get_page_count() === 1;
    }
}
