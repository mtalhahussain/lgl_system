@php
    $templateSrc = \App\Support\PdfImage::dataUri(public_path('template.png'));
    if (! $templateSrc) {
        \Illuminate\Support\Facades\Log::warning('Certificate template image missing; rendering on a plain background.', ['path' => public_path('template.png')]);
    }
    $soLine = trim($cert->student_name.($cert->father_name ? ' S/O '.$cert->father_name : ''));
    $dateDe = $cert->completion_date->copy()->locale('de')->translatedFormat('j. F Y');
@endphp
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Certificate {{ $cert->certificate_number }}</title>
<style>
    @font-face { font-family:'Certificate Sans'; src:url("{{ resource_path('fonts/certificates/Poppins-Regular.ttf') }}") format('truetype'); font-weight:400; font-style:normal; }
    @font-face { font-family:'Certificate Sans'; src:url("{{ resource_path('fonts/certificates/Poppins-BoldItalic.ttf') }}") format('truetype'); font-weight:700; font-style:italic; }
    @page { size: A4 landscape; margin: 0; }
    html, body { margin: 0; padding: 0; }
    body { font-family: 'DejaVu Serif', Georgia, serif; color: #1a1a1a; }
    .sheet { position: relative; width: 297mm; height: 210mm; overflow: hidden; }
    .bg { position: absolute; top: 0; left: 0; width: 297mm; height: 210mm; }
    {!! file_get_contents(resource_path('css/certificate-typography.css')) !!}
    /* Dompdf scales Poppins line boxes by its 1.5em font bounds × 1.1 font-height ratio.
       Compensate for that 1.65 factor so PDF baselines match browser CSS line heights. */
    .cert-grats { line-height:0.8181818; }
    .cert-line { line-height:0.7575758; }
    .cert-course { line-height:0.7272727; }
    .cert-date, .cert-text { line-height:0.7878788; }
    .cert-number { position: absolute; bottom: 6mm; right: 10mm; font-size: 8pt; color: #888; }
    .revoked-stamp { position: absolute; top: 80mm; left: 70mm; width: 157mm; text-align: center; border: 6px solid #c0202a; color: #c0202a; font-size: 54pt; font-weight: bold; letter-spacing: 6pt; padding: 6mm 0; transform: rotate(-18deg); opacity: .75; }
    .revoked-reason { position: absolute; bottom: 3mm; left: 10mm; font-size: 8pt; color: #c0202a; }
</style>
</head>
<body>
<div class="sheet">
    @if($templateSrc)<img class="bg" src="{{ $templateSrc }}" alt="">@endif
    <div class="cert-body">
        <h1 class="cert-title">Teilnahmebescheinigung</h1>
        <p class="cert-grats">Herzlichen Glückwunsch, <span class="cert-name">{{ $soLine }}</span></p>
        <p class="cert-line">zum erfolgreichen Abschluss des</p>
        <p class="cert-course">Deutsch-Sprachkurses &ndash; Niveau {{ $cert->level }}</p>
        <p class="cert-date">Kurs abgeschlossen am <strong>{{ $dateDe }}</strong></p>
        <p class="cert-text">{{ $cert->paragraphText() }}</p>
    </div>
    <div class="cert-number">{{ $cert->certificate_number }}</div>
    @if($cert->isRevoked())
        <div class="revoked-stamp">REVOKED</div>
        <div class="revoked-reason">Revoked: {{ $cert->revoke_reason }}</div>
    @endif
</div>
</body>
</html>
