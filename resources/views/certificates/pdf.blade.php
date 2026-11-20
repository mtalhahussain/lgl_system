@php
    $documentLogo = \App\Support\PdfImage::instituteLogo();
    $templateSrc = \App\Support\PdfImage::dataUri(public_path('template.png'));
    if (! $templateSrc) {
        \Illuminate\Support\Facades\Log::warning('Certificate template image missing; rendering on a plain background.', ['path' => public_path('template.png')]);
    }
    $soLine = trim($cert->student_name.($cert->father_name ? ' S/O '.$cert->father_name : ''));
    $dateDe = $cert->completion_date->format('d.m.Y');
@endphp
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Certificate {{ $cert->certificate_number }}</title>
<style>
    @page { size: A4 landscape; margin: 0; }
    html, body { margin: 0; padding: 0; }
    body { font-family: 'DejaVu Serif', Georgia, serif; color: #1a1a1a; }
    .sheet { position: relative; width: 297mm; height: 210mm; overflow: hidden; }
    .bg { position: absolute; top: 0; left: 0; width: 297mm; height: 210mm; }
    .certificate-logo { position:absolute; top:20mm; left:2mm; width:60mm; height:16mm; background:#fff; text-align:center; }
    .certificate-logo img { max-width:58mm; max-height:16mm; }
    .cert-body { position: absolute; top: 46mm; left: 120mm; width: 159mm; }
    .cert-title { font-size: 30pt; font-weight: bold; margin: 0 0 10mm; }
    .cert-grats { font-size: 15pt; font-style: italic; margin: 0 0 2mm; }
    .cert-name { color: #c0202a; font-weight: bold; font-style: italic; }
    .cert-line { font-size: 14pt; margin: 0 0 1mm; }
    .cert-course { font-size: 15pt; font-weight: bold; margin: 0 0 8mm; }
    .cert-date { font-size: 13pt; margin: 0 0 8mm; }
    .cert-text { font-size: 11.5pt; line-height: 1.5; width: 150mm; margin: 0; }
    .cert-number { position: absolute; bottom: 6mm; right: 10mm; font-size: 8pt; color: #888; }
    .revoked-stamp { position: absolute; top: 80mm; left: 70mm; width: 157mm; text-align: center; border: 6px solid #c0202a; color: #c0202a; font-size: 54pt; font-weight: bold; letter-spacing: 6pt; padding: 6mm 0; transform: rotate(-18deg); opacity: .75; }
    .revoked-reason { position: absolute; bottom: 3mm; left: 10mm; font-size: 8pt; color: #c0202a; }
</style>
</head>
<body>
<div class="sheet">
    @if($templateSrc)<img class="bg" src="{{ $templateSrc }}" alt="">@endif
    @if($documentLogo)<div class="certificate-logo"><img src="{{ $documentLogo }}" alt="Institute logo"></div>@endif
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
    @if($cert->isRevoked())
        <div class="revoked-stamp">REVOKED</div>
        <div class="revoked-reason">Revoked: {{ $cert->revoke_reason }}</div>
    @endif
</div>
</body>
</html>
