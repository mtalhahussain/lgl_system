@php
    $templatePath = storage_path('app/public/settings/template.png');
    $templateSrc = null;
    if (is_file($templatePath)) {
        $templateSrc = 'data:image/png;base64,'.base64_encode(file_get_contents($templatePath));
    } else {
        \Illuminate\Support\Facades\Log::warning('Certificate template image missing; rendering on a plain background.', ['path' => $templatePath]);
    }
    $soLine = trim($cert->student_name.($cert->father_name ? ' S/O '.$cert->father_name : ''));
    $dateDe = $cert->completion_date->format('d.m.Y');
@endphp
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate {{ $cert->certificate_number }}</title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { background: #e9e9e9; font-family: 'Georgia', 'Times New Roman', serif; color: #1a1a1a; }
        .sheet {
            position: relative;
            width: 297mm; height: 210mm;
            margin: 0 auto;
            background: #fff center/cover no-repeat;
            @if($templateSrc) background-image: url('{{ $templateSrc }}'); @endif
            overflow: hidden;
        }
        /* Text block over the empty centre/right of the template. */
        .cert-body {
            position: absolute;
            top: 46mm; left: 120mm; right: 18mm;
        }
        .cert-title { font-size: 30pt; font-weight: 700; margin: 0 0 10mm; letter-spacing: .3pt; }
        .cert-grats { font-size: 15pt; font-style: italic; margin: 0 0 2mm; }
        .cert-name { color: #c0202a; font-weight: 700; font-style: italic; }
        .cert-line { font-size: 14pt; margin: 0 0 1mm; }
        .cert-course { font-size: 15pt; font-weight: 700; margin: 0 0 8mm; }
        .cert-date { font-size: 13pt; margin: 0 0 8mm; }
        .cert-date strong { font-weight: 700; }
        .cert-text { font-size: 11.5pt; line-height: 1.5; max-width: 150mm; }
        .cert-number { position: absolute; bottom: 6mm; right: 10mm; font-size: 8pt; color: #888; }
        .toolbar { text-align: center; padding: 14px; }
        .toolbar button { font: inherit; padding: 8px 18px; cursor: pointer; }
        @media print { .toolbar { display: none; } body { background: #fff; } .sheet { margin: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>
    <div class="sheet">
        <div class="cert-body">
            <h1 class="cert-title">Teilnahmebescheinigung</h1>
            <p class="cert-grats">Herzlichen Glückwunsch, <span class="cert-name">{{ $soLine }}</span></p>
            <p class="cert-line">zum erfolgreichen Abschluss des</p>
            <p class="cert-course">Deutsch-Sprachkurses &ndash; Niveau {{ $cert->level }}</p>
            <p class="cert-date">Kurs abgeschlossen am <strong>{{ $dateDe }}</strong></p>
            <p class="cert-text">
                Ihr Einsatz beim Erlernen der deutschen Sprache war lobenswert. Auf dem
                {{ $cert->level }}-Niveau haben Sie grundlegende sprachliche Fähigkeiten erworben
                und erste einfache Kommunikationssituationen sicher gemeistert.
            </p>
        </div>
        <div class="cert-number">{{ $cert->certificate_number }}</div>
    </div>
</body>
</html>
