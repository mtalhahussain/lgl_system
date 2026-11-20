@php
    $documentLogo = \App\Support\PdfImage::instituteLogo();
    $templatePath = public_path('template.png');
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
    @vite('resources/css/certificate-print.css')
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('certificates.pdf', [$cert, 'download' => 1]) }}">Download PDF</a>
        <a href="{{ route('certificates.pdf', $cert) }}" target="_blank" rel="noopener">Print PDF</a>
    </div>
    <div class="sheet">
        @if($templateSrc)<img class="certificate-background" src="{{ $templateSrc }}" alt="">@endif
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
            <div class="revoked-overlay">
                <span class="revoked-stamp">REVOKED</span>
            </div>
            <div class="revoked-reason">Revoked: {{ $cert->revoke_reason }}</div>
        @endif
    </div>
</body>
</html>
